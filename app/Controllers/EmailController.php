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
            log_message('error', 'KINTA SMTP test email failed.');

            return $this->response->setJSON([
                'success' => false,
                'message' => 'Email failed to send.',
            ]);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Test email sent.'
        ]);
    }
}
