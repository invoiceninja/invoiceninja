<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\Gateways\Mollie;

use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\PaymentHash;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Http\FormRequest;

class Mollie3dsRequest extends FormRequest
{
    use MakesHash;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            //
        ];
    }

    public function getCompany(): ?Company
    {
        /** @var \App\Models\Company */
        return Company::where('company_key', $this->company_key)->first();
    }

    public function getCompanyGateway(): ?CompanyGateway
    {
        /** @var \App\Models\CompanyGateway */
        return CompanyGateway::find($this->decodePrimaryKey($this->company_gateway_id));
    }

    public function getPaymentHash(): ?PaymentHash
    {
        /** @var \App\Models\PaymentHash */
        return PaymentHash::where('hash', $this->hash)->first();
    }

    public function getClient(): ?Client
    {
        /** @var \App\Models\Client */
        return Client::find($this->getPaymentHash()->data->client_id);
    }

    public function getPaymentId(): ?string
    {
        return $this->getPaymentHash()->data->payment_id;
    }
}
