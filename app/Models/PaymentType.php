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

class PaymentType extends StaticModel
{
    /**
     * @var bool
     */
    public $timestamps = false;

    const CREDIT = 32;
    const ACH = 4;
    const VISA = 5;
    const MASTERCARD = 6;
    const AMERICAN_EXPRESS = 7;
    const DISCOVER = 8;
    const DINERS = 9;
    const EUROCARD = 10;
    const NOVA = 11;
    const CREDIT_CARD_OTHER = 12;
    const PAYPAL = 13;
    const CHECK = 15;
    const CARTE_BLANCHE = 16;
    const UNIONPAY = 17;
    const JCB = 18;
    const LASER = 19;
    const MAESTRO = 20;
    const SOLO = 21;
    const SWITCH = 22;
    const ALIPAY = 27;
    const SOFORT = 28;
    const SEPA = 29;
    const GOCARDLESS = 30;
    const CRYPTO = 31;
    const MOLLIE_BANK_TRANSFER = 34;
    const KBC = 35;
    const BANCONTACT = 36;
    const IDEAL = 37;
    const HOSTED_PAGE = 38;
    const GIROPAY = 39;
    const PRZELEWY24 = 40;
    const EPS = 41;
    const DIRECT_DEBIT = 42;
    const BECS = 43;
    const ACSS = 44;
    const INSTANT_BANK_PAY = 45;
    const FPX = 46;

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
}
