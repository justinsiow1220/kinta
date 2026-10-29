<?php

namespace App\Controllers;

use App\Services\KintaEmailService;
use CodeIgniter\HTTP\ResponseInterface;

class EmailController extends BaseController
{
    public function send(): ResponseInterface
    {
        $mailer = new KintaEmailService();

        $success = $mailer->send(
            'noreply@kinta.com',
            'KINTA SMTP Test',
            'If you received this email, KINTA SMTP is working.'
        );

        if (!$success) {
            $email = service('email');

            return $this->response->setJSON([
                'success' => false,
                'message' => 'Email failed to send.',
                'debug' => $email->printDebugger(),
            ]);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Test email sent.'
        ]);
    }
}