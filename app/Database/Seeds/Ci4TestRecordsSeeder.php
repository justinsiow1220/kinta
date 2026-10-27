<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class Ci4TestRecordsSeeder extends Seeder
{
    public function run()
    {
        $records = $this->db->table('ci4_test_records');

        if ($records->countAllResults() > 0) {
            return;
        }

        $records->insert([
            'title'      => 'Checkpoint 4 sample record',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
