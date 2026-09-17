<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\Gateways\GoCardless;

use App\Libraries\MultiDB;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\PaymentHash;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Http\FormRequest;

class HostedPaymentPageReturnRequest extends FormRequest
{
    use MakesHash;

    public function authorize(): bool
    {
        MultiDB::findAndSetDbByCompanyKey($this->route('company_key'));

        if (! $this->hasValidSignature()) {
            return false;
        }

        $company = $this->getCompany();
        $company_gateway = $this->getCompanyGateway();
        $payment_hash = $this->getPaymentHash();
        $client = $this->getClient();

        return $company
            && $company_gateway
            && $payment_hash
            && $client
            && $company_gateway->company_id === $company->id
            && $client->company_id === $company->id
            && data_get($payment_hash->data, 'gocardless.billing_request');
    }

    public function rules(): array
    {
        return [];
    }

    public function getCompany(): ?Company
    {
        return Company::query()->where('company_key', $this->route('company_key'))->first();
    }

    public function getCompanyGateway(): ?CompanyGateway
    {
        return CompanyGateway::query()->find($this->decodePrimaryKey($this->route('company_gateway_id')));
    }

    public function getPaymentHash(): ?PaymentHash
    {
        return PaymentHash::query()->where('hash', $this->route('hash'))->first();
    }

    public function getClient(): ?Client
    {
        $client_id = data_get($this->getPaymentHash()?->data, 'client_id');

        return $client_id ? Client::query()->find($client_id) : null;
    }
}
