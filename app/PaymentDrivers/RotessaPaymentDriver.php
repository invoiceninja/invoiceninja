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

namespace App\PaymentDrivers;

use Omnipay\Omnipay;
use App\Models\Payment;
use App\Models\SystemLog;
use App\Models\PaymentHash;
use Illuminate\Support\Arr;
use App\Models\GatewayType;
use Omnipay\Rotessa\Gateway;
use App\Utils\Traits\MakesHash;
use App\PaymentDrivers\BaseDriver;
use App\PaymentDrivers\Rotessa\Bacs;
use App\PaymentDrivers\Rotessa\Acss;
use App\PaymentDrivers\Rotessa\DirectDebit;
use App\PaymentDrivers\Rotessa\BankTransfer;

class RotessaPaymentDriver extends BaseDriver
{
    use MakesHash;

    public $refundable = false;

    public $token_billing = true;

    public $can_authorise_credit_card = true;

    public Gateway $gateway;

    public $payment_method;

    public static $methods = [
        GatewayType::BANK_TRANSFER => BankTransfer::class,
        GatewayType::BACS => Bacs::class,
        GatewayType::ACSS => Acss::class,
        GatewayType::DIRECT_DEBIT => DirectDebit::class
    ];

    public function init(): self
    {
       
        $this->gateway = Omnipay::create(
            $this->company_gateway->gateway->provider
        );
        $this->gateway->initialize((array) $this->company_gateway->getConfig());
        return $this;
    }

    public function gatewayTypes(): array
    {
        $types = [];

        if ($this->client
            && isset($this->client->country)
            && (in_array($this->client->country->iso_3166_2, ['US']) || ($this->client->gateway_tokens()->where('gateway_type_id', GatewayType::BANK_TRANSFER)->exists()))
        ) {
            $types[] = GatewayType::BANK_TRANSFER;
        }

        if ($this->client
            && $this->client->currency()
            && in_array($this->client->currency()->code, ['CAD', 'USD'])
            && isset($this->client->country)
            && in_array($this->client->country->iso_3166_2, ['CA', 'US'])) {
            $types[] = GatewayType::DIRECT_DEBIT;
            $types[] = GatewayType::ACSS;
        }

        return $types;
    }

    
    public function setPaymentMethod($payment_method_id)
    {
        $class = self::$methods[$payment_method_id];
        $this->payment_method = new $class($this);

        return $this;
    }
    
    public function authorizeView(array $data)
    {
        return $this->payment_method->authorizeView($data);
    }

    public function authorizeResponse($request)
    {
        return $this->payment_method->authorizeResponse($request);
    }

    public function processPaymentView(array $data)
    {
        return $this->payment_method->paymentView($data);
    }

    public function processPaymentResponse($request)
    {
        return $this->payment_method->paymentResponse($request);
    }

    
}
