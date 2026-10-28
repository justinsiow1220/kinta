<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Adds auditable soft deletion for completed process records. */
class AddProcessSoftDeletes extends Migration
{
    public function up()
    {
        $this->forge->addColumn('processes', [
            'deleted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'deleted_by_user_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
        ]);

        $this->db->query('ALTER TABLE `processes` ADD INDEX `idx_processes_deleted_at` (`deleted_at`)');
        $this->db->query('ALTER TABLE `processes` ADD CONSTRAINT `fk_kinta_processes_deleted_by_user` FOREIGN KEY (`deleted_by_user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT');
    }

    public function down()
    {
        $this->db->query('ALTER TABLE `processes` DROP FOREIGN KEY `fk_kinta_processes_deleted_by_user`');
        $this->db->query('ALTER TABLE `processes` DROP INDEX `idx_processes_deleted_at`');
        $this->forge->dropColumn('processes', ['deleted_at', 'deleted_by_user_id']);
    }
}
