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

namespace App\DataMapper;

class PaymentTransaction
{
    public $transaction_id;

    public $gateway_response;

    public $account_gateway_id;

    public $type_id;

    public $status; // prepayment|payment|response|completed

    public $invoices;
}
