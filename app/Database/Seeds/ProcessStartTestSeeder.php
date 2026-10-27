<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use Throwable;

/**
 * Adds clearly marked test master records for the process-start integration page.
 */
class ProcessStartTestSeeder extends Seeder
{
    public function run()
    {
        $now = date('Y-m-d H:i:s');
        $this->db->transBegin();

        try {
            $machineId = $this->findOrCreate(
                'machines',
                ['machine_code' => 'TEST-MACHINE-001'],
                [
                    'machine_code' => 'TEST-MACHINE-001',
                    'machine_name' => 'TEST - Integration Machine',
                    'status'       => 'active',
                    'created_at'   => $now,
                ],
            );

            $sectorId = $this->findOrCreate(
                'sectors',
                ['name' => 'TEST - Integration Sector'],
                [
                    'name'       => 'TEST - Integration Sector',
                    'status'     => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );

            $this->findOrCreate(
                'activities',
                [
                    'sector_id' => $sectorId,
                    'name'      => 'TEST - Integration Activity',
                ],
                [
                    'sector_id' => $sectorId,
                    'name'      => 'TEST - Integration Activity',
                    'status'    => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );

            $this->findOrCreate(
                'problems',
                ['code' => 'TEST-PROB-001'],
                [
                    'code'       => 'TEST-PROB-001',
                    'name'       => 'TEST - Integration Problem',
                    'status'     => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );

            $supervisorId = $this->createTestUser(
                'TEST-SUP-001',
                'test.supervisor',
                'TEST - Supervisor',
                'supervisor',
                $now,
            );

            $this->createTestUser(
                'TEST-SUP-002',
                'test.supervisor.2',
                'TEST - Supervisor 2',
                'supervisor',
                $now,
            );

            $this->createTestUser(
                'TEST-OP-001',
                'test.operator.1',
                'TEST - Operator 1',
                'operator',
                $now,
            );

            $this->createTestUser(
                'TEST-OP-002',
                'test.operator.2',
                'TEST - Operator 2',
                'operator',
                $now,
            );

            $this->createTestUser(
                'TEST-QC-001',
                'test.qc',
                'TEST - QC',
                'qc',
                $now,
            );

            if (! $this->db->transStatus()) {
                throw new \RuntimeException('The process-start test records could not be seeded.');
            }

            $this->db->transCommit();
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }

    private function createTestUser(
        string $employeeNo,
        string $username,
        string $name,
        string $role,
        string $now,
    ): int {
        return $this->findOrCreate(
            'users',
            ['employee_no' => $employeeNo],
            [
                'employee_no'  => $employeeNo,
                'name'         => $name,
                'username'     => $username,
                'password_hash' => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
                'role'         => $role,
                'status'       => 'active',
                'created_at'   => $now,
                'updated_at'   => $now,
            ],
        );
    }

    private function findOrCreate(string $table, array $criteria, array $values): int
    {
        $existing = $this->db->table($table)
            ->where($criteria)
            ->get()
            ->getRowArray();

        if ($existing !== null) {
            return (int) $existing['id'];
        }

        $this->db->table($table)->insert($values);

        return (int) $this->db->insertID();
    }
}
