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

namespace App\Livewire\PaymentMethods;

use Livewire\Component;
use App\Libraries\MultiDB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use App\Models\ClientGatewayToken;

class UpdateDefaultMethod extends Component
{
    #[Locked]
    public $db;

    #[Locked]
    public $token_id;

    #[Computed]
    public function token()
    {
        $contact = auth()->guard('contact')->user();
        abort_unless($contact, 403);

        $company = $contact->company;
        abort_unless($company && $this->db === $company->db, 403);

        MultiDB::setDb($company->db);

        return ClientGatewayToken::query()
            ->where('client_id', $contact->client_id)
            ->where('company_id', $contact->company_id)
            ->where('is_deleted', false)
            ->findOrFail($this->token_id);
    }

    public function makeDefault(): void
    {
        $token = $this->token();

        if ($token->is_default) {
            return;
        }

        $token->client->gateway_tokens()->update(['is_default' => 0]);

        $token->is_default = 1;
        $token->save();

        $this->dispatch('UpdateDefaultMethod::method-updated');
    }

    public function render()
    {
        return render('components.livewire.update-default-payment-method');
    }
}
