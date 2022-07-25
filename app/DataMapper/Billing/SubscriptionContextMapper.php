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

namespace App\DataMapper\Billing;

class SubscriptionContextMapper
{
    /**
     * @var int
     */
    public $subscription_id;

    /**
     * @var string
     */
    public $email;

    /**
     * @var int
     */
    public $client_id;

    /**
     * @var int
     */
    public $invoice_id;

    /**
     * @var string[]
     */
    public $casts = [
        'subscription_id' => 'integer',
        'email' => 'string',
        'client_id' => 'integer',
        'invoice_id' => 'integer',
    ];
}
