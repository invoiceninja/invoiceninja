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
 * App\Models\PaymentType
 *
 * @property int $id
 * @property string $name
 * @property int|null $gateway_type_id
 * @method static \Illuminate\Database\Eloquent\Builder|StaticModel company()
 * @method static \Illuminate\Database\Eloquent\Builder|StaticModel exclude($columns)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentType newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentType newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentType query()
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentType whereGatewayTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentType whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentType whereName($value)
 * @mixin \Eloquent
 */
class PaymentType extends StaticModel
{
    /**
     * @var bool
     */
    public $timestamps = false;

    public const BANK_TRANSFER = 1;
    public const CASH = 2;
    public const ACH = 4;
    public const VISA = 5;
    public const MASTERCARD = 6;
    public const AMERICAN_EXPRESS = 7;
    public const DISCOVER = 8;
    public const DINERS = 9;
    public const EUROCARD = 10;
    public const NOVA = 11;
    public const CREDIT_CARD_OTHER = 12;
    public const PAYPAL = 13;
    public const CHECK = 15;
    public const CARTE_BLANCHE = 16;
    public const UNIONPAY = 17;
    public const JCB = 18;
    public const LASER = 19;
    public const MAESTRO = 20;
    public const SOLO = 21;
    public const SWITCH = 22;
    public const VENMO = 24;
    public const ALIPAY = 27;
    public const SOFORT = 28;
    public const SEPA = 29;
    public const GOCARDLESS = 30;
    public const CRYPTO = 31;
    public const CREDIT = 32;
    public const ZELLE = 33;
    public const MOLLIE_BANK_TRANSFER = 34;
    public const KBC = 35;
    public const BANCONTACT = 36;
    public const IDEAL = 37;
    public const HOSTED_PAGE = 38;
    public const GIROPAY = 39;
    public const PRZELEWY24 = 40;
    public const EPS = 41;
    public const DIRECT_DEBIT = 42;
    public const BECS = 43;
    public const ACSS = 44;
    public const INSTANT_BANK_PAY = 45;
    public const FPX = 46;
    public const KLARNA = 47;
    public const Interac_E_Transfer = 48;
    public const BACS = 49;
    public const STRIPE_BANK_TRANSFER = 50;
    public const CASH_APP = 51;
    public const PAY_LATER = 52;

    public array $type_names = [
        self::BANK_TRANSFER => 'payment_type_Bank Transfer',
        self::CASH => 'payment_type_Cash',
        self::CREDIT => 'payment_type_Credit',
        self::ZELLE => 'payment_type_Zelle',
        self::ACH => 'payment_type_ACH',
        self::VISA => 'payment_type_Visa Card',
        self::MASTERCARD => 'payment_type_MasterCard',
        self::AMERICAN_EXPRESS => 'payment_type_American Express',
        self::DISCOVER => 'payment_type_Discover Card',
        self::DINERS => 'payment_type_Diners Card',
        self::EUROCARD => 'payment_type_EuroCard',
        self::NOVA => 'payment_type_Nova',
        self::CREDIT_CARD_OTHER => 'payment_type_Credit Card Other',
        self::PAYPAL => 'payment_type_PayPal',
        self::CHECK => 'payment_type_Check',
        self::CARTE_BLANCHE => 'payment_type_Carte Blanche',
        self::UNIONPAY => 'payment_type_UnionPay',
        self::JCB => 'payment_type_JCB',
        self::LASER => 'payment_type_Laser',
        self::MAESTRO => 'payment_type_Maestro',
        self::SOLO => 'payment_type_Solo',
        self::SWITCH => 'payment_type_Switch',
        self::ALIPAY => 'payment_type_Alipay',
        self::SOFORT => 'payment_type_Sofort',
        self::SEPA => 'payment_type_SEPA',
        self::GOCARDLESS => 'payment_type_GoCardless',
        self::CRYPTO => 'payment_type_Crypto',
        self::MOLLIE_BANK_TRANSFER => 'payment_type_Mollie Bank Transfer',
        self::KBC => 'payment_type_KBC/CBC',
        self::BANCONTACT => 'payment_type_Bancontact',
        self::IDEAL => 'payment_type_iDEAL',
        self::HOSTED_PAGE => 'payment_type_Hosted Page',
        self::GIROPAY => 'payment_type_GiroPay',
        self::PRZELEWY24 => 'payment_type_Przelewy24',
        self::EPS => 'payment_type_EPS',
        self::DIRECT_DEBIT => 'payment_type_Direct Debit',
        self::BECS => 'payment_type_BECS',
        self::ACSS => 'payment_type_ACSS',
        self::INSTANT_BANK_PAY => 'payment_type_Instant Bank Pay',
        self::FPX => 'fpx',
        self::KLARNA => 'payment_type_Klarna',
        self::Interac_E_Transfer => 'payment_type_Interac E Transfer',
        self::STRIPE_BANK_TRANSFER => 'bank_transfer',
        self::CASH_APP => 'payment_type_Cash App',
        self::VENMO => 'payment_type_Venmo',
        self::PAY_LATER => 'payment_type_Pay Later',
    ];

    public static function parseCardType($cardName)
    {
        $cardTypes = [
            'visa' => self::VISA,
            'americanexpress' => self::AMERICAN_EXPRESS,
            'amex' => self::AMERICAN_EXPRESS,
            'mastercard' => self::MASTERCARD,
            'discover' => self::DISCOVER,
            'jcb' => self::JCB,
            'dinersclub' => self::DINERS,
            'carteblanche' => self::CARTE_BLANCHE,
            'chinaunionpay' => self::UNIONPAY,
            'unionpay' => self::UNIONPAY,
            'laser' => self::LASER,
            'maestro' => self::MAESTRO,
            'solo' => self::SOLO,
            'switch' => self::SWITCH,
        ];

        $cardName = strtolower(str_replace([' ', '-', '_'], '', $cardName));

        if (empty($cardTypes[$cardName]) && 1 == preg_match('/^('.implode('|', array_keys($cardTypes)).')/', $cardName, $matches)) {
            // Some gateways return extra stuff after the card name
            $cardName = $matches[1];
        }

        if (! empty($cardTypes[$cardName])) {
            return $cardTypes[$cardName];
        } else {
            return self::CREDIT_CARD_OTHER;
        }
    }

    public function name($id)
    {
        if (isset($this->type_names[$id])) {
            return ctrans("texts.".$this->type_names[$id]);
        }

        return ctrans('texts.manual_entry');
    }
}
