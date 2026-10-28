<?php

namespace App\Controllers\Api;

use App\Libraries\OfflineGrantService;
use CodeIgniter\RESTful\ResourceController;

/**
 * Provides the first online registration and login endpoints for the Flutter app.
 */
class AuthController extends ResourceController
{
    public function preflight()
    {
        return $this->response->setStatusCode(204);
    }

    public function register()
    {
        $payload = $this->request->getJSON(true);
        if (! is_array($payload)) {
            return $this->respondWithError('A JSON request body is required.', 400);
        }

        $employeeNo = trim((string) ($payload['employee_no'] ?? ''));
        $name = trim((string) ($payload['name'] ?? ''));
        $username = mb_strtolower(trim((string) ($payload['username'] ?? '')));
        $password = $payload['password'] ?? null;

        if ($employeeNo === '' || mb_strlen($employeeNo) > 50) {
            return $this->respondWithError('Employee number is required and must be 50 characters or fewer.', 422);
        }
        if ($name === '' || mb_strlen($name) > 150) {
            return $this->respondWithError('Name is required and must be 150 characters or fewer.', 422);
        }
        if (mb_strlen($username) > 100 || filter_var($username, FILTER_VALIDATE_EMAIL) === false) {
            return $this->respondWithError('Enter a valid email address to use as the username.', 422);
        }
        if (! is_string($password) || strlen($password) < 8 || strlen($password) > 200) {
            return $this->respondWithError('Password must be between 8 and 200 characters.', 422);
        }

        $db = db_connect();
        if ($db->table('users')->groupStart()
            ->where('employee_no', $employeeNo)
            ->orWhere('username', $username)
            ->groupEnd()->countAllResults() > 0) {
            return $this->respondWithError('That employee number or username is already registered.', 409);
        }

        $now = date('Y-m-d H:i:s');
        $inserted = $db->table('users')->insert([
            'employee_no'  => $employeeNo,
            'name'         => $name,
            'username'     => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            // Public registration cannot assign elevated roles.
            'role'         => 'operator',
            'status'       => 'active',
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);

        if (! $inserted) {
            return $this->respondWithError('CI4 could not create the account.', 500);
        }

        $user = $db->table('users')
            ->select('id, employee_no, name, username, role')
            ->where('id', $db->insertID())
            ->get()
            ->getRowArray();

        return $this->respondCreated([
            'success' => true,
            'message' => 'Account registered.',
            'data'    => $this->authResponseData($user),
        ]);
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

        $user = db_connect()->table('users')
            ->where('username', $username)
            ->where('status', 'active')
            ->get()
            ->getRowArray();

        if ($user === null || ! password_verify($password, $user['password_hash'])) {
            return $this->respondWithError('Username or password is incorrect.', 401);
        }

        $publicUser = [
            'id'          => (int) $user['id'],
            'employee_no' => $user['employee_no'],
            'name'        => $user['name'],
            'username'    => $user['username'],
            'role'        => $user['role'],
        ];

        return $this->respond([
            'success' => true,
            'message' => 'Login successful.',
            'data'    => $this->authResponseData($publicUser),
        ]);
    }

    /** @param array<string, mixed> $user */
    private function authResponseData(array $user): array
    {
        $grant = (new OfflineGrantService(config('KintaAuth')))->issue($user);
        return [
            'user'                   => $user,
            'offline_grant'          => $grant['token'],
            'offline_grant_issued_at'=> $grant['issued_at'],
            'offline_grant_expires_at'=> $grant['expires_at'],
            'offline_permissions'    => $grant['permissions'],
        ];
    }

    private function respondWithError(string $message, int $status)
    {
        return $this->respond(['success' => false, 'message' => $message], $status);
    }
}
