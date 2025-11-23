<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Models;

use App\DataMapper\TransactionEventMetadata;

/**
 * Class Bank.
 *
 * @property int $id
 * @property int $client_id
 * @property int $invoice_id
 * @property int $payment_id
 * @property int $credit_id
 * @property float $client_balance
 * @property float $client_paid_to_date
 * @property float $client_credit_balance
 * @property float $invoice_balance
 * @property float $invoice_amount
 * @property float $invoice_partial
 * @property float $invoice_paid_to_date
 * @property int|null $invoice_status
 * @property float $payment_amount
 * @property float $payment_applied
 * @property float $payment_refunded
 * @property int|null $payment_status
 * @property array|null $paymentables
 * @property int $event_id
 * @property int $timestamp
 * @property array|null $payment_request
 * @property TransactionEventMetadata|null $metadata
 * @property float $credit_balance
 * @property float $credit_amount
 * @property int|null $credit_status
 * @property \Carbon\Carbon|null $period
 * @method static \Illuminate\Database\Eloquent\Builder|StaticModel company()
 * @method static \Illuminate\Database\Eloquent\Builder|StaticModel exclude($columns)
 * @mixin \Eloquent
 */
class TransactionEvent extends StaticModel
{
    public $timestamps = false;

    public $guarded = ['id'];

    public $casts = [
        'metadata' => TransactionEventMetadata::class,
        'payment_request' => 'array',
        'paymentables' => 'array',
        'period' => 'date',
    ];

    public const INVOICE_UPDATED = 1;

    public const PAYMENT_REFUNDED = 2;

    public const PAYMENT_DELETED = 3;

    public const PAYMENT_CASH = 4;

}
