<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Renames personnel storage and its references without changing row IDs. */
class RenameUsersToWorkforce extends Migration
{
    public function up()
    {
        $this->db->query('RENAME TABLE `users` TO `workforce`');
        $renames = [
            'workforce' => [['employee_no', 'staff_id']],
            'processes' => [['current_supervisor_id', 'current_supervisor_workforce_id'], ['created_by_user_id', 'created_by_workforce_id'], ['deleted_by_user_id', 'deleted_by_workforce_id']],
            'process_operators' => [['user_id', 'workforce_id']],
            'qc_inspections' => [['qc_user_id', 'qc_workforce_id']],
            'process_pic_history' => [['supervisor_id', 'supervisor_workforce_id']],
            'process_sync_receipts' => [['actor_user_id', 'actor_workforce_id']],
        ];
        foreach ($renames as $table => $columns) {
            foreach ($columns as [$from, $to]) {
                $this->db->query("ALTER TABLE `{$table}` RENAME COLUMN `{$from}` TO `{$to}`");
            }
        }
        $this->db->query('ALTER TABLE `workforce` MODIFY `username` VARCHAR(100) NULL, MODIFY `password_hash` VARCHAR(255) NULL');
    }

    public function down()
    {
        $this->db->query('ALTER TABLE `workforce` MODIFY `username` VARCHAR(100) NOT NULL, MODIFY `password_hash` VARCHAR(255) NOT NULL');
        $renames = [
            'workforce' => [['staff_id', 'employee_no']],
            'processes' => [['current_supervisor_workforce_id', 'current_supervisor_id'], ['created_by_workforce_id', 'created_by_user_id'], ['deleted_by_workforce_id', 'deleted_by_user_id']],
            'process_operators' => [['workforce_id', 'user_id']],
            'qc_inspections' => [['qc_workforce_id', 'qc_user_id']],
            'process_pic_history' => [['supervisor_workforce_id', 'supervisor_id']],
            'process_sync_receipts' => [['actor_workforce_id', 'actor_user_id']],
        ];
        foreach ($renames as $table => $columns) {
            foreach ($columns as [$from, $to]) {
                $this->db->query("ALTER TABLE `{$table}` RENAME COLUMN `{$from}` TO `{$to}`");
            }
        }
        $this->db->query('RENAME TABLE `workforce` TO `users`');
    }
}
