<?php

namespace App\Helpers\Mail;

use App\Helpers\Mail\GmailTransport;
use Dacastro4\LaravelGmail\Services\Message\Mail;
use Illuminate\Mail\TransportManager;

class GmailTransportManager extends TransportManager
{
    protected function createGmailDriver()
    {
        $token = $this->app['config']->get('services.gmail.token', []);
        $mail = new Mail;
        
        return new GmailTransport($mail, string $token);
    }

}