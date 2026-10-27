<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Creates KINTA's process and process-history tables.
 */
class CreateKintaProcessTables extends Migration
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
            'machine_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'sector_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'activity_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'sales_order_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'current_supervisor_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'description' => ['type' => 'TEXT', 'null' => true],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'pending',
            ],
            'started_at' => ['type' => 'DATETIME', 'null' => true],
            'ended_at' => ['type' => 'DATETIME', 'null' => true],
            'qty_passed' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'qty_rejected' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'remarks' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('machine_id');
        $this->forge->addKey('sector_id');
        $this->forge->addKey('activity_id');
        $this->forge->addKey('sales_order_id');
        $this->forge->addKey('current_supervisor_id');
        $this->forge->addKey('status');
        $this->forge->addKey('started_at');
        $this->forge->addForeignKey('machine_id', 'machines', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('sector_id', 'sectors', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('activity_id', 'activities', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('sales_order_id', 'sales_orders', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('current_supervisor_id', 'users', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('processes', true, ['ENGINE' => 'InnoDB']);

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'process_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'user_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'assigned_at' => ['type' => 'DATETIME'],
            'removed_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('process_id');
        $this->forge->addKey('user_id');
        $this->forge->addForeignKey('process_id', 'processes', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('process_operators', true, ['ENGINE' => 'InnoDB']);

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'process_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'qc_user_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'inspection_time' => ['type' => 'DATETIME'],
            'quantity' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('process_id');
        $this->forge->addKey('qc_user_id');
        $this->forge->addKey('inspection_time');
        $this->forge->addForeignKey('process_id', 'processes', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('qc_user_id', 'users', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('qc_inspections', true, ['ENGINE' => 'InnoDB']);

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'process_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'problem_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('process_id');
        $this->forge->addKey('problem_id');
        $this->forge->addUniqueKey(['process_id', 'problem_id']);
        $this->forge->addForeignKey('process_id', 'processes', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('problem_id', 'problems', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('process_problems', true, ['ENGINE' => 'InnoDB']);

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'process_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'supervisor_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'started_at' => ['type' => 'DATETIME'],
            'ended_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('process_id');
        $this->forge->addKey('supervisor_id');
        $this->forge->addKey('started_at');
        $this->forge->addForeignKey('process_id', 'processes', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('supervisor_id', 'users', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('process_pic_history', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        $this->forge->dropTable('process_pic_history', true);
        $this->forge->dropTable('process_problems', true);
        $this->forge->dropTable('qc_inspections', true);
        $this->forge->dropTable('process_operators', true);
        $this->forge->dropTable('processes', true);
    }
}
