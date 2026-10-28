<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Adds authenticated actor references for process creation and sync receipts. */
class AddKintaProcessActors extends Migration
{
    public function up()
    {
        $this->forge->addColumn('processes', [
            'created_by_user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
        ]);
        $this->db->query('ALTER TABLE `processes` ADD INDEX `idx_processes_created_by_user_id` (`created_by_user_id`)');
        $this->db->query('ALTER TABLE `processes` ADD CONSTRAINT `fk_kinta_processes_created_by_user` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT');

        $this->forge->addColumn('process_sync_receipts', [
            'actor_user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
        ]);
        $this->db->query('ALTER TABLE `process_sync_receipts` ADD INDEX `idx_process_sync_receipts_actor_user_id` (`actor_user_id`)');
        $this->db->query('ALTER TABLE `process_sync_receipts` ADD CONSTRAINT `fk_kinta_process_sync_receipts_actor_user` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT');
    }

    public function down()
    {
        $this->db->query('ALTER TABLE `process_sync_receipts` DROP FOREIGN KEY `fk_kinta_process_sync_receipts_actor_user`');
        $this->db->query('ALTER TABLE `process_sync_receipts` DROP INDEX `idx_process_sync_receipts_actor_user_id`');
        $this->forge->dropColumn('process_sync_receipts', 'actor_user_id');

        $this->db->query('ALTER TABLE `processes` DROP FOREIGN KEY `fk_kinta_processes_created_by_user`');
        $this->db->query('ALTER TABLE `processes` DROP INDEX `idx_processes_created_by_user_id`');
        $this->forge->dropColumn('processes', 'created_by_user_id');
    }
}
