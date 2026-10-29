<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AdminAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! session('admin_workforce_id')) return redirect()->to('/admin/login');
        $admin = db_connect()->table('workforce')->where('id', (int) session('admin_workforce_id'))->where('role', 'admin')->where('status', 'active')->countAllResults();
        if (! $admin) { session()->destroy(); return redirect()->to('/admin/login'); }
        return null;
    }
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}
}
