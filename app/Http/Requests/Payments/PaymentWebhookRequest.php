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
use App\Utils\Traits\MakesHash;

class PaymentWebhookRequest extends Request
{
    use MakesHash;

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
        return CompanyGateway::find($this->decodePrimaryKey($this->company_gateway_id))->firstOrFail();
    }

    /**
     * Resolve payment hash.
     *
     * @param string $hash
     * @return null|\App\Models\PaymentHash
     */
    public function getPaymentHash(): ?PaymentHash
    {
        if ($this->query('hash')) {
            return PaymentHash::where('hash', $this->query('hash'))->firstOrFail();
        }

        return null;
    }

    /**
     * Resolve possible payment in the request.
     *
     * @return null|\App\Models\Payment
     */
    public function getPayment(): ?Payment
    {
        /**
         * Some gateways, like Checkout, we can dynamically pass payment hash,
         * which we will resolve here and get payment information from it.
         */
        if ($this->getPaymentHash()) {
            return $this->getPaymentHash()->payment;
        }

        /**
         * Some gateways, like Stripe, send us transcation reference via webhook,
         * so we can resolve payment from there.
         */
        if ($this->has('data') && $this->has('type')) {
            $src = $this->data['object']['id'];

            info('Using src: ' . $src);

            $payment = \App\Models\Payment::where('transaction_reference', $src)->first();

            info('payment fetched!');
            info($payment);
        }

        info('before abort, 97');

        abort(404);
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
