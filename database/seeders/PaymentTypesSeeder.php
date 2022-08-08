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

namespace Database\Seeders;

use App\Models\PaymentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

class PaymentTypesSeeder extends Seeder
{
    const BANK_LIBRARY_OFX = 1;

    const GATEWAY_TYPE_CREDIT_CARD = 1;

    const GATEWAY_TYPE_BANK_TRANSFER = 2;

    const GATEWAY_TYPE_PAYPAL = 3;

    const GATEWAY_TYPE_CRYPTO = 4;

    const GATEWAY_TYPE_DWOLLA = 5;

    const GATEWAY_TYPE_CUSTOM1 = 6;

    const GATEWAY_TYPE_ALIPAY = 7;

    const GATEWAY_TYPE_SOFORT = 8;

    const GATEWAY_TYPE_SEPA = 9;

    const GATEWAY_TYPE_GOCARDLESS = 10;

    const GATEWAY_TYPE_APPLE_PAY = 11;

    const GATEWAY_TYPE_CUSTOM2 = 12;

    const GATEWAY_TYPE_CUSTOM3 = 13;

    const GATEWAY_TYPE_CREDIT = 14;

    public function run()
    {
        Model::unguard();

        $paymentTypes = [
            //            ['name' => 'Apply Credit'],
            ['name' => 'Bank Transfer', 'gateway_type_id' => self::GATEWAY_TYPE_BANK_TRANSFER],
            ['name' => 'Cash'],
            ['name' => 'Debit', 'gateway_type_id' => self::GATEWAY_TYPE_CREDIT_CARD],
            ['name' => 'ACH', 'gateway_type_id' => self::GATEWAY_TYPE_BANK_TRANSFER],
            ['name' => 'Visa Card', 'gateway_type_id' => self::GATEWAY_TYPE_CREDIT_CARD],
            ['name' => 'MasterCard', 'gateway_type_id' => self::GATEWAY_TYPE_CREDIT_CARD],
            ['name' => 'American Express', 'gateway_type_id' => self::GATEWAY_TYPE_CREDIT_CARD],
            ['name' => 'Discover Card', 'gateway_type_id' => self::GATEWAY_TYPE_CREDIT_CARD],
            ['name' => 'Diners Card', 'gateway_type_id' => self::GATEWAY_TYPE_CREDIT_CARD],
            ['name' => 'EuroCard', 'gateway_type_id' => self::GATEWAY_TYPE_CREDIT_CARD],
            ['name' => 'Nova', 'gateway_type_id' => self::GATEWAY_TYPE_CREDIT_CARD],
            ['name' => 'Credit Card Other', 'gateway_type_id' => self::GATEWAY_TYPE_CREDIT_CARD],
            ['name' => 'PayPal', 'gateway_type_id' => self::GATEWAY_TYPE_PAYPAL],
            ['name' => 'Google Wallet'],
            ['name' => 'Check'],
            ['name' => 'Carte Blanche', 'gateway_type_id' => self::GATEWAY_TYPE_CREDIT_CARD],
            ['name' => 'UnionPay', 'gateway_type_id' => self::GATEWAY_TYPE_CREDIT_CARD],
            ['name' => 'JCB', 'gateway_type_id' => self::GATEWAY_TYPE_CREDIT_CARD],
            ['name' => 'Laser', 'gateway_type_id' => self::GATEWAY_TYPE_CREDIT_CARD],
            ['name' => 'Maestro', 'gateway_type_id' => self::GATEWAY_TYPE_CREDIT_CARD],
            ['name' => 'Solo', 'gateway_type_id' => self::GATEWAY_TYPE_CREDIT_CARD],
            ['name' => 'Switch', 'gateway_type_id' => self::GATEWAY_TYPE_CREDIT_CARD],
            ['name' => 'iZettle', 'gateway_type_id' => self::GATEWAY_TYPE_CREDIT_CARD],
            ['name' => 'Swish', 'gateway_type_id' => self::GATEWAY_TYPE_BANK_TRANSFER],
            ['name' => 'Venmo'],
            ['name' => 'Money Order'],
            ['name' => 'Alipay', 'gateway_type_id' => self::GATEWAY_TYPE_ALIPAY],
            ['name' => 'Sofort', 'gateway_type_id' => self::GATEWAY_TYPE_SOFORT],
            ['name' => 'SEPA', 'gateway_type_id' => self::GATEWAY_TYPE_SEPA],
            ['name' => 'GoCardless', 'gateway_type_id' => self::GATEWAY_TYPE_GOCARDLESS],
            ['name' => 'Crypto', 'gateway_type_id' => self::GATEWAY_TYPE_CRYPTO],
            ['name' => 'Credit', 'gateway_type_id' => self::GATEWAY_TYPE_CREDIT],
            ['name' => 'Zelle'],
        ];

        $x = 1;
        foreach ($paymentTypes as $paymentType) {
            $record = PaymentType::where('name', '=', $paymentType['name'])->first();

            if ($record) {
                $record->id = $x;
                $record->name = $paymentType['name'];
                $record->gateway_type_id = ! empty($paymentType['gateway_type_id']) ? $paymentType['gateway_type_id'] : null;

                $record->save();
            } else {
                $paymentType['id'] = $x;
                PaymentType::create($paymentType);
            }

            $x++;
        }
    }
}
