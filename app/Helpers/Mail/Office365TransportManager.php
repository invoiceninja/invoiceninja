<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Helpers\Mail;

use App\Helpers\Mail\Office365MailTransport;
use Illuminate\Mail\MailManager;

class Office365TransportManager extends MailManager
{
    protected function createOffice365Transport()
    {
        return new Office365MailTransport();
    }
}