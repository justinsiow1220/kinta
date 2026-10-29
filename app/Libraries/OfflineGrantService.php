<?php

namespace App\Libraries;

use CodeIgniter\HTTP\RequestInterface;
use Config\KintaAuth;
use RuntimeException;

/** Signs and validates RFC 7515 compact JWS offline access grants (RS256). */
class OfflineGrantService
{
    public function __construct(private readonly KintaAuth $config)
    {
    }

    /**
     * @param array<string, mixed> $user A public user row; never pass a password hash.
     * @return array{token: string, issued_at: int, expires_at: int, permissions: list<string>}
     */
    public function issue(array $user): array
    {
        $userId = (int) ($user['id'] ?? 0);
        $role = (string) ($user['role'] ?? '');
        $permissions = self::permissionsForRole($role);
        if ($userId < 1 || $permissions === []) {
            throw new RuntimeException('This account role is not enabled for offline access.');
        }

        $now = time();
        $expiresAt = $now + max(300, $this->config->grantLifetimeSeconds);
        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT',
            'kid' => $this->config->grantKeyId,
        ];
        $claims = [
            'iss'            => $this->config->grantIssuer,
            'aud'            => $this->config->grantAudience,
            'sub'            => (string) $userId,
            'user_id'        => $userId,
            'role'           => $role,
            'permissions'    => $permissions,
            'offline_allowed'=> true,
            'iat'            => $now,
            'nbf'            => $now,
            'exp'            => $expiresAt,
            'jti'            => bin2hex(random_bytes(16)),
        ];

        $signingInput = self::base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES))
            . '.'
            . self::base64UrlEncode(json_encode($claims, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $privateKey = $this->privateKey();
        if (! openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('CI4 could not sign the offline access grant.');
        }

        return [
            'token'       => $signingInput . '.' . self::base64UrlEncode($signature),
            'issued_at'   => $now,
            'expires_at'  => $expiresAt,
            'permissions' => $permissions,
        ];
    }

    /**
     * Validates the compact JWS and the current database account for this request.
     * Returns the current user and signed claims, or null when access is invalid.
     *
     * @return array<string, mixed>|null
     */
    public function authenticateRequest(RequestInterface $request): ?array
    {
        $authorization = trim($request->getHeaderLine('Authorization'));
        if (! preg_match('/^Bearer\s+([^\s]+)$/i', $authorization, $matches)) {
            return null;
        }

        $claims = $this->verify($matches[1]);
        if ($claims === null) {
            return null;
        }

        $userId = (int) ($claims['user_id'] ?? 0);
        $user = db_connect()->table('workforce')
            ->select('id, staff_id AS employee_no, name, username, role, status')
            ->where('id', $userId)
            ->where('status', 'active')
            ->get()
            ->getRowArray();

        if ($user === null || $user['role'] !== ($claims['role'] ?? null)) {
            return null;
        }

        $currentPermissions = self::permissionsForRole((string) $user['role']);
        $claimPermissions = $claims['permissions'] ?? null;
        if (! is_array($claimPermissions)) {
            return null;
        }
        if (array_filter($claimPermissions, static fn ($permission): bool =>
            ! is_string($permission) || ! in_array($permission, $currentPermissions, true)
        ) !== []) {
            return null;
        }

        // A still-valid grant may predate an additive permission. Preserve its
        // original capabilities; a fresh online login is required for new ones.
        $effectivePermissions = array_values(array_intersect($currentPermissions, $claimPermissions));

        return [
            'user'        => $user,
            'claims'      => $claims,
            'permissions' => $effectivePermissions,
        ];
    }

    /** @return array<string, mixed>|null */
    public function verify(string $token): ?array
    {
        if (strlen($token) > 8192) {
            return null;
        }
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$encodedHeader, $encodedClaims, $encodedSignature] = $parts;
        $headerJson = self::base64UrlDecode($encodedHeader);
        $claimsJson = self::base64UrlDecode($encodedClaims);
        $signature = self::base64UrlDecode($encodedSignature);
        if ($headerJson === null || $claimsJson === null || $signature === null) {
            return null;
        }

        $header = json_decode($headerJson, true);
        $claims = json_decode($claimsJson, true);
        if (! is_array($header) || ! is_array($claims)
            || ($header['alg'] ?? null) !== 'RS256'
            || ($header['typ'] ?? null) !== 'JWT'
            || ($header['kid'] ?? null) !== $this->config->grantKeyId) {
            return null;
        }

        $publicKey = openssl_pkey_get_details($this->privateKey())['key'] ?? null;
        if (! is_string($publicKey)
            || openssl_verify($encodedHeader . '.' . $encodedClaims, $signature, $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
            return null;
        }

        $now = time();
        $userId = $claims['user_id'] ?? null;
        if (($claims['iss'] ?? null) !== $this->config->grantIssuer
            || ($claims['aud'] ?? null) !== $this->config->grantAudience
            || ! is_int($userId) || $userId < 1
            || ($claims['sub'] ?? null) !== (string) $userId
            || ($claims['offline_allowed'] ?? null) !== true
            || ! is_int($claims['iat'] ?? null)
            || ! is_int($claims['nbf'] ?? null)
            || ! is_int($claims['exp'] ?? null)
            || $claims['iat'] > $now + 60
            || $claims['nbf'] > $now + 60
            || $claims['exp'] <= $now
            || ! is_string($claims['jti'] ?? null)
            || ! is_string($claims['role'] ?? null)) {
            return null;
        }

        return $claims;
    }

    /** @return list<string> */
    public static function permissionsForRole(string $role): array
    {
        $common = ['master.read', 'process.read', 'process.sync'];
        return $role === 'supervisor'
            ? [...$common, 'process.write', 'process.delete', 'qc.write', 'pic.change', 'operator.assign', 'problem.report']
            : [];
    }

    private function privateKey()
    {
        if (! is_file($this->config->offlinePrivateKeyPath)) {
            throw new RuntimeException('Offline grant signing key is missing. Run the KINTA key setup script.');
        }

        $pem = file_get_contents($this->config->offlinePrivateKeyPath);
        $key = is_string($pem) ? openssl_pkey_get_private($pem) : false;
        if ($key === false) {
            throw new RuntimeException('Offline grant signing key could not be opened.');
        }
        return $key;
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): ?string
    {
        if ($value === '' || preg_match('/[^A-Za-z0-9_-]/', $value)) {
            return null;
        }
        $padded = strtr($value, '-_', '+/') . str_repeat('=', (4 - strlen($value) % 4) % 4);
        $decoded = base64_decode($padded, true);
        return $decoded === false ? null : $decoded;
    }
}
