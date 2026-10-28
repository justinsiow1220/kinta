<?php

namespace App\Filters;

use App\Libraries\OfflineGrantService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/** Requires a valid, unexpired grant and current active account for API access. */
class OfflineGrantFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $authenticated = (new OfflineGrantService(config('KintaAuth')))->authenticateRequest($request);
        if ($authenticated === null) {
            return service('response')->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'A valid, active KINTA sign-in is required.',
                'error_code' => 'AUTH_REQUIRED',
            ]);
        }

        $permissions = $authenticated['permissions'];
        $requiredPermission = $arguments[0] ?? null;
        if (is_string($requiredPermission) && ! in_array($requiredPermission, $permissions, true)) {
            return service('response')->setStatusCode(403)->setJSON([
                'success' => false,
                'message' => 'Your current role is not allowed to perform this action.',
                'error_code' => 'PERMISSION_DENIED',
            ]);
        }

        // Controllers may trust this header only on routes protected by this filter.
        $request->setHeader('X-Kinta-Auth-User-Id', (string) $authenticated['user']['id']);
        $request->setHeader('X-Kinta-Auth-Role', (string) $authenticated['user']['role']);
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
