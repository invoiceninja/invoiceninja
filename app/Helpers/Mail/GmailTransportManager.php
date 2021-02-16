<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */
namespace App\Helpers\Mail;

use Illuminate\Mail\MailManager;
use App\CustomMailDriver\CustomTransport;
use Dacastro4\LaravelGmail\Services\Message\Mail;
use Illuminate\Support\Facades\Config;


class GmailTransportManager extends MailManager
{
    protected function createGmailTransport()
    {
        return new GmailTransport(new Mail);
    }
}