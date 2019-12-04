<?php

namespace App\Helpers\Mail;

use App\Helpers\Mail\GmailTransport;
use Illuminate\Mail\TransportManager;

class GmailTransportManager extends TransportManager
{
    protected function createGmailDriver()
    {
        return new GmailTransport();
    }

}