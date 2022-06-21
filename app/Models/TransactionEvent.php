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

namespace App\Models;

/**
 * Class Bank.
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
