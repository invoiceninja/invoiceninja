<?php

namespace App\Helpers\Mail;

use Illuminate\Mail\MailManager;
use App\CustomMailDriver\CustomTransport;
use Dacastro4\LaravelGmail\Services\Message\Mail;
use Illuminate\Support\Facades\Config;


class GmailTransportManager extends MailManager
{
    protected function createGmailTransport()
    {
    	info("booting gmail transport");
        // $token = $this->app['config']->get('services.gmail.token', []);
        //$token = config('services.gmail.token');

        $mail = new Mail;

        return new GmailTransport($mail, $token);
    }
}