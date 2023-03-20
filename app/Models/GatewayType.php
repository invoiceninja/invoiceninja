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
 * App\Models\GatewayType
 *
 * @property int $id
 * @property string|null $alias
 * @property string|null $name
 * @property-read \App\Models\Gateway|null $gateway
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PaymentType> $payment_methods
 * @property-read int|null $payment_methods_count
 * @method static \Illuminate\Database\Eloquent\Builder|StaticModel company()
 * @method static \Illuminate\Database\Eloquent\Builder|StaticModel exclude($columns)
 * @method static \Illuminate\Database\Eloquent\Builder|GatewayType newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GatewayType newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GatewayType query()
 * @method static \Illuminate\Database\Eloquent\Builder|GatewayType whereAlias($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GatewayType whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GatewayType whereName($value)
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PaymentType> $payment_methods
 * @mixin \Eloquent
 */
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

    const HOSTED_PAGE = 14; // For gateways that contain multiple methods.

    const GIROPAY = 15;

    const PRZELEWY24 = 16;

    const EPS = 17;

    const DIRECT_DEBIT = 18;

    const ACSS = 19;

    const BECS = 20;

    const INSTANT_BANK_PAY = 21;

    const FPX = 22;

    const KLARNA = 23;

    const BACS = 24;

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
            case self::IDEAL:
                return ctrans('texts.ideal');
            case self::HOSTED_PAGE:
                return ctrans('texts.aio_checkout');
            case self::PRZELEWY24:
                return ctrans('texts.przelewy24');
            case self::GIROPAY:
                return ctrans('texts.giropay');
            case self::EPS:
                return ctrans('texts.eps');
            case self::BECS:
                return ctrans('texts.becs');
            case self::BACS:
                return ctrans('texts.bacs');
            case self::ACSS:
                return ctrans('texts.acss');
            case self::DIRECT_DEBIT:
                return ctrans('texts.payment_type_direct_debit');
            case self::INSTANT_BANK_PAY:
                return ctrans('texts.payment_type_instant_bank_pay');
            case self::FPX:
                return ctrans('texts.fpx');
            case self::KLARNA:
                return ctrans('texts.klarna');
            default:
                return ' ';
                break;
        }
    }
}
