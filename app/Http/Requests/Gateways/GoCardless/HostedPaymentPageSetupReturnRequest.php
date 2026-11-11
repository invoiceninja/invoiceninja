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
use Illuminate\Support\Facades\Log;

class HostedPaymentPageSetupReturnRequest extends FormRequest
{
    use MakesHash;

    private array $context_state = [];

    public function authorize(): bool
    {

        $this->context_state = Cache::get($this->route('context'), []);

        if (! isset($this->context_state['db'], $this->context_state['company_gateway_id'], $this->context_state['gateway_type_id'], $this->context_state['contact_key'])) {
            return $this->rejectCallback('missing_setup_context');
        }

        MultiDB::setDb($this->context_state['db']);

        $contact = $this->getContact();
        $company_gateway = $this->getCompanyGateway();

        if (! $contact) {
            return $this->rejectCallback('missing_contact_session');
        }

        if (! $company_gateway) {
            return $this->rejectCallback('missing_company_gateway');
        }

        if (! hash_equals((string) $this->context_state['contact_key'], (string) $contact->contact_key)) {
            return $this->rejectCallback('contact_mismatch');
        }

        if ($contact->client->company_id !== $company_gateway->company_id) {
            return $this->rejectCallback('client_company_mismatch');
        }

        if ($company_gateway->id !== (int) $this->context_state['company_gateway_id']) {
            return $this->rejectCallback('gateway_mismatch');
        }

        if (! hash_equals($company_gateway->company->company_key, (string) $this->route('company_key'))) {
            return $this->rejectCallback('company_key_mismatch');
        }

        if (! data_get($this->context_state, 'gocardless.billing_request')) {
            return $this->rejectCallback('missing_billing_request');
        }

        return true;
    }

    private function rejectCallback(string $reason): bool
    {
        Log::warning('GoCardless setup callback rejected', [
            'reason' => $reason,
            'host' => $this->getHost(),
            'scheme' => $this->getScheme(),
            'query_keys' => array_keys($this->query()),
        ]);

        return false;
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
