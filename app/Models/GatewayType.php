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

    public const CREDIT_CARD = 1;

    public const BANK_TRANSFER = 2;

    public const PAYPAL = 3;

    public const CRYPTO = 4;

    public const CUSTOM = 5;

    public const ALIPAY = 6;

    public const SOFORT = 7;

    public const APPLE_PAY = 8;

    public const SEPA = 9;

    public const CREDIT = 10;

    public const KBC = 11;

    public const BANCONTACT = 12;

    public const IDEAL = 13;

    public const HOSTED_PAGE = 14; // For gateways that contain multiple methods.

    public const GIROPAY = 15;

    public const PRZELEWY24 = 16;

    public const EPS = 17;

    public const DIRECT_DEBIT = 18;

    public const ACSS = 19;

    public const BECS = 20;

    public const INSTANT_BANK_PAY = 21;

    public const FPX = 22;

    public const KLARNA = 23;

    public const BACS = 24;

    public const VENMO = 25;

    public const MERCADOPAGO = 26;

    public const MYBANK = 27;

    public const PAYLATER = 28;

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
                return ctrans('texts.bank_transfer') . " / " . ctrans('texts.payment_type_direct_debit');
            case self::INSTANT_BANK_PAY:
                return ctrans('texts.payment_type_instant_bank_pay');
            case self::FPX:
                return ctrans('texts.fpx');
            case self::KLARNA:
                return ctrans('texts.klarna');
            case self::VENMO:
                return ctrans('texts.payment_type_Venmo');
            case self::MERCADOPAGO:
                return ctrans('texts.mercado_pago');
            case self::MYBANK:
                return ctrans('texts.mybank');
            case self::PAYLATER:
                return ctrans('texts.paypal_paylater');
            default:
                return ' ';
        }
    }
}
