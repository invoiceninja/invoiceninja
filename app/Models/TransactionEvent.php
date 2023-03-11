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

namespace App\Models;

/**
 * Class Bank.
 *
 * @property int $id
 * @property int $client_id
 * @property int $invoice_id
 * @property int $payment_id
 * @property int $credit_id
 * @property string $client_balance
 * @property string $client_paid_to_date
 * @property string $client_credit_balance
 * @property string $invoice_balance
 * @property string $invoice_amount
 * @property string $invoice_partial
 * @property string $invoice_paid_to_date
 * @property int|null $invoice_status
 * @property string $payment_amount
 * @property string $payment_applied
 * @property string $payment_refunded
 * @property int|null $payment_status
 * @property array|null $paymentables
 * @property int $event_id
 * @property int $timestamp
 * @property array|null $payment_request
 * @property array|null $metadata
 * @property string $credit_balance
 * @property string $credit_amount
 * @property int|null $credit_status
 * @method static \Illuminate\Database\Eloquent\Builder|StaticModel company()
 * @method static \Illuminate\Database\Eloquent\Builder|StaticModel exclude($columns)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionEvent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionEvent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionEvent query()
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionEvent whereClientBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionEvent whereClientCreditBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionEvent whereClientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionEvent whereClientPaidToDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionEvent whereCreditAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionEvent whereCreditBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionEvent whereCreditId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionEvent whereCreditStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionEvent whereEventId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionEvent whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionEvent whereInvoiceAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionEvent whereInvoiceBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionEvent whereInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionEvent whereInvoicePaidToDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionEvent whereInvoicePartial($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionEvent whereInvoiceStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionEvent whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionEvent wherePaymentAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionEvent wherePaymentApplied($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionEvent wherePaymentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionEvent wherePaymentRefunded($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionEvent wherePaymentRequest($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionEvent wherePaymentStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionEvent wherePaymentables($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionEvent whereTimestamp($value)
 * @mixin \Eloquent
 */
class TransactionEvent extends StaticModel
{
    public $timestamps = false;

    public $guarded = ['id'];

    public $casts = [
        'metadata' => 'array',
        'payment_request' => 'array',
        'paymentables' => 'array',
    ];

    public const INVOICE_MARK_PAID = 1;

    public const INVOICE_UPDATED = 2;

    public const INVOICE_DELETED = 3;

    public const INVOICE_PAYMENT_APPLIED = 4;

    public const INVOICE_CANCELLED = 5;

    public const INVOICE_FEE_APPLIED = 6;

    public const INVOICE_REVERSED = 7;

    public const PAYMENT_MADE = 100;

    public const PAYMENT_APPLIED = 101;

    public const PAYMENT_REFUND = 102;

    public const PAYMENT_FAILED = 103;

    public const GATEWAY_PAYMENT_MADE = 104;

    public const PAYMENT_DELETED = 105;

    public const CLIENT_STATUS = 200;
}
