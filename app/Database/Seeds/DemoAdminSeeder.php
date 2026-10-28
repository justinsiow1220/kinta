<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use RuntimeException;

/**
 * Creates or refreshes a local-only KINTA administrator test account.
 *
 * Set KINTA_DEMO_ADMIN_PASSWORD in the shell before running this seeder.
 * Do not run this seeder in a production environment.
 */
class DemoAdminSeeder extends Seeder
{
    private const EMPLOYEE_NO = 'KINTA-ADMIN-DEMO';
    private const NAME = 'KINTA Demo Admin';
    private const USERNAME = 'admin.demo@kinta.local';

    public function run()
    {
        $password = env('KINTA_DEMO_ADMIN_PASSWORD'); //KintaDemo!2026
        if (! is_string($password) || strlen($password) < 12) {
            throw new RuntimeException(
                'Set KINTA_DEMO_ADMIN_PASSWORD to a password of at least 12 characters before running this local test seeder.',
            );
        }

        $db = $this->db;
        $matches = $db->table('users')
            ->groupStart()
                ->where('employee_no', self::EMPLOYEE_NO)
                ->orWhere('username', self::USERNAME)
            ->groupEnd()
            ->get()
            ->getResultArray();

        if (count($matches) > 1 || ($matches !== [] && (
            $matches[0]['employee_no'] !== self::EMPLOYEE_NO
            || $matches[0]['username'] !== self::USERNAME
        ))) {
            throw new RuntimeException(
                'The demo admin employee number or username is already used by another account; no user row was changed.',
            );
        }

        $now = date('Y-m-d H:i:s');
        $values = [
            'employee_no'  => self::EMPLOYEE_NO,
            'name'         => self::NAME,
            'username'     => self::USERNAME,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role'         => 'admin',
            'status'       => 'active',
            'updated_at'   => $now,
        ];

        if ($matches === []) {
            $values['created_at'] = $now;
            $db->table('users')->insert($values);
            echo "Created local demo administrator: " . self::USERNAME . PHP_EOL;
            return;
        }

        $db->table('users')->where('id', $matches[0]['id'])->update($values);
        echo "Refreshed local demo administrator: " . self::USERNAME . PHP_EOL;
    }
}
