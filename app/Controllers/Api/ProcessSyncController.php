<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use App\Libraries\OfflineGrantService;
use Throwable;

/**
 * Receives locally saved process data using one retry-safe transaction.
 */
class ProcessSyncController extends ResourceController
{
    private ?int $actorUserId = null;

    public function preflight()
    {
        return $this->response->setStatusCode(204);
    }

    public function sync()
    {
        $payload = $this->request->getJSON(true);
        if (! is_array($payload)) {
            return $this->respondWithError('A JSON request body is required.', 400);
        }

        $authenticatedUserId = (int) $this->request->getHeaderLine('X-Kinta-Auth-User-Id');
        $payloadActorId = $this->positiveId($payload['actor_user_id'] ?? $payload['actor_workforce_id'] ?? null);
        if ($authenticatedUserId < 1 || $payloadActorId !== $authenticatedUserId) {
            return $this->respondWithError('The queued process belongs to a different or unverified account.', 403, 'ACTOR_MISMATCH');
        }
        $this->actorUserId = $authenticatedUserId;

        $localId = $payload['local_process_id'] ?? null;
        if (! is_string($localId) || $localId === '' || strlen($localId) > 80 || ! preg_match('/^[A-Za-z0-9_-]+$/', $localId)) {
            return $this->respondWithError('local_process_id is invalid.', 422);
        }

        $serverProcessId = $this->positiveId($payload['server_process_id'] ?? null);
        if (array_key_exists('server_process_id', $payload) && $payload['server_process_id'] !== null && $serverProcessId === null) {
            return $this->respondWithError('server_process_id must be a positive integer or null.', 422);
        }

        $processData = $payload['process'] ?? [];
        $qcInspections = $payload['qc_inspections'] ?? [];
        $picHistory = $payload['pic_history'] ?? [];
        $problems = $payload['problems'] ?? [];
        $end = $payload['end'] ?? null;
        if (! is_array($processData) || ! is_array($qcInspections) || ! array_is_list($qcInspections)
            || ! is_array($picHistory) || ! array_is_list($picHistory)
            || ! is_array($problems) || ! array_is_list($problems)
            || ($end !== null && ! is_array($end))) {
            return $this->respondWithError('The process sync payload has an invalid structure.', 422);
        }

        // Re-check every operation against the current role when syncing.
        $rolePermissions = OfflineGrantService::permissionsForRole(
            $this->request->getHeaderLine('X-Kinta-Auth-Role'),
        );
        $requestedPermissions = [];
        if ($serverProcessId === null || $end !== null) {
            $requestedPermissions[] = 'process.write';
        }
        if (($processData['operator_ids'] ?? []) !== []) {
            $requestedPermissions[] = 'operator.assign';
        }
        if ($qcInspections !== []) {
            $requestedPermissions[] = 'qc.write';
        }
        if (count($picHistory) > 1) {
            $requestedPermissions[] = 'pic.change';
        }
        if ($problems !== []) {
            $requestedPermissions[] = 'problem.report';
        }
        foreach (array_unique($requestedPermissions) as $permission) {
            if (! in_array($permission, $rolePermissions, true)) {
                return $this->respondWithError(
                    'The current account role cannot synchronize one or more queued actions.',
                    403,
                    'PERMISSION_DENIED',
                );
            }
        }

        $machineId = $this->positiveId($processData['machine_id'] ?? null);
        $sectorId = $this->positiveId($processData['sector_id'] ?? null);
        $activityId = $this->positiveId($processData['activity_id'] ?? null);
        $operatorIds = $processData['operator_ids'] ?? [];
        if (! is_array($operatorIds) || ! array_is_list($operatorIds)) {
            return $this->respondWithError('operator_ids must be a list of IDs.', 422);
        }
        $operatorIds = array_map(fn ($id) => $this->positiveId($id), $operatorIds);
        if (in_array(null, $operatorIds, true)) {
            return $this->respondWithError('Every operator ID must be positive.', 422);
        }
        $operatorIds = array_values(array_unique($operatorIds));

        $salesOrderId = null;
        if (isset($processData['sales_order_id']) && $processData['sales_order_id'] !== '') {
            $salesOrderId = $this->positiveId($processData['sales_order_id']);
            if ($salesOrderId === null) {
                return $this->respondWithError('sales_order_id must be a positive integer or null.', 422);
            }
        }

        $initialSupervisorId = null;
        if ($picHistory !== [] && is_array($picHistory[0])) {
            $initialSupervisorId = $this->positiveId($picHistory[0]['supervisor_id'] ?? [0]['supervisor_id'] ?? $row['supervisor_workforce_id'] ?? null);
        }
        $initialSupervisorId ??= $this->positiveId($processData['current_supervisor_id'] ?? ['current_supervisor_workforce_id'] ?? null);

        $startKey = $localId . ':start';
        $db = db_connect();
        if ($serverProcessId === null) {
            $knownStart = $db->table('process_sync_receipts')
                ->where('client_action_key', $startKey)
                ->get()->getRowArray();
            if ($knownStart !== null) {
                $serverProcessId = (int) $knownStart['process_id'];
            }
        }

        if ($serverProcessId === null && ($machineId === null || $sectorId === null || $activityId === null || $initialSupervisorId === null)) {
            return $this->respondWithError('Machine, sector, activity, and an initial supervisor are required to sync a locally started process.', 422);
        }

        if ($serverProcessId === null) {
            if (! $this->activeRecordExists($db, 'machines', $machineId)
                || ! $this->activeRecordExists($db, 'sectors', $sectorId)) {
                return $this->respondWithError('The selected machine or sector is no longer active.', 422);
            }
            if (! $db->table('activities')->where('id', $activityId)->where('sector_id', $sectorId)->where('status', 'active')->countAllResults()) {
                return $this->respondWithError('The selected activity is no longer active in this sector.', 422);
            }
            if (! $this->userExistsWithRole($db, $initialSupervisorId, 'supervisor')) {
                return $this->respondWithError('The initial supervisor is no longer active.', 422);
            }
            if ($salesOrderId !== null && ! $db->table('sales_orders')->where('id', $salesOrderId)->countAllResults()) {
                return $this->respondWithError('The selected sales order no longer exists.', 422);
            }
            if ($operatorIds !== [] && $db->table('workforce')->whereIn('id', $operatorIds)->where('role', 'operator')->where('status', 'active')->countAllResults() !== count($operatorIds)) {
                return $this->respondWithError('One or more selected operators are no longer active.', 422);
            }
        }

        $now = date('Y-m-d H:i:s');
        $db->transBegin();

        try {
            if ($serverProcessId === null) {
                $startReceipt = $db->table('process_sync_receipts')
                    ->where('client_action_key', $startKey)
                    ->get()->getRowArray();
                if ($startReceipt !== null) {
                    $serverProcessId = (int) $startReceipt['process_id'];
                } else {
                    $startedAt = $this->dateTimeValue($processData['started_at'] ?? null, $now);
                    $description = $processData['description'] ?? null;
                    if ($description !== null && ! is_string($description)) {
                        throw new \RuntimeException('description must be a string or null.', 422);
                    }
                    $db->table('processes')->insert([
                        'created_by_workforce_id' => $this->actorUserId,
                        'machine_id' => $machineId,
                        'sector_id' => $sectorId,
                        'activity_id' => $activityId,
                        'sales_order_id' => $salesOrderId,
                        'current_supervisor_workforce_id' => $initialSupervisorId,
                        'description' => $description,
                        'status' => 'active',
                        'started_at' => $startedAt,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $serverProcessId = (int) $db->insertID();
                    $db->table('process_pic_history')->insert([
                        'process_id' => $serverProcessId,
                        'supervisor_workforce_id' => $initialSupervisorId,
                        'started_at' => $startedAt,
                        'created_at' => $now,
                    ]);
                    if ($operatorIds !== []) {
                        $assignments = array_map(static fn (int $operatorId): array => [
                            'process_id' => $serverProcessId,
                            'workforce_id' => $operatorId,
                            'assigned_at' => $startedAt,
                            'created_at' => $now,
                        ], $operatorIds);
                        $db->table('process_operators')->insertBatch($assignments);
                    }
                    $this->recordReceipt($db, $startKey, $serverProcessId, 'start', $now);
                }
            }

            $process = $db->table('processes')->where('id', $serverProcessId)->get()->getRowArray();
            if ($process === null) {
                throw new \RuntimeException('The server process could not be found.', 404);
            }
            if ($process['deleted_at'] !== null) {
                throw new \RuntimeException('This process was archived on the server. Review the local record before syncing more changes.', 409);
            }
            if ((int) ($process['created_by_workforce_id'] ?? 0) !== $this->actorUserId) {
                throw new \RuntimeException('This process is owned by another account or predates account binding. Review it before syncing.', 409);
            }

            foreach ($qcInspections as $index => $inspection) {
                if (! is_array($inspection)) {
                    throw new \RuntimeException('A QC inspection has an invalid structure.', 422);
                }
                $key = $this->eventKey($inspection['client_action_key'] ?? null, $localId . ':qc:' . $index);
                if ($this->hasReceipt($db, $key)) {
                    continue;
                }
                $qcUserId = $this->positiveId($inspection['qc_user_id'] ?? $inspection['qc_workforce_id'] ?? null);
                $validQuantity = true;
                $quantity = $this->optionalQuantity(['quantity' => $inspection['quantity'] ?? null], 'quantity', $validQuantity);
                if ($qcUserId === null || ! $validQuantity || $quantity === null || ! $this->userExistsWithRole($db, $qcUserId, 'qc')) {
                    throw new \RuntimeException('A queued QC inspection contains an invalid or inactive QC user or quantity.', 422);
                }
                $inspectedAt = $this->dateTimeValue($inspection['inspected_at'] ?? null, $now);
                $db->table('qc_inspections')->insert([
                    'process_id' => $serverProcessId,
                    'qc_workforce_id' => $qcUserId,
                    'inspection_time' => $inspectedAt,
                    'quantity' => $quantity,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->recordReceipt($db, $key, $serverProcessId, 'qc', $now);
            }

            $localPicIds = [];
            foreach ($picHistory as $row) {
                if (! is_array($row)) {
                    throw new \RuntimeException('PIC history contains an invalid row.', 422);
                }
                $supervisorId = $this->positiveId($row['supervisor_id'] ?? $row['supervisor_workforce_id'] ?? null);
                if ($supervisorId === null) {
                    throw new \RuntimeException('PIC history contains an invalid supervisor ID.', 422);
                }
                $localPicIds[] = $supervisorId;
            }
            if ($localPicIds !== []) {
                $serverPicRows = $db->table('process_pic_history')
                    ->select('supervisor_workforce_id')->where('process_id', $serverProcessId)
                    ->orderBy('started_at', 'ASC')->orderBy('id', 'ASC')->get()->getResultArray();
                $serverPicIds = array_map(static fn (array $row): int => (int) $row['supervisor_workforce_id'], $serverPicRows);
                if (count($serverPicIds) > count($localPicIds)
                    || array_slice($localPicIds, 0, count($serverPicIds)) !== $serverPicIds) {
                    throw new \RuntimeException('PIC history changed on the server. Review this process before syncing.', 409);
                }
                for ($index = 1; $index < count($localPicIds); $index++) {
                    $key = $this->eventKey($picHistory[$index]['client_action_key'] ?? null, $localId . ':pic:' . $index);
                    if ($this->hasReceipt($db, $key)) {
                        continue;
                    }
                    if ($index < count($serverPicIds)) {
                        $this->recordReceipt($db, $key, $serverProcessId, 'pic', $now);
                        continue;
                    }
                    if ($process['status'] !== 'active') {
                        throw new \RuntimeException('A queued PIC change cannot be applied because the server process is no longer active.', 409);
                    }
                    $supervisorId = $localPicIds[$index];
                    if (! $this->userExistsWithRole($db, $supervisorId, 'supervisor')) {
                        throw new \RuntimeException('A queued supervisor is no longer active.', 422);
                    }
                    $startedAt = $this->dateTimeValue($picHistory[$index]['started_at'] ?? null, $now);
                    $db->table('processes')->where('id', $serverProcessId)->update([
                        'current_supervisor_workforce_id' => $supervisorId,
                        'updated_at' => $now,
                    ]);
                    $db->table('process_pic_history')->where('process_id', $serverProcessId)->where('ended_at', null)->update(['ended_at' => $startedAt]);
                    $db->table('process_pic_history')->insert([
                        'process_id' => $serverProcessId,
                        'supervisor_workforce_id' => $supervisorId,
                        'started_at' => $startedAt,
                        'created_at' => $now,
                    ]);
                    $process['current_supervisor_workforce_id'] = $supervisorId;
                    $this->recordReceipt($db, $key, $serverProcessId, 'pic', $now);
                }
            }

            if ($end !== null) {
                $endKey = $this->eventKey($end['client_action_key'] ?? null, $localId . ':end');
                if (! $this->hasReceipt($db, $endKey)) {
                    $validPassed = true;
                    $validRejected = true;
                    $passed = $this->optionalQuantity($end, 'qty_passed', $validPassed);
                    $rejected = $this->optionalQuantity($end, 'qty_rejected', $validRejected);
                    $remarks = $end['remarks'] ?? null;
                    if (! $validPassed || ! $validRejected || ($remarks !== null && ! is_string($remarks))) {
                        throw new \RuntimeException('Queued process end values are invalid.', 422);
                    }
                    $remarks = is_string($remarks) ? trim($remarks) : null;
                    $remarks = $remarks === '' ? null : $remarks;
                    if ($process['status'] === 'active') {
                        $endedAt = $this->dateTimeValue($end['ended_at'] ?? null, $now);
                        $db->table('processes')->where('id', $serverProcessId)->update([
                            'status' => 'completed',
                            'ended_at' => $endedAt,
                            'qty_passed' => $passed,
                            'qty_rejected' => $rejected,
                            'remarks' => $remarks,
                            'updated_at' => $now,
                        ]);
                        $db->table('process_operators')->where('process_id', $serverProcessId)->where('removed_at', null)->update(['removed_at' => $endedAt]);
                        $db->table('process_pic_history')->where('process_id', $serverProcessId)->where('ended_at', null)->update(['ended_at' => $endedAt]);
                        $process['status'] = 'completed';
                        $process['qty_passed'] = $passed;
                        $process['qty_rejected'] = $rejected;
                        $process['remarks'] = $remarks;
                    } elseif ($process['status'] !== 'completed'
                        || (string) $process['qty_passed'] !== (string) $passed
                        || (string) $process['qty_rejected'] !== (string) $rejected
                        || $process['remarks'] !== $remarks) {
                        throw new \RuntimeException('The server process was completed with different result values.', 409);
                    }
                    $this->recordReceipt($db, $endKey, $serverProcessId, 'end', $now);
                }
            }

            foreach ($problems as $index => $problemRow) {
                if (! is_array($problemRow)) {
                    throw new \RuntimeException('A queued problem has an invalid structure.', 422);
                }
                $problemId = $this->positiveId($problemRow['problem_id'] ?? null);
                if ($problemId === null) {
                    throw new \RuntimeException('A queued problem ID is invalid.', 422);
                }
                $key = $this->eventKey($problemRow['client_action_key'] ?? null, $localId . ':problem:' . $problemId . ':' . $index);
                if ($this->hasReceipt($db, $key)) {
                    continue;
                }
                if (! $db->table('problems')->where('id', $problemId)->where('status', 'active')->countAllResults()) {
                    throw new \RuntimeException('A queued problem is no longer active.', 422);
                }
                if (! $db->table('process_problems')->where('process_id', $serverProcessId)->where('problem_id', $problemId)->countAllResults()) {
                    $reportedAt = $this->dateTimeValue($problemRow['reported_at'] ?? null, $now);
                    $db->table('process_problems')->insert([
                        'process_id' => $serverProcessId,
                        'problem_id' => $problemId,
                        'created_at' => $reportedAt,
                    ]);
                }
                $this->recordReceipt($db, $key, $serverProcessId, 'problem', $now);
            }

            if (! $db->transStatus()) {
                throw new \RuntimeException('The synchronization transaction could not be completed.');
            }
            $db->transCommit();
        } catch (Throwable $exception) {
            $db->transRollback();
            $status = $exception->getCode();
            $status = $status >= 400 && $status <= 599 ? $status : 500;
            if ($status >= 500) {
                log_message('error', 'Offline process sync failed: {message}', ['message' => $exception->getMessage()]);
            }
            $serverSnapshot = null;
            if ($status === 409 && $serverProcessId !== null) {
                $serverSnapshot = $db->table('processes')->where('id', $serverProcessId)->get()->getRowArray();
            }
            return $this->respondWithError(
                $exception->getMessage(),
                $status,
                $status === 409 ? 'SYNC_CONFLICT' : null,
                $serverSnapshot === null ? null : ['server_process' => $serverSnapshot],
            );
        }

        return $this->respond([
            'success' => true,
            'message' => 'Offline process synchronized.',
            'data' => [
                'local_process_id' => $localId,
                'process_id' => $serverProcessId,
                'status' => $process['status'],
            ],
        ]);
    }

    private function eventKey($value, string $fallback): string
    {
        if ($value === null || $value === '') {
            return $fallback;
        }
        if (! is_string($value) || strlen($value) > 190 || ! preg_match('/^[A-Za-z0-9:_-]+$/', $value)) {
            throw new \RuntimeException('A sync action key is invalid.', 422);
        }
        return $value;
    }

    private function hasReceipt($db, string $key): bool
    {
        return (bool) $db->table('process_sync_receipts')->where('client_action_key', $key)->countAllResults();
    }

    private function recordReceipt($db, string $key, int $processId, string $type, string $now): void
    {
        if (! $this->hasReceipt($db, $key)) {
            $db->table('process_sync_receipts')->insert([
                'client_action_key' => $key,
                'process_id' => $processId,
                'action_type' => $type,
                'actor_workforce_id' => $this->actorUserId,
                'created_at' => $now,
            ]);
        }
    }

    private function dateTimeValue($value, string $fallback): string
    {
        if ($value === null || $value === '') {
            return $fallback;
        }
        if (! is_string($value)) {
            throw new \RuntimeException('A queued timestamp is invalid.', 422);
        }
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            throw new \RuntimeException('A queued timestamp is invalid.', 422);
        }
        return date('Y-m-d H:i:s', $timestamp);
    }

    private function positiveId($value): ?int
    {
        if (! is_int($value) && ! is_string($value)) {
            return null;
        }
        $id = filter_var($value, FILTER_VALIDATE_INT);
        return $id !== false && $id > 0 ? $id : null;
    }

    private function optionalQuantity(array $payload, string $field, bool &$valid): ?int
    {
        if (! array_key_exists($field, $payload) || $payload[$field] === null || $payload[$field] === '') {
            return null;
        }
        $value = $payload[$field];
        if (! is_int($value) && ! is_string($value)) {
            $valid = false;
            return null;
        }
        $quantity = filter_var($value, FILTER_VALIDATE_INT);
        if ($quantity === false || $quantity < 0 || $quantity > 4294967295) {
            $valid = false;
            return null;
        }
        return $quantity;
    }

    private function activeRecordExists($db, string $table, int $id): bool
    {
        return (bool) $db->table($table)->where('id', $id)->where('status', 'active')->countAllResults();
    }

    private function userExistsWithRole($db, int $id, string $role): bool
    {
        return (bool) $db->table('workforce')->where('id', $id)->where('role', $role)->where('status', 'active')->countAllResults();
    }

    private function respondWithError(string $message, int $status, ?string $errorCode = null, ?array $data = null)
    {
        return $this->respond([
            'success' => false,
            'message' => $message,
            'error_code' => $errorCode,
            'data' => $data,
        ], $status);
    }
}
