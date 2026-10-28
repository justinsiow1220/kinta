<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Stores idempotency receipts for offline process synchronization.
 */
class CreateProcessSyncReceipts extends Migration
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
            'client_action_key' => [
                'type'       => 'VARCHAR',
                'constraint' => 190,
            ],
            'process_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'action_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('client_action_key');
        $this->forge->addKey('process_id');
        $this->forge->addForeignKey('process_id', 'processes', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('process_sync_receipts', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        $this->forge->dropTable('process_sync_receipts', true);
    }
}
