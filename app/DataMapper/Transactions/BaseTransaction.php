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

namespace App\DataMapper\Transactions;

use App\Models\Client;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\TransactionEvent;

/**
 * BaseTransaction.
 */
class BaseTransaction implements TransactionInterface
{
    public $event_id = TransactionEvent::INVOICE_MARK_PAID;

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

    public function transform(array $data) :array
    {
        // $invoice = $data['invoice'];
        // $payment = $data['payment'];
        // $client = $data['client'];
        // $credit = $data['credit'];
        // $payment_request = $data['payment']['payment_request'];
        // $metadata = $data['metadata'];

        return array_merge(
            $data['invoice'],
            $data['payment'],
            $data['client'],
            $data['credit'],
            ['metadata' => $data['metadata']],
            ['event_id' => $this->event_id, 'timestamp' =>time()],
        );
        // return [
        //     'event_id' => $this->event_id,
        //     'client_id' => $client?->id,
        //     'invoice_id' => $invoice?->id,
        //     'payment_id' => $payment?->id,
        //     'credit_id' => $credit?->creditid,
        //     'client_balance' => $client?->balance,
        //     'client_paid_to_date' => $client?->paid_to_date,
        //     'client_credit_balance' => $client?->credit_balance,
        //     'invoice_balance' => $invoice?->balance,
        //     'invoice_amount' => $invoice?->amount,
        //     'invoice_partial' => $invoice?->partial,
        //     'invoice_paid_to_date' => $invoice?->paid_to_date,
        //     'invoice_status' => $invoice?->status_id,
        //     'payment_amount' => $payment?->amount,
        //     'payment_applied' => $payment?->applied,
        //     'payment_refunded' => $payment?->refunded,
        //     'payment_status' => $payment?->status_id,
        //     'paymentables' => $payment?->paymentables,
        //     'payment_request' => $payment_request,
        //     'metadata' => $metadata,
        //     'credit_balance' => $credit?->balance,
        //     'credit_amount' => $credit?->amount,
        //     'credit_status' => $credit?->status_id,
        //     'timestamp' => time(),
        // ];
    }
}
