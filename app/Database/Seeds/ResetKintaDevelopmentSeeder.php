<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use RuntimeException;

/** Clears approved local test data and provisions the first local admin. */
class ResetKintaDevelopmentSeeder extends Seeder
{
    public function run()
    {
        $password = env('KINTA_INITIAL_ADMIN_PASSWORD');
        if (! is_string($password) || $password === '') {
            throw new RuntimeException('Set KINTA_INITIAL_ADMIN_PASSWORD in the local .env before running this reset seeder.');
        }

        $db = $this->db;
        $db->transBegin();
        try {
            foreach (['process_sync_receipts', 'process_pic_history', 'process_problems', 'qc_inspections', 'process_operators', 'processes'] as $table) {
                $db->table($table)->emptyTable();
            }
            $db->table('workforce')->emptyTable();
            foreach (['activities', 'sectors', 'machines', 'sales_orders', 'problems'] as $table) {
                $db->table($table)->emptyTable();
            }
            foreach (['process_sync_receipts', 'process_pic_history', 'process_problems', 'qc_inspections', 'process_operators', 'processes', 'workforce', 'activities', 'sectors', 'machines', 'sales_orders', 'problems'] as $table) {
                $db->query("ALTER TABLE `{$table}` AUTO_INCREMENT = 1");
            }

            $now = date('Y-m-d H:i:s');
            $db->table('workforce')->insert([
                'staff_id' => 'SID000001',
                'name' => 'KINTA Administrator',
                'username' => 'kintaadmin@gmail.com',
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => 'admin',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            if (! $db->transStatus()) throw new RuntimeException('The local reset transaction failed.');
            $db->transCommit();
            echo "Cleared authorized local test/master data and provisioned the first administrator (SID000001)." . PHP_EOL;
        } catch (\Throwable $error) {
            $db->transRollback();
            throw $error;
        }
    }
}
