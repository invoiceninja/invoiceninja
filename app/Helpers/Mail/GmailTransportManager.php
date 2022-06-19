<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */
namespace App\Helpers\Mail;

use App\CustomMailDriver\CustomTransport;
use App\Helpers\Mail\Office365MailTransport;
use Dacastro4\LaravelGmail\Services\Message\Mail;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Config;


class GmailTransportManager extends MailManager
{
    protected function createGmailTransport()
    {
        return new GmailTransport(new Mail);
    }

    protected function createOffice365Transport()
    {
        return new Office365MailTransport();
    }
}