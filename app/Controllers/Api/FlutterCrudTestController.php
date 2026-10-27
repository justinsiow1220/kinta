<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;

class FlutterCrudTestController extends ResourceController
{
    private const TABLE = 'ci4_test_records';

    public function preflight()
    {
        return $this->response->setStatusCode(204);
    }

    public function index()
    {
        $records = db_connect()
            ->table(self::TABLE)
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        return $this->respond([
            'success' => true,
            'message' => 'Test records loaded.',
            'data'    => $records,
        ]);
    }

    public function create()
    {
        $title = $this->getTitle();

        if ($title === null) {
            return $this->respond([
                'success' => false,
                'message' => 'Title is required and must be 120 characters or fewer.',
            ], 422);
        }

        $db = db_connect();
        $db->table(self::TABLE)->insert([
            'title'      => $title,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $record = $db->table(self::TABLE)
            ->where('id', $db->insertID())
            ->get()
            ->getRowArray();

        return $this->respondCreated([
            'success' => true,
            'message' => 'Test record created.',
            'data'    => $record,
        ]);
    }

    public function update($id = null)
    {
        $db = db_connect();
        $record = $db->table(self::TABLE)->where('id', $id)->get()->getRowArray();

        if ($record === null) {
            return $this->respond([
                'success' => false,
                'message' => 'Test record not found.',
            ], 404);
        }

        $title = $this->getTitle();

        if ($title === null) {
            return $this->respond([
                'success' => false,
                'message' => 'Title is required and must be 120 characters or fewer.',
            ], 422);
        }

        $db->table(self::TABLE)
            ->where('id', $id)
            ->update([
                'title'      => $title,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        $record = $db->table(self::TABLE)->where('id', $id)->get()->getRowArray();

        return $this->respond([
            'success' => true,
            'message' => 'Test record updated.',
            'data'    => $record,
        ]);
    }

    public function delete($id = null)
    {
        $db = db_connect();
        $record = $db->table(self::TABLE)->where('id', $id)->get()->getRowArray();

        if ($record === null) {
            return $this->respond([
                'success' => false,
                'message' => 'Test record not found.',
            ], 404);
        }

        $db->table(self::TABLE)->where('id', $id)->delete();

        return $this->respond([
            'success' => true,
            'message' => 'Test record deleted.',
            'data'    => $record,
        ]);
    }

    private function getTitle(): ?string
    {
        $payload = $this->request->getJSON(true);
        $title   = is_array($payload) ? ($payload['title'] ?? null) : null;

        if (! is_string($title)) {
            return null;
        }

        $title = trim($title);

        if ($title === '' || mb_strlen($title) > 120) {
            return null;
        }

        return $title;
    }
}
