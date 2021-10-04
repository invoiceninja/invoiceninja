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

namespace App\Models;

class GatewayType extends StaticModel
{
    public $timestamps = false;

    const CREDIT_CARD = 1;
    const BANK_TRANSFER = 2;
    const PAYPAL = 3;
    const CRYPTO = 4;
    const CUSTOM = 5;
    const ALIPAY = 6;
    const SOFORT = 7;
    const APPLE_PAY = 8;
    const SEPA = 9;
    const CREDIT = 10;
    const KBC = 11;
    const BANCONTACT = 12;
    const IDEAL = 13;
    
    public function gateway()
    {
        return $this->belongsTo(Gateway::class);
    }

    public function payment_methods()
    {
        return $this->hasMany(PaymentType::class);
    }

    public static function getAlias($type)
    {
        switch ($type) {
            case self::CREDIT_CARD:
                return ctrans('texts.credit_card');
            case self::BANK_TRANSFER:
                return ctrans('texts.bank_transfer');
            case self::PAYPAL:
                return ctrans('texts.paypal');
            case self::CRYPTO:
                return ctrans('texts.crypto');
            case self::CUSTOM:
                return ctrans('texts.custom');
            case self::ALIPAY:
                return ctrans('texts.alipay');
            case self::SOFORT:
                return ctrans('texts.sofort');
            case self::APPLE_PAY:
                return ctrans('texts.apple_pay');
            case self::SEPA:
                return ctrans('texts.sepa');
            case self::KBC:
                return ctrans('texts.kbc_cbc');
            case self::BANCONTACT:
                return ctrans('texts.bancontact');

            default:
                return 'Undefined.';
                break;
        }
    }
}
