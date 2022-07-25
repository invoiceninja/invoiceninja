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

namespace App\Import\Definitions;

class PaymentMap
{
    public static function importable()
    {
        return [
            0 => 'payment.number',
            1 => 'payment.user_id',
            2 => 'payment.amount',
            3 => 'payment.refunded',
            4 => 'payment.applied',
            5 => 'payment.transaction_reference',
            6 => 'payment.private_notes',
            7 => 'payment.custom_value1',
            8 => 'payment.custom_value2',
            9 => 'payment.custom_value3',
            10 => 'payment.custom_value4',
            11 => 'payment.client_id',
            12 => 'payment.invoice_number',
            13 => 'payment.date',
            14 => 'payment.method',
        ];
    }

    public static function import_keys()
    {
        return [
            0 => 'texts.number',
            1 => 'texts.user',
            2 => 'texts.amount',
            3 => 'texts.refunded',
            4 => 'texts.applied',
            5 => 'texts.transaction_reference',
            6 => 'texts.private_notes',
            7 => 'texts.custom_value',
            8 => 'texts.custom_value',
            9 => 'texts.custom_value',
            10 => 'texts.custom_value',
            11 => 'texts.client',
            12 => 'texts.invoice_number',
            13 => 'texts.date',
            14 => 'texts.method',
        ];
    }
}
