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

namespace App\DataMapper\Transactions;

/**
 * MarkPaidTransaction.
 */
class MarkPaidTransaction
{
    
    public array $model = [
        'client_id',
        'invoice_id',
        'payment_id',
        'credit_id',
        'client_balance',
        'client_paid_to_date',
        'client_credit_balance',
        'invoice_balance',
        'invoice_amount',
        'invoice_partial',
        'invoice_paid_to_date',
        'invoice_status',
        'payment_amount',
        'payment_applied',
        'payment_refunded',
        'payment_status',
        'paymentables',
        'event_id',
        'timestamp',
        'payment_request',
        'metadata',
        'credit_balance',
        'credit_amount',
        'credit_status',
    ];



}
