<?php

namespace App\Helpers\Mail;

use Illuminate\Mail\MailManager;
use App\CustomMailDriver\CustomTransport;
use Dacastro4\LaravelGmail\Services\Message\Mail;


class GmailTransportManager extends MailManager
{
    protected function createGmailTransport()
    {

        $token = $this->app['config']->get('services.gmail.token', []);
        $mail = new Mail;

        return new GmailTransport($mail, $token);
    }
}