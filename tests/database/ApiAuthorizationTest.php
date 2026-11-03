<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Security\Exceptions\SecurityException;
use App\Libraries\OfflineGrantService;
use Config\KintaAuth;

/** @internal */
final class ApiAuthorizationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $DBGroup = 'tests';
    protected $namespace = 'App';
    private string $privateKeyPath;
    private int $ownerId;
    private int $otherSupervisorId;
    private OfflineGrantService $grantService;

    protected function setUp(): void
    {
        parent::setUp();

        $opensslConfig = tempnam(sys_get_temp_dir(), 'kinta-openssl-');
        self::assertNotFalse($opensslConfig);
        file_put_contents($opensslConfig, "[ req ]\ndistinguished_name = req_dn\n[ req_dn ]\nprompt = no\n");
        $key = openssl_pkey_new([
            'config' => $opensslConfig,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]);
        $this->privateKeyPath = tempnam(sys_get_temp_dir(), 'kinta-test-key-');
        self::assertNotFalse($key);
        self::assertNotFalse($this->privateKeyPath);
        self::assertTrue(openssl_pkey_export_to_file($key, $this->privateKeyPath, null, ['config' => $opensslConfig]));
        unlink($opensslConfig);

        $authConfig = config(KintaAuth::class);
        $authConfig->offlinePrivateKeyPath = $this->privateKeyPath;
        $this->grantService = new OfflineGrantService($authConfig);
        $this->ownerId = $this->createSupervisor('test.owner');
        $this->otherSupervisorId = $this->createSupervisor('test.other');
    }

    protected function tearDown(): void
    {
        if (isset($this->privateKeyPath) && is_file($this->privateKeyPath)) {
            unlink($this->privateKeyPath);
        }
        parent::tearDown();
    }

    public function testApiRejectsMissingGrantEvenWhenIdentityHeadersAreForged(): void
    {
        $response = $this->withHeaders([
            'X-Kinta-Auth-User-Id' => '1',
            'X-Kinta-Auth-Role' => 'supervisor',
        ])->post('/api/processes/sync', ['processes' => []]);

        $response->assertStatus(401);
        $response->assertJSONFragment(['error_code' => 'AUTH_REQUIRED']);
        self::assertSame(0, $this->db->table('processes')->countAllResults());
        self::assertSame(0, $this->db->table('process_sync_receipts')->countAllResults());
    }

    public function testAdminRoutesRejectUnauthenticatedSession(): void
    {
        $response = $this->get('/admin/');

        $response->assertRedirectTo('/admin/login');
        self::assertSame(2, $this->db->table('workforce')->countAllResults());
    }

    public function testAdminLoginAcceptsFreshSessionCsrfToken(): void
    {
        $loginPage = $this->get('/admin/login');
        $loginPage->assertStatus(200);
        self::assertSame(1, preg_match('/name="csrf_test_name" value="([^"]+)"/', $loginPage->getBody(), $matches));

        $validPost = $this->post('/admin/login', [
            'csrf_test_name' => $matches[1],
            'username' => 'csrf-probe-invalid-user',
            'password' => 'synthetic-invalid-probe',
        ]);
        $validPost->assertStatus(200);
        $validPost->assertSee('Email/username or password is incorrect.');
    }

    public function testAdminLoginRejectsMissingCsrfToken(): void
    {
        $this->get('/admin/login')->assertStatus(200);
        $this->expectException(SecurityException::class);
        $this->post('/admin/login', [
            'username' => 'csrf-probe-invalid-user',
            'password' => 'synthetic-invalid-probe',
        ]);
    }

    public function testAdminLoginRejectsInvalidCsrfToken(): void
    {
        $this->get('/admin/login')->assertStatus(200);
        $this->expectException(SecurityException::class);
        $this->post('/admin/login', [
            'csrf_test_name' => 'invalid-synthetic-token',
            'username' => 'csrf-probe-invalid-user',
            'password' => 'synthetic-invalid-probe',
        ]);
    }

    public function testValidGrantAllowsAuthorizedApiRead(): void
    {
        $response = $this->withHeaders($this->authHeaders($this->ownerId))->get('/api/processes');

        $response->assertStatus(200);
        $response->assertJSONFragment(['success' => true]);
    }

    public function testGrantWithoutRequiredPermissionIsForbidden(): void
    {
        $token = $this->signedGrant($this->ownerId, ['process.read']);
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->post('/api/processes/sync', ['processes' => []]);

        $response->assertStatus(403);
        $response->assertJSONFragment(['error_code' => 'PERMISSION_DENIED']);
        self::assertSame(0, $this->db->table('processes')->countAllResults());
        self::assertSame(0, $this->db->table('process_sync_receipts')->countAllResults());
    }

    public function testExpiredGrantIsUnauthorized(): void
    {
        $token = $this->signedGrant($this->ownerId, ['process.read'], time() - 1);
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])->get('/api/processes');

        $response->assertStatus(401);
        $response->assertJSONFragment(['error_code' => 'AUTH_REQUIRED']);
    }

    public function testGrantIsRejectedWhenCurrentAccountIsInactiveOrRoleChanged(): void
    {
        $token = $this->authHeaders($this->ownerId)['Authorization'];
        $this->db->table('workforce')->where('id', $this->ownerId)->update(['role' => 'operator']);
        $changedRole = $this->withHeaders(['Authorization' => $token])->get('/api/processes');
        $changedRole->assertStatus(401);

        $this->db->table('workforce')->where('id', $this->ownerId)->update(['role' => 'supervisor', 'status' => 'inactive']);
        $inactive = $this->withHeaders(['Authorization' => $token])->get('/api/processes');
        $inactive->assertStatus(401);
    }

    public function testOwnerCanArchiveCompletedProcessAndOtherSupervisorCannot(): void
    {
        $processId = $this->createCompletedProcess($this->ownerId);

        $denied = $this->withHeaders($this->authHeaders($this->otherSupervisorId))->delete('/api/processes/' . $processId);
        $denied->assertStatus(404);
        self::assertNull($this->db->table('processes')->where('id', $processId)->get()->getRowArray()['deleted_at']);

        $allowed = $this->withHeaders($this->authHeaders($this->ownerId))->delete('/api/processes/' . $processId);
        $allowed->assertStatus(200);
        self::assertNotNull($this->db->table('processes')->where('id', $processId)->get()->getRowArray()['deleted_at']);
        self::assertSame($this->ownerId, (int) $this->db->table('processes')->where('id', $processId)->get()->getRowArray()['deleted_by_workforce_id']);
    }

    public function testForgedActorIdCannotArchiveAnotherSupervisorsProcess(): void
    {
        $processId = $this->createCompletedProcess($this->ownerId);
        $response = $this->withHeaders([
            ...$this->authHeaders($this->otherSupervisorId),
            'X-Kinta-Auth-User-Id' => (string) $this->ownerId,
        ])->delete('/api/processes/' . $processId);

        $response->assertStatus(404);
        $process = $this->db->table('processes')->where('id', $processId)->get()->getRowArray();
        self::assertNull($process['deleted_at']);
    }

    public function testOfflineStartRetryCreatesOneProcessAndOneReceipt(): void
    {
        [$machineId, $sectorId, $activityId] = $this->createActiveMasterData();
        $qcUserId = $this->createUser('test.qc', 'qc');
        $payload = [
            'actor_user_id' => $this->ownerId,
            'local_process_id' => 'offline-retry-001',
            'process' => [
                'machine_id' => $machineId,
                'sector_id' => $sectorId,
                'activity_id' => $activityId,
                'current_supervisor_id' => $this->ownerId,
                'started_at' => '2026-11-03 10:00:00',
                'description' => 'Synthetic offline retry test',
            ],
            'qc_inspections' => [[
                'client_action_key' => 'offline-retry-001:qc:first',
                'qc_user_id' => $qcUserId,
                'quantity' => 7,
                'inspected_at' => '2026-11-03 10:15:00',
            ]],
        ];
        $headers = $this->authHeaders($this->ownerId);
        $first = $this->withHeaders($headers)->withBodyFormat('json')->post('/api/processes/sync', $payload);
        $first->assertStatus(200);
        $firstData = json_decode($first->getJSON(), true)['data'];
        self::assertSame('2026-11-03 02:00:00', $this->db->table('processes')->where('id', $firstData['process_id'])->get()->getRowArray()['started_at']);

        $retryPayload = [
            'actor_user_id' => $this->ownerId,
            'local_process_id' => 'offline-retry-001',
            'process' => [
                'machine_id' => (string) $machineId,
                'sector_id' => (string) $sectorId,
                'activity_id' => (string) $activityId,
                'current_supervisor_workforce_id' => (string) $this->ownerId,
                'sales_order_id' => null,
                'operator_ids' => [],
                'started_at' => '2026-11-03T02:00:00Z',
                'description' => 'Synthetic offline retry test',
            ],
            'qc_inspections' => [[
                'client_action_key' => 'offline-retry-001:qc:first',
                'qc_workforce_id' => (string) $qcUserId,
                'quantity' => '7',
                'inspected_at' => '2026-11-03T02:15:00Z',
            ]],
        ];
        $retry = $this->withHeaders($headers)->withBodyFormat('json')->post('/api/processes/sync', $retryPayload);
        $retry->assertStatus(200);
        $retryData = json_decode($retry->getJSON(), true)['data'];

        self::assertSame($firstData['process_id'], $retryData['process_id']);
        self::assertSame(1, $this->db->table('processes')->countAllResults());
        self::assertSame(2, $this->db->table('process_sync_receipts')->countAllResults());
        self::assertSame(1, $this->db->table('process_pic_history')->countAllResults());
        self::assertSame(1, $this->db->table('qc_inspections')->countAllResults());
    }

    public function testActionKeyCannotBeReusedForAnotherActionType(): void
    {
        [$machineId, $sectorId, $activityId] = $this->createActiveMasterData();
        $qcUserId = $this->createUser('test.qc.conflict', 'qc');
        $firstPayload = [
            'actor_user_id' => $this->ownerId,
            'local_process_id' => 'offline-conflict-source',
            'process' => [
                'machine_id' => $machineId,
                'sector_id' => $sectorId,
                'activity_id' => $activityId,
                'current_supervisor_id' => $this->ownerId,
            ],
            'qc_inspections' => [[
                'client_action_key' => 'offline-conflict-target:start',
                'qc_user_id' => $qcUserId,
                'quantity' => 1,
            ]],
        ];
        $created = $this->withHeaders($this->authHeaders($this->ownerId))->withBodyFormat('json')
            ->post('/api/processes/sync', $firstPayload);
        $created->assertStatus(200);

        $conflict = $this->withHeaders($this->authHeaders($this->ownerId))->withBodyFormat('json')
            ->post('/api/processes/sync', [
                'actor_user_id' => $this->ownerId,
                'local_process_id' => 'offline-conflict-target',
                'process' => [
                    'machine_id' => $machineId,
                    'sector_id' => $sectorId,
                    'activity_id' => $activityId,
                    'current_supervisor_id' => $this->ownerId,
                ],
            ]);

        $conflict->assertStatus(409);
        $conflict->assertJSONFragment(['error_code' => 'SYNC_CONFLICT']);
        self::assertSame(1, $this->db->table('processes')->countAllResults());
        self::assertSame(2, $this->db->table('process_sync_receipts')->countAllResults());
        self::assertSame(1, $this->db->table('qc_inspections')->countAllResults());
        $receipt = $this->db->table('process_sync_receipts')->where('client_action_key', 'offline-conflict-target:start')->get()->getRowArray();
        self::assertSame('qc', $receipt['action_type']);
        self::assertSame('active', $this->db->table('processes')->get()->getRowArray()['status']);
    }

    public function testSameActionKeyWithChangedQcPayloadConflictsWithoutChangingTheEvent(): void
    {
        [$machineId, $sectorId, $activityId] = $this->createActiveMasterData();
        $qcUserId = $this->createUser('test.qc.changed-payload', 'qc');
        $payload = [
            'actor_user_id' => $this->ownerId,
            'local_process_id' => 'offline-changed-payload',
            'process' => [
                'machine_id' => $machineId,
                'sector_id' => $sectorId,
                'activity_id' => $activityId,
                'current_supervisor_id' => $this->ownerId,
            ],
            'qc_inspections' => [[
                'client_action_key' => 'offline-changed-payload:qc:one',
                'qc_user_id' => $qcUserId,
                'quantity' => 7,
                'inspected_at' => '2026-11-03 10:15:00',
            ]],
        ];

        $first = $this->withHeaders($this->authHeaders($this->ownerId))->withBodyFormat('json')
            ->post('/api/processes/sync', $payload);
        $first->assertStatus(200);

        $changedPayload = $payload;
        $changedPayload['qc_inspections'][0]['quantity'] = 8;
        $conflict = $this->withHeaders($this->authHeaders($this->ownerId))->withBodyFormat('json')
            ->post('/api/processes/sync', $changedPayload);

        $conflict->assertStatus(409);
        $conflict->assertJSONFragment(['error_code' => 'SYNC_CONFLICT']);
        self::assertSame(1, $this->db->table('processes')->countAllResults());
        self::assertSame(2, $this->db->table('process_sync_receipts')->countAllResults());
        self::assertSame(1, $this->db->table('qc_inspections')->countAllResults());
        self::assertSame(7, (int) $this->db->table('qc_inspections')->get()->getRowArray()['quantity']);
    }

    public function testSameActionKeyBoundToAnotherProcessConflictsWithoutModifyingEitherProcess(): void
    {
        $firstProcessId = $this->createCompletedProcess($this->ownerId);
        $secondProcessId = $this->createCompletedProcess($this->ownerId);
        $qcUserId = $this->createUser('test.qc.process-binding', 'qc');
        $firstPayload = [
            'actor_user_id' => $this->ownerId,
            'local_process_id' => 'offline-binding-shared',
            'server_process_id' => $firstProcessId,
            'qc_inspections' => [[
                'client_action_key' => 'offline-binding-qc',
                'qc_user_id' => $qcUserId,
                'quantity' => 3,
                'inspected_at' => '2026-11-03 10:15:00',
            ]],
        ];
        $first = $this->withHeaders($this->authHeaders($this->ownerId))->withBodyFormat('json')
            ->post('/api/processes/sync', $firstPayload);
        $first->assertStatus(200);

        $changedBinding = $firstPayload;
        $changedBinding['server_process_id'] = $secondProcessId;
        $conflict = $this->withHeaders($this->authHeaders($this->ownerId))->withBodyFormat('json')
            ->post('/api/processes/sync', $changedBinding);

        $conflict->assertStatus(409);
        $conflict->assertJSONFragment(['error_code' => 'SYNC_CONFLICT']);
        self::assertSame(2, $this->db->table('processes')->countAllResults());
        self::assertSame(1, $this->db->table('process_sync_receipts')->countAllResults());
        self::assertSame(1, $this->db->table('qc_inspections')->countAllResults());
        self::assertSame(2, $this->db->table('processes')->where('status', 'completed')->countAllResults());
        self::assertSame(1, $this->db->table('qc_inspections')->where('process_id', $firstProcessId)->countAllResults());
        self::assertSame(0, $this->db->table('qc_inspections')->where('process_id', $secondProcessId)->countAllResults());
    }

    public function testLegacyReceiptWithoutFingerprintFailsClosed(): void
    {
        [$machineId, $sectorId, $activityId] = $this->createActiveMasterData();
        $payload = [
            'actor_user_id' => $this->ownerId,
            'local_process_id' => 'offline-legacy-receipt',
            'process' => [
                'machine_id' => $machineId,
                'sector_id' => $sectorId,
                'activity_id' => $activityId,
                'current_supervisor_id' => $this->ownerId,
            ],
        ];
        $first = $this->withHeaders($this->authHeaders($this->ownerId))->withBodyFormat('json')
            ->post('/api/processes/sync', $payload);
        $first->assertStatus(200);
        $this->db->table('process_sync_receipts')
            ->where('client_action_key', 'offline-legacy-receipt:start')
            ->update(['request_fingerprint' => null]);

        $conflict = $this->withHeaders($this->authHeaders($this->ownerId))->withBodyFormat('json')
            ->post('/api/processes/sync', $payload);

        $conflict->assertStatus(409);
        $conflict->assertJSONFragment(['error_code' => 'SYNC_CONFLICT']);
        self::assertSame(1, $this->db->table('processes')->countAllResults());
        self::assertSame(1, $this->db->table('process_sync_receipts')->countAllResults());
    }

    public function testNormalSyncActionsRemainIdempotentAcrossStartQcPicEndAndProblem(): void
    {
        [$machineId, $sectorId, $activityId] = $this->createActiveMasterData();
        $qcUserId = $this->createUser('test.qc.normal-sync', 'qc');
        $operatorId = $this->createUser('test.operator.normal-sync', 'operator');
        $nextSupervisorId = $this->createSupervisor('test.supervisor.normal-sync');
        $now = date('Y-m-d H:i:s');
        $this->db->table('problems')->insert([
            'code' => 'TEST-NORMAL-SYNC-' . bin2hex(random_bytes(4)),
            'name' => 'TEST Normal Sync Problem',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $problemId = (int) $this->db->insertID();

        $payload = [
            'actor_user_id' => $this->ownerId,
            'local_process_id' => 'offline-normal-action-flow',
            'process' => [
                'machine_id' => $machineId,
                'sector_id' => $sectorId,
                'activity_id' => $activityId,
                'current_supervisor_id' => $this->ownerId,
                'operator_ids' => [$operatorId],
                'started_at' => '2026-11-03 10:00:00',
            ],
            'qc_inspections' => [[
                'client_action_key' => 'normal-flow-qc',
                'qc_user_id' => $qcUserId,
                'quantity' => 4,
                'inspected_at' => '2026-11-03 10:15:00',
            ]],
            'pic_history' => [
                ['supervisor_id' => $this->ownerId, 'started_at' => '2026-11-03 10:00:00'],
                [
                    'client_action_key' => 'normal-flow-pic',
                    'supervisor_workforce_id' => $nextSupervisorId,
                    'started_at' => '2026-11-03 10:30:00',
                ],
            ],
            'end' => [
                'client_action_key' => 'normal-flow-end',
                'qty_passed' => 4,
                'qty_rejected' => 0,
                'remarks' => '  Completed normally  ',
                'ended_at' => '2026-11-03 11:00:00',
            ],
            'problems' => [[
                'client_action_key' => 'normal-flow-problem',
                'problem_id' => $problemId,
                'reported_at' => '2026-11-03 10:45:00',
            ]],
        ];
        $headers = $this->authHeaders($this->ownerId);
        $first = $this->withHeaders($headers)->withBodyFormat('json')->post('/api/processes/sync', $payload);
        $first->assertStatus(200);
        $processId = (int) json_decode($first->getJSON(), true)['data']['process_id'];

        $retryPayload = $payload;
        $retryPayload['server_process_id'] = $processId;
        $retry = $this->withHeaders($headers)->withBodyFormat('json')->post('/api/processes/sync', $retryPayload);
        $retry->assertStatus(200);

        self::assertSame(1, $this->db->table('processes')->countAllResults());
        self::assertSame(5, $this->db->table('process_sync_receipts')->countAllResults());
        self::assertSame(1, $this->db->table('qc_inspections')->countAllResults());
        self::assertSame(2, $this->db->table('process_pic_history')->countAllResults());
        self::assertSame(1, $this->db->table('process_operators')->countAllResults());
        self::assertSame(1, $this->db->table('process_problems')->countAllResults());
        $process = $this->db->table('processes')->where('id', $processId)->get()->getRowArray();
        self::assertSame('completed', $process['status']);
        self::assertSame('Completed normally', $process['remarks']);
    }

    public function testOfflineSyncCannotClaimAnotherAccountsQueuedProcess(): void
    {
        [$machineId, $sectorId, $activityId] = $this->createActiveMasterData();
        $payload = [
            'actor_user_id' => $this->ownerId,
            'local_process_id' => 'offline-wrong-actor-001',
            'process' => [
                'machine_id' => $machineId,
                'sector_id' => $sectorId,
                'activity_id' => $activityId,
                'current_supervisor_id' => $this->ownerId,
            ],
        ];

        $response = $this->withHeaders($this->authHeaders($this->otherSupervisorId))
            ->withBodyFormat('json')->post('/api/processes/sync', $payload);

        $response->assertStatus(403);
        $response->assertJSONFragment(['error_code' => 'ACTOR_MISMATCH']);
        self::assertSame(0, $this->db->table('processes')->countAllResults());
        self::assertSame(0, $this->db->table('process_sync_receipts')->countAllResults());
    }

    public function testMalformedOfflineSyncDoesNotCreateBusinessRows(): void
    {
        $response = $this->withHeaders($this->authHeaders($this->ownerId))
            ->withBodyFormat('json')->post('/api/processes/sync', [
                'actor_user_id' => $this->ownerId,
                'local_process_id' => 'invalid id with spaces',
                'process' => [],
            ]);

        $response->assertStatus(422);
        self::assertSame(0, $this->db->table('processes')->countAllResults());
        self::assertSame(0, $this->db->table('process_sync_receipts')->countAllResults());
    }

    public function testOfflineSyncRejectsMissingStartReferencesWithoutSideEffects(): void
    {
        $response = $this->withHeaders($this->authHeaders($this->ownerId))
            ->withBodyFormat('json')->post('/api/processes/sync', [
                'actor_user_id' => $this->ownerId,
                'local_process_id' => 'offline-missing-references',
                'process' => [],
            ]);

        $response->assertStatus(422);
        self::assertSame(0, $this->db->table('processes')->countAllResults());
        self::assertSame(0, $this->db->table('process_sync_receipts')->countAllResults());
    }

    private function createSupervisor(string $username): int
    {
        return $this->createUser($username, 'supervisor');
    }

    private function createUser(string $username, string $role): int
    {
        $now = date('Y-m-d H:i:s');
        $this->db->table('workforce')->insert([
            'staff_id' => strtoupper(str_replace('.', '-', $username)),
            'name' => 'TEST - ' . $username,
            'username' => $username,
            'password_hash' => null,
            'role' => $role,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->db->insertID();
    }

    private function createCompletedProcess(int $ownerId): int
    {
        $now = date('Y-m-d H:i:s');
        $suffix = bin2hex(random_bytes(4));
        $this->db->table('machines')->insert(['machine_code' => 'TEST-MACHINE-' . $ownerId . '-' . $suffix, 'machine_name' => 'TEST Machine', 'status' => 'active', 'created_at' => $now]);
        $machineId = (int) $this->db->insertID();
        $this->db->table('sectors')->insert(['name' => 'TEST Sector ' . $ownerId . '-' . $suffix, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        $sectorId = (int) $this->db->insertID();
        $this->db->table('activities')->insert(['sector_id' => $sectorId, 'name' => 'TEST Activity ' . $ownerId . '-' . $suffix, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        $activityId = (int) $this->db->insertID();
        $this->db->table('processes')->insert([
            'machine_id' => $machineId,
            'sector_id' => $sectorId,
            'activity_id' => $activityId,
            'sales_order_id' => null,
            'current_supervisor_workforce_id' => $ownerId,
            'created_by_workforce_id' => $ownerId,
            'description' => 'Synthetic API ownership test record',
            'status' => 'completed',
            'started_at' => $now,
            'ended_at' => $now,
            'qty_passed' => 1,
            'qty_rejected' => 0,
            'remarks' => 'Synthetic test only',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->db->insertID();
    }

    /** @return array{int, int, int} */
    private function createActiveMasterData(): array
    {
        $now = date('Y-m-d H:i:s');
        $this->db->table('machines')->insert(['machine_code' => 'TEST-SYNC-MACHINE-' . bin2hex(random_bytes(4)), 'machine_name' => 'TEST Sync Machine', 'status' => 'active', 'created_at' => $now]);
        $machineId = (int) $this->db->insertID();
        $this->db->table('sectors')->insert(['name' => 'TEST Sync Sector ' . bin2hex(random_bytes(4)), 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        $sectorId = (int) $this->db->insertID();
        $this->db->table('activities')->insert(['sector_id' => $sectorId, 'name' => 'TEST Sync Activity ' . bin2hex(random_bytes(4)), 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        $activityId = (int) $this->db->insertID();

        return [$machineId, $sectorId, $activityId];
    }

    /** @return array<string, string> */
    private function authHeaders(int $userId): array
    {
        return ['Authorization' => 'Bearer ' . $this->grantService->issue(['id' => $userId, 'role' => 'supervisor'])['token']];
    }

    /** @param list<string> $permissions */
    private function signedGrant(int $userId, array $permissions, ?int $expiresAt = null): string
    {
        $now = time();
        $header = ['alg' => 'RS256', 'typ' => 'JWT', 'kid' => config(KintaAuth::class)->grantKeyId];
        $claims = [
            'iss' => config(KintaAuth::class)->grantIssuer,
            'aud' => config(KintaAuth::class)->grantAudience,
            'sub' => (string) $userId,
            'user_id' => $userId,
            'role' => 'supervisor',
            'permissions' => $permissions,
            'offline_allowed' => true,
            'iat' => $now - 120,
            'nbf' => $now - 120,
            'exp' => $expiresAt ?? ($now + 600),
            'jti' => bin2hex(random_bytes(16)),
        ];
        $encode = static fn (string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
        $input = $encode(json_encode($header, JSON_THROW_ON_ERROR)) . '.' . $encode(json_encode($claims, JSON_THROW_ON_ERROR));
        $privateKey = openssl_pkey_get_private((string) file_get_contents($this->privateKeyPath));
        self::assertNotFalse($privateKey);
        self::assertTrue(openssl_sign($input, $signature, $privateKey, OPENSSL_ALGO_SHA256));

        return $input . '.' . $encode($signature);
    }
}
