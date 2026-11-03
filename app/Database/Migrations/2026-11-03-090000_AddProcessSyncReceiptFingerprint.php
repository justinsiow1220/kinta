<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Adds a deterministic action fingerprint to sync receipts. */
class AddProcessSyncReceiptFingerprint extends Migration
{
    public function up()
    {
        $this->forge->addColumn('process_sync_receipts', [
            'request_fingerprint' => [
                'type'       => 'VARCHAR',
                'constraint' => 80,
                'null'       => true,
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('process_sync_receipts', 'request_fingerprint');
    }
}
