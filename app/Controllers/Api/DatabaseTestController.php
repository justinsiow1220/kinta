<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use Throwable;

class DatabaseTestController extends ResourceController
{
    public function index()
    {
        try {
            $row = db_connect()->query('SELECT 1 AS connected')->getRowArray();

            return $this->respond([
                'success' => (int) ($row['connected'] ?? 0) === 1,
                'message' => 'CI4 connected to MySQL successfully.',
            ]);
        } catch (Throwable $exception) {
            log_message('error', 'MySQL connection test failed: {message}', [
                'message' => $exception->getMessage(),
            ]);

            return $this->respond([
                'success' => false,
                'message' => 'CI4 could not connect to MySQL.',
            ], 503);
        }
    }

    public function records()
    {
        try {
            $records = db_connect()
                ->table('ci4_test_records')
                ->orderBy('id', 'ASC')
                ->get()
                ->getResultArray();

            return $this->respond([
                'success' => true,
                'message' => 'Test records loaded from MySQL.',
                'data'    => $records,
            ]);
        } catch (Throwable $exception) {
            log_message('error', 'MySQL test records read failed: {message}', [
                'message' => $exception->getMessage(),
            ]);

            return $this->respond([
                'success' => false,
                'message' => 'CI4 could not read the MySQL test records.',
            ], 503);
        }
    }
}
