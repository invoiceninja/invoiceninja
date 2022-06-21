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

namespace App\PaymentDrivers\Eway;

class ErrorCode
{
    private static $success = [
        'A2000' =>	'Transaction Approved',
        'A2008' =>	'Honour With Identification',
        'A2010' =>	'Approved For Partial Amount',
        'A2011' =>	'Approved, VIP',
        'A2016' =>	'Approved, Update Track 3',
    ];

    private static $failure = [
        'D4401' =>	'Refer to Issuer',
        'D4402' =>	'Refer to Issuer, special',
        'D4403' =>	'No Merchant',
        'D4404' =>	'Pick Up Card',
        'D4405' =>	'Do Not Honour',
        'D4406' =>	'Error',
        'D4407' =>	'Pick Up Card, Special',
        'D4409' =>	'Request In Progress',
        'D4412' =>	'Invalid Transaction',
        'D4413' =>	'Invalid Amount',
        'D4414' =>	'Invalid Card Number',
        'D4415' =>	'No Issuer',
        'D4417' =>	'3D Secure Error',
        'D4419' =>	'Re-enter Last Transaction',
        'D4421' =>	'No Action Taken',
        'D4422' =>	'Suspected Malfunction',
        'D4423' =>	'Unacceptable Transaction Fee',
        'D4425' =>	'Unable to Locate Record On File',
        'D4430' =>	'Format Error',
        'D4431' =>	'Bank Not Supported By Switch',
        'D4433' =>	'Expired Card, Capture',
        'D4434' =>	'Suspected Fraud, Retain Card',
        'D4435' =>	'Card Acceptor, Contact Acquirer, Retain Card',
        'D4436' =>	'Restricted Card, Retain Card',
        'D4437' =>	'Contact Acquirer Security Department, Retain Card',
        'D4438' =>	'PIN Tries Exceeded, Capture',
        'D4439' =>	'No Credit Account',
        'D4440' =>	'Function Not Supported',
        'D4441' =>	'Lost Card',
        'D4442' =>	'No Universal Account',
        'D4443' =>	'Stolen Card',
        'D4444' =>	'No Investment Account',
        'D4450' =>	'Click-to-Pay (Visa Checkout) Transaction',
        'D4451' =>	'Insufficient Funds',
        'D4452' =>	'No Cheque Account',
        'D4453' =>	'No Savings Account',
        'D4454' =>	'Expired Card',
        'D4455' =>	'Incorrect PIN',
        'D4456' =>	'No Card Record',
        'D4457' =>	'Function Not Permitted to Cardholder',
        'D4458' =>	'Function Not Permitted to Terminal',
        'D4459' =>	'Suspected Fraud',
        'D4460' =>	'Acceptor Contact Acquirer',
        'D4461' =>	'Exceeds Withdrawal Limit',
        'D4462' =>	'Restricted Card',
        'D4463' =>	'Security Violation',
        'D4464' =>	'Original Amount Incorrect',
        'D4466' =>	'Acceptor Contact Acquirer, Security',
        'D4467' =>	'Capture Card',
        'D4475' =>	'PIN Tries Exceeded',
        'D4476' =>	'Invalidate Txn Reference',
        'D4481' =>	'Accumulated Transaction Counter (Amount) Exceeded',
        'D4482' =>	'CVV Validation Error',
        'D4483' =>	'Acquirer Is Not Accepting Transactions From You At This Time',
        'D4484' =>	'Acquirer Is Not Accepting This Transaction',
        'D4490' =>	'Cut off In Progress',
        'D4491' =>	'Card Issuer Unavailable',
        'D4492' =>	'Unable To Route Transaction',
        'D4493' =>	'Cannot Complete, Violation Of The Law',
        'D4494' =>	'Duplicate Transaction',
        'D4495' =>	'Amex Declined',
        'D4496' =>	'System Error',
        'D4497' =>	'MasterPass Error',
        'D4498' =>	'PayPal Create Transaction Error',
        'D4499' =>	'Invalid Transaction for Auth/Void',
    ];

    public static function getStatus($code)
    {
        if (array_key_exists($code, self::$success)) {
            return ['success' => true, 'message' => self::$success[$code]];
        }

        if (array_key_exists($code, self::$failure)) {
            return ['success' => false, 'message' => self::$failure[$code]];
        }

        return ['success' => false, 'message' => "Unknown error message code - {$code}"];
    }
}
