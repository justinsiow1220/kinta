<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;

class MasterDataController extends ResourceController
{
    public function users()
    {
        $builder = db_connect()
            ->table('users')
            ->select('id, employee_no, name, role, status')
            ->where('status', 'active');

        $role = $this->request->getGet('role');
        if ($role !== null && $role !== '') {
            $role = trim($role);
            if (mb_strlen($role) > 50) {
                return $this->respond([
                    'success' => false,
                    'message' => 'The role filter must be 50 characters or fewer.',
                ], 400);
            }

            $builder->where('role', $role);
        }

        return $this->respondWithData(
            'Active users loaded.',
            $builder->orderBy('name', 'ASC')->get()->getResultArray(),
        );
    }

    public function machines()
    {
        $records = db_connect()
            ->table('machines')
            ->select('id, machine_code, machine_name, status')
            ->where('status', 'active')
            ->orderBy('machine_name', 'ASC')
            ->get()
            ->getResultArray();

        return $this->respondWithData('Active machines loaded.', $records);
    }

    public function sectors()
    {
        $records = db_connect()
            ->table('sectors')
            ->select('id, name, status')
            ->where('status', 'active')
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();

        return $this->respondWithData('Active sectors loaded.', $records);
    }

    public function activities()
    {
        $builder = db_connect()
            ->table('activities')
            ->select('id, sector_id, name, status')
            ->where('status', 'active');

        $sectorId = $this->request->getGet('sector_id');
        if ($sectorId !== null && $sectorId !== '') {
            if (filter_var($sectorId, FILTER_VALIDATE_INT) === false || (int) $sectorId < 1) {
                return $this->respond([
                    'success' => false,
                    'message' => 'sector_id must be a positive integer.',
                ], 400);
            }

            $builder->where('sector_id', (int) $sectorId);
        }

        $records = $builder->orderBy('name', 'ASC')->get()->getResultArray();

        return $this->respondWithData('Active activities loaded.', $records);
    }

    public function salesOrders()
    {
        $records = db_connect()
            ->table('sales_orders')
            ->select('id, so_number, customer_name, batch_qty, status')
            ->orderBy('so_number', 'DESC')
            ->get()
            ->getResultArray();

        return $this->respondWithData('Sales orders loaded.', $records);
    }

    public function problems()
    {
        $records = db_connect()
            ->table('problems')
            ->select('id, code, name, status')
            ->where('status', 'active')
            ->orderBy('code', 'ASC')
            ->get()
            ->getResultArray();

        return $this->respondWithData('Active problems loaded.', $records);
    }

    private function respondWithData(string $message, array $records)
    {
        return $this->respond([
            'success' => true,
            'message' => $message,
            'data'    => $records,
        ]);
    }
}
