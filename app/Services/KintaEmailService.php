<?php

namespace App\Services;

class KintaEmailService
{
    public function send(
        string $to,
        string $subject,
        string $message
    ): bool {
        $email = service('email');

        $email->setTo($to);
        $email->setSubject($subject);
        $email->setMessage($message);

        return $email->send();
    }
}