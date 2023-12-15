<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Helpers\Mail\Webhook;

use App\Models\Company;

interface BaseWebhookHandler
{
    public function process()
    {

    }

    protected function matchCompany(string $email)
    {
        return Company::where("expense_mailbox", $email)->first();
    }
}
