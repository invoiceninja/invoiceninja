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

namespace App\Helpers\Mail\Webhook\Postmark;

use App\Factory\ExpenseFactory;
use App\Helpers\Mail\Webhook\BaseWebhookHandler;

interface PostmarkWebhookHandler extends BaseWebhookHandler
{
    public function process($data)
    {

        $email = '';

        $company = $this->matchCompany($email);
        if (!$company)
            return false;

        $expense = ExpenseFactory::create($company->id, $company->owner()->id);

    }
}
