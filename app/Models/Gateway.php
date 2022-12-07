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

class Gateway extends StaticModel
{
    protected $casts = [
        'is_offsite' => 'boolean',
        'is_secure' => 'boolean',
        'recommended' => 'boolean',
        'visible' => 'boolean',
        'sort_order' => 'int',
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'default_gateway_type_id' => 'string',
        // 'fields' => 'json',
        'fields' => 'object',
        'options' => 'array',
    ];

    protected $appends = [
        'options',
    ];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    public function getOptionsAttribute()
    {
        return $this->getMethods();
    }

    /**
     * Test if gateway is custom.
     * @return bool TRUE|FALSE
     */
    public function isCustom() :bool
    {
        return in_array($this->id, [62, 67, 68]); //static table ids of the custom gateways
    }

    public function getHelp()
    {
        $link = '';

        if ($this->id == 1) {
            $link = 'http://reseller.authorize.net/application/?id=5560364';
        } elseif ($this->id == 15) {
            $link = 'https://www.paypal.com/us/cgi-bin/webscr?cmd=_login-api-run';
        } elseif ($this->id == 24) {
            $link = 'https://www.2checkout.com/referral?r=2c37ac2298';
        } elseif ($this->id == 35) {
            $link = 'https://bitpay.com/dashboard/signup';
        } elseif ($this->id == 18) {
            $link = 'https://applications.sagepay.com/apply/2C02C252-0F8A-1B84-E10D-CF933EFCAA99';
        } elseif ($this->id == 20 || $this->id == 56) {
            $link = 'https://dashboard.stripe.com/account/apikeys';
        } elseif ($this->id == 59) {
            $link = 'https://www.forte.net/';
        }

        return $link;
    }

