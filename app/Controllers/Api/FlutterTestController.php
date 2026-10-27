<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;

class FlutterTestController extends ResourceController
{
    public function index()
    {
        return $this->respond([
            'success' => true,
            'message' => 'Flutter connected to the new CI4 API.',
        ]);
    }
}
