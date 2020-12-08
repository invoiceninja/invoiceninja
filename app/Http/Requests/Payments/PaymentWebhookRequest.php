<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Requests\Payments;

use App\Http\Requests\Request;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\Payment;
use App\Models\PaymentHash;

class PaymentWebhookRequest extends Request
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            //
        ];
    }

    /**
     * Resolve company gateway.
     *
     * @param mixed $id
     * @return null|\App\Models\CompanyGateway
     */
    public function getCompanyGateway(): ?CompanyGateway
    {
        return CompanyGateway::where('gateway_key', $this->gateway_key)->firstOrFail();
    }

    /**
     * Resolve payment hash.
     *
     * @param string $hash
     * @return null|\App\Http\Requests\Payments\PaymentHash
     */
    public function getPaymentHash(): ?PaymentHash
    {
        if ($this->query('hash')) {
            return PaymentHash::where('hash', $this->query('hash'))->firstOrFail();
        }
    }

    /**
     * Resolve possible payment in the request.
     *
     * @return null|\App\Models\Payment
     */
    public function getPayment(): ?Payment
    {
        $hash = $this->getPaymentHash();

        return $hash->payment;
    }

    /**
     * Resolve client from payment hash.
     *
     * @return null|\App\Models\Client
     */
    public function getClient(): ?Client
    {
        $hash = $this->getPaymentHash();

        return Client::find($hash->data->client_id)->firstOrFail();
    }

    /**
     * Resolve company from company_key parameter.
     *
     * @return null|\App\Models\Company
     */
    public function getCompany(): ?Company
    {
        return Company::where('company_key', $this->company_key)->firstOrFail();
    }
}
