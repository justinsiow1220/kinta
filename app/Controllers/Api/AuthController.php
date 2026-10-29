<?php

namespace App\Controllers\Api;

use App\Libraries\OfflineGrantService;
use CodeIgniter\RESTful\ResourceController;

/** Online login endpoint for supervisor PWA accounts; registration is admin-only. */
class AuthController extends ResourceController
{
    public function preflight()
    {
        return $this->response->setStatusCode(204);
    }

    public function login()
    {
        $payload = $this->request->getJSON(true);
        if (! is_array($payload)) {
            return $this->respondWithError('A JSON request body is required.', 400);
        }
        $username = mb_strtolower(trim((string) ($payload['username'] ?? '')));
        $password = $payload['password'] ?? null;
        if ($username === '' || ! is_string($password) || $password === '') {
            return $this->respondWithError('Username and password are required.', 422);
        }
        $row = db_connect()->table('workforce')
            ->where('username', $username)->where('role', 'supervisor')->where('status', 'active')
            ->get()->getRowArray();
        if ($row === null || ! is_string($row['password_hash'] ?? null) || ! password_verify($password, $row['password_hash'])) {
            return $this->respondWithError('Username or password is incorrect.', 401);
        }
        $user = [
            'id' => (int) $row['id'], 'employee_no' => $row['staff_id'],
            'name' => $row['name'], 'username' => $row['username'], 'role' => 'supervisor',
        ];
        $grant = (new OfflineGrantService(config('KintaAuth')))->issue($user);
        return $this->respond([
            'success' => true, 'message' => 'Login successful.',
            'data' => [
                'user' => $user, 'offline_grant' => $grant['token'],
                'offline_grant_issued_at' => $grant['issued_at'],
                'offline_grant_expires_at' => $grant['expires_at'],
                'offline_permissions' => $grant['permissions'],
            ],
        ]);
    }

    private function respondWithError(string $message, int $status)
    {
        return $this->respond(['success' => false, 'message' => $message], $status);
    }
}
