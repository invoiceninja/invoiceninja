<?php

namespace App\Helpers\Mail;

use Dacastro4\LaravelGmail\Services\Message\Mail;
use Illuminate\Mail\TransportManager;

class GmailTransportManager extends TransportManager
{
    protected function createGmailDriver()
    {
    	info('ping pong');
        $token = $this->app['config']->get('services.gmail.token', []);
        $mail = new Mail;

        return new GmailTransport($mail, $token);
    }
}