    /**
     * Returns an array of methods and the gatewaytypes possible
     *
     * @return array
     */
    public function getMethods()
    {
        switch ($this->id) {
            case 1:
                return [GatewayType::CREDIT_CARD => ['refund' => true, 'token_billing' => true]]; //Authorize.net
                break;
            case 3:
                return [GatewayType::CREDIT_CARD => ['refund' => false, 'token_billing' => true]]; //eWay
                break;
            case 11:
                return [GatewayType::CREDIT_CARD => ['refund' => false, 'token_billing' => true]]; //Payfast
                break;
            case 7:
                return [
                    GatewayType::CREDIT_CARD => ['refund' => false, 'token_billing' => true, 'webhooks' => [' ']], // Mollie
                    GatewayType::BANK_TRANSFER => ['refund' => false, 'token_billing' => true, 'webhooks' => [' ']],
                    GatewayType::KBC => ['refund' => false, 'token_billing' => false, 'webhooks' => [' ']],
                    GatewayType::BANCONTACT => ['refund' => false, 'token_billing' => false, 'webhooks' => [' ']],
                    GatewayType::IDEAL => ['refund' => false, 'token_billing' => false, 'webhooks' => [' ']],
                ];
            case 15:
                return [
                    GatewayType::PAYPAL => ['refund' => false, 'token_billing' => false],
                ]; //Paypal
                break;
            case 20:
                return [
                    GatewayType::CREDIT_CARD => ['refund' => true, 'token_billing' => true],
                    GatewayType::BANK_TRANSFER => ['refund' => true, 'token_billing' => true, 'webhooks' => ['source.chargeable', 'charge.succeeded', 'payment_intent.succeeded', 'charge.failed', 'payment_intent.payment_failed']],
                    GatewayType::ALIPAY => ['refund' => false, 'token_billing' => false],
                    GatewayType::APPLE_PAY => ['refund' => false, 'token_billing' => false],
                    GatewayType::SOFORT => ['refund' => true, 'token_billing' => true, 'webhooks' => ['source.chargeable', 'charge.succeeded']], //Stripe
                    GatewayType::SEPA => ['refund' => true, 'token_billing' => true, 'webhooks' => ['source.chargeable', 'charge.succeeded']],
                    GatewayType::PRZELEWY24 => ['refund' => true, 'token_billing' => true, 'webhooks' => ['source.chargeable', 'charge.succeeded']],
                    GatewayType::GIROPAY => ['refund' => true, 'token_billing' => true, 'webhooks' => ['source.chargeable', 'charge.succeeded']],
                    GatewayType::EPS => ['refund' => true, 'token_billing' => true, 'webhooks' => ['source.chargeable', 'charge.succeeded']],
                    GatewayType::BANCONTACT => ['refund' => true, 'token_billing' => true, 'webhooks' => ['source.chargeable', 'charge.succeeded']],
                    GatewayType::BECS => ['refund' => true, 'token_billing' => true, 'webhooks' => ['source.chargeable', 'charge.succeeded']],
                    GatewayType::IDEAL => ['refund' => true, 'token_billing' => true, 'webhooks' => ['source.chargeable', 'charge.succeeded']],
                    GatewayType::ACSS => ['refund' => true, 'token_billing' => true, 'webhooks' => ['source.chargeable', 'charge.succeeded']],
                    GatewayType::FPX => ['refund' => true, 'token_billing' => true, 'webhooks' => ['source.chargeable', 'charge.succeeded']], ];
            case 39:
                return [GatewayType::CREDIT_CARD => ['refund' => true, 'token_billing' => true, 'webhooks' => [' ']]]; //Checkout
                break;
            case 46:
                return [GatewayType::CREDIT_CARD => ['refund' => true, 'token_billing' => true]]; //Paytrace
            case 49:
                return [
                    GatewayType::CREDIT_CARD => ['refund' => true, 'token_billing' => true],
                    GatewayType::BANK_TRANSFER => ['refund' => true, 'token_billing' => true, 'webhooks' => [' ']],
                ]; //WePay
                break;
            case 50:
                return [
                    GatewayType::CREDIT_CARD => ['refund' => true, 'token_billing' => true], //Braintree
                    GatewayType::PAYPAL => ['refund' => true, 'token_billing' => true],
                    GatewayType::BANK_TRANSFER => ['refund' => true, 'token_billing' => true, 'webhooks' => [' ']],
                ];
                break;
            case 56:
                return [
                    GatewayType::CREDIT_CARD => ['refund' => true, 'token_billing' => true, 'webhooks' => ['payment_intent.succeeded']],
                    GatewayType::BANK_TRANSFER => ['refund' => true, 'token_billing' => true, 'webhooks' => ['source.chargeable', 'charge.succeeded']],
                    GatewayType::ALIPAY => ['refund' => false, 'token_billing' => false],
                    GatewayType::APPLE_PAY => ['refund' => false, 'token_billing' => false],
                    GatewayType::SOFORT => ['refund' => true, 'token_billing' => true, 'webhooks' => ['source.chargeable', 'charge.succeeded', 'payment_intent.succeeded']], //Stripe
                    GatewayType::SEPA => ['refund' => true, 'token_billing' => true, 'webhooks' => ['source.chargeable', 'charge.succeeded', 'payment_intent.succeeded']],
                    GatewayType::PRZELEWY24 => ['refund' => true, 'token_billing' => true, 'webhooks' => ['source.chargeable', 'charge.succeeded', 'payment_intent.succeeded']],
                    GatewayType::GIROPAY => ['refund' => true, 'token_billing' => true, 'webhooks' => ['source.chargeable', 'charge.succeeded', 'payment_intent.succeeded']],
                    GatewayType::EPS => ['refund' => true, 'token_billing' => true, 'webhooks' => ['source.chargeable', 'charge.succeeded', 'payment_intent.succeeded']],
                    GatewayType::BANCONTACT => ['refund' => true, 'token_billing' => true, 'webhooks' => ['source.chargeable', 'charge.succeeded', 'payment_intent.succeeded']],
                    GatewayType::BECS => ['refund' => true, 'token_billing' => true, 'webhooks' => ['source.chargeable', 'charge.succeeded', 'payment_intent.succeeded']],
                    GatewayType::IDEAL => ['refund' => true, 'token_billing' => true, 'webhooks' => ['source.chargeable', 'charge.succeeded', 'payment_intent.succeeded']],
                    GatewayType::ACSS => ['refund' => true, 'token_billing' => true, 'webhooks' => ['source.chargeable', 'charge.succeeded', 'payment_intent.succeeded']],
                    GatewayType::FPX => ['refund' => true, 'token_billing' => true, 'webhooks' => ['source.chargeable', 'charge.succeeded']],
                ];
                break;
            case 57:
                return [
                    GatewayType::CREDIT_CARD => ['refund' => false, 'token_billing' => true], //Square
                ];
                break;
            case 52:
                return [
                    GatewayType::BANK_TRANSFER => ['refund' => false, 'token_billing' => true, 'webhooks' => [' ']], // GoCardless
                    GatewayType::DIRECT_DEBIT => ['refund' => false, 'token_billing' => true, 'webhooks' => [' ']],
                    GatewayType::SEPA => ['refund' => false, 'token_billing' => true, 'webhooks' => [' ']],
                    GatewayType::INSTANT_BANK_PAY => ['refund' => false, 'token_billing' => true, 'webhooks' => [' ']],
                ];
                break;
            case 58:
                return [
                    GatewayType::HOSTED_PAGE => ['refund' => false, 'token_billing' => false, 'webhooks' => [' ']], // Razorpay
                ];
                break;
            case 59:
                return [
                    GatewayType::CREDIT_CARD => ['refund' => true, 'token_billing' => true], // Forte
                    GatewayType::BANK_TRANSFER => ['refund' => true, 'token_billing' => true, 'webhooks' => [' ']],
                ];
                break;
            default:
                return [];
                break;
        }
    }
}
