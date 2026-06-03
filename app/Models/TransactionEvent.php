<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Models;

use App\DataMapper\ReportData;
use App\DataMapper\TransactionEventMetadata;

/**
 * Class Bank.
 *
 * @property int $id
 * @property int $company_id
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
 * @property ReportData|null $reporting_data
 * @property int $event_id
 * @property int $timestamp
 * @property array|null $payment_request
 * @property TransactionEventMetadata|null $metadata
 * @property float $credit_balance
 * @property float $credit_amount
 * @property int|null $credit_status
 * @property \Carbon\Carbon|null $period
 * @property-read \App\Models\Invoice|null $invoice
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
        'reporting_data' => ReportData::class,
        'period' => 'date',
    ];

    public const INVOICE_UPDATED = 1;

    public const PAYMENT_REFUNDED = 2;

    public const PAYMENT_DELETED = 3;

    public const PAYMENT_CASH = 4;

    public const FR_B2C_TRANSACTION = 1001;

    public const FR_B2C_PAYMENT = 1002;

    public const FR_VAT_EXCLUDED_TRANSACTION = 1003;

    public const FR_VAT_EXCLUDED_PAYMENT = 1004;

    public const FR_REPORT_SUBMISSION_B2C = 1005;

    public const FR_REPORT_SUBMISSION_VAT_EXCLUDED = 1006;

    public const FR_REPORT_SUBMISSION_CORRECTIVE = 1007;

    public const FR_B2B_PAYMENT_RECEIVED_NOTIFICATION = 1008;

    public const FR_REPORTING_STATUS_PENDING = 1;

    public const FR_REPORTING_STATUS_SUBMITTED = 3;

    public const FR_REPORTING_STATUS_FAILED = 4;

    public const FR_REPORTING_STATUS_DEFERRED = 5;

    public const TAX_REPORTING_EVENTS = [
        self::INVOICE_UPDATED,
        self::PAYMENT_REFUNDED,
        self::PAYMENT_DELETED,
        self::PAYMENT_CASH,
    ];

    public const FR_REPORTING_EVENTS = [
        self::FR_B2C_TRANSACTION,
        self::FR_B2C_PAYMENT,
        self::FR_VAT_EXCLUDED_TRANSACTION,
        self::FR_VAT_EXCLUDED_PAYMENT,
    ];

    public const FR_PAYMENT_NOTIFICATION_EVENTS = [
        self::FR_B2B_PAYMENT_RECEIVED_NOTIFICATION,
    ];

    public const FR_REPORT_SUBMISSION_EVENTS = [
        self::FR_REPORT_SUBMISSION_B2C,
        self::FR_REPORT_SUBMISSION_VAT_EXCLUDED,
        self::FR_REPORT_SUBMISSION_CORRECTIVE,
    ];

    public function invoice(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Invoice::class)->withTrashed();
    }

}
