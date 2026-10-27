<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Isolated records for the Flutter-to-CI4 integration checkpoints.
 */
class CreateCi4TestRecords extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'title' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('ci4_test_records', true);
    }

    public function down()
    {
        $this->forge->dropTable('ci4_test_records', true);
    }
}
