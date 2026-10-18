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
use App\Models\ClientContact;
use App\Models\CompanyGateway;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Cache;

class HostedPaymentPageSetupReturnRequest extends FormRequest
{
    use MakesHash;

    private array $context_state = [];

    public function authorize(): bool
    {
        if (! $this->hasValidSignature()) {
            return false;
        }

        $this->context_state = Cache::get($this->route('context'), []);

        if (! isset($this->context_state['db'], $this->context_state['company_gateway_id'], $this->context_state['gateway_type_id'], $this->context_state['contact_key'])) {
            return false;
        }

        MultiDB::setDb($this->context_state['db']);

        $contact = $this->getContact();
        $company_gateway = $this->getCompanyGateway();

        return $contact
            && $company_gateway
            && hash_equals((string) $this->context_state['contact_key'], (string) $contact->contact_key)
            && $contact->client->company_id === $company_gateway->company_id
            && $company_gateway->id === (int) $this->context_state['company_gateway_id']
            && hash_equals($company_gateway->company->company_key, (string) $this->route('company_key'))
            && data_get($this->context_state, 'gocardless.billing_request');
    }

    public function rules(): array
    {
        return [];
    }

    public function getContextState(): array
    {
        return $this->context_state;
    }

    public function getContact(): ?ClientContact
    {
        $contact = auth()->guard('contact')->user();

        return $contact instanceof ClientContact ? $contact : null;
    }

    public function getCompanyGateway(): ?CompanyGateway
    {
        return CompanyGateway::query()->find($this->decodePrimaryKey($this->route('company_gateway_id')));
    }
}
