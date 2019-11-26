<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentType extends StaticModel
{
    /**
     * @var bool
     */
    public $timestamps = false;
    
    const CREDIT = 1;
    const ACH = 5;
    const VISA = 6;
    const MASTERCARD = 7;
    const AMERICAN_EXPRESS = 8;
    const DISCOVER = 9;
    const DINERS = 10;
    const EUROCARD = 11;
    const NOVA = 12;
    const CREDIT_CARD_OTHER = 13;
    const PAYPAL = 14;
    const CARTE_BLANCHE = 17;
    const UNIONPAY = 18;
    const JCB = 19;
    const LASER = 20;
    const MAESTRO = 21;
    const SOLO = 22;
    const SWITCH = 23;
    const ALIPAY = 28;
    const SOFORT = 29;
    const SEPA = 30;
    const GOCARDLESS = 31;
    const CRYPTO = 32;

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
