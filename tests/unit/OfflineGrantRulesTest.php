<?php

use App\Libraries\OfflineGrantService;
use CodeIgniter\Test\CIUnitTestCase;
use Config\KintaAuth;

/** @internal */
final class OfflineGrantRulesTest extends CIUnitTestCase
{
    private string $privateKeyPath;
    private Config\KintaAuth $config;
    private OfflineGrantService $service;

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
        self::assertNotFalse($key);
        self::assertTrue(openssl_pkey_export($key, $privatePem, null, ['config' => $opensslConfig]));
        unlink($opensslConfig);

        $this->privateKeyPath = tempnam(sys_get_temp_dir(), 'kinta-test-key-');
        self::assertNotFalse($this->privateKeyPath);
        self::assertNotFalse(file_put_contents($this->privateKeyPath, $privatePem));
        $this->config = new Config\KintaAuth();
        $this->config->offlinePrivateKeyPath = $this->privateKeyPath;
        $this->service = new OfflineGrantService($this->config);
    }

    protected function tearDown(): void
    {
        if (isset($this->privateKeyPath) && is_file($this->privateKeyPath)) {
            unlink($this->privateKeyPath);
        }
        parent::tearDown();
    }

    public function testOnlySupervisorRoleHasOfflinePermissions(): void
    {
        self::assertContains('process.sync', OfflineGrantService::permissionsForRole('supervisor'));
        self::assertContains('process.write', OfflineGrantService::permissionsForRole('supervisor'));
        self::assertSame([], OfflineGrantService::permissionsForRole('admin'));
        self::assertSame([], OfflineGrantService::permissionsForRole('operator'));
    }

    public function testMalformedGrantIsRejectedBeforeKeyOrDatabaseAccess(): void
    {
        $service = new OfflineGrantService(new KintaAuth());

        self::assertNull($service->verify('not-a-grant'));
        self::assertNull($service->verify('a.b.c'));
    }

    public function testAccountWithoutOfflineRoleCannotReceiveGrant(): void
    {
        $service = new OfflineGrantService(new KintaAuth());

        $this->expectException(RuntimeException::class);
        $service->issue(['id' => 7, 'role' => 'admin']);
    }

    public function testIssuedGrantHasValidSignatureAndExpectedClaims(): void
    {
        $grant = $this->service->issue(['id' => 42, 'role' => 'supervisor']);
        $claims = $this->service->verify($grant['token']);

        self::assertSame(42, $claims['user_id']);
        self::assertSame('supervisor', $claims['role']);
        self::assertSame($grant['expires_at'], $claims['exp']);
        self::assertContains('process.sync', $claims['permissions']);
    }

    public function testExpiredAndTamperedGrantsAreRejected(): void
    {
        $claims = [
            'iss' => $this->config->grantIssuer,
            'aud' => $this->config->grantAudience,
            'sub' => '42',
            'user_id' => 42,
            'role' => 'supervisor',
            'permissions' => ['process.read'],
            'offline_allowed' => true,
            'iat' => time() - 120,
            'nbf' => time() - 120,
            'exp' => time() - 1,
            'jti' => bin2hex(random_bytes(16)),
        ];
        $expired = $this->signedToken($claims);
        $valid = $this->service->issue(['id' => 42, 'role' => 'supervisor'])['token'];
        $parts = explode('.', $valid);
        $parts[1] = $this->base64UrlEncode('{"user_id":99}');

        self::assertNull($this->service->verify($expired));
        self::assertNull($this->service->verify(implode('.', $parts)));
    }

    /** @param array<string, mixed> $claims */
    private function signedToken(array $claims): string
    {
        $header = ['alg' => 'RS256', 'typ' => 'JWT', 'kid' => $this->config->grantKeyId];
        $input = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR)) . '.'
            . $this->base64UrlEncode(json_encode($claims, JSON_THROW_ON_ERROR));
        $key = openssl_pkey_get_private((string) file_get_contents($this->privateKeyPath));
        self::assertNotFalse($key);
        self::assertTrue(openssl_sign($input, $signature, $key, OPENSSL_ALGO_SHA256));

        return $input . '.' . $this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
