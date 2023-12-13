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

namespace App\Livewire\PaymentMethods;

use App\Libraries\MultiDB;
use Livewire\Component;

class UpdateDefaultMethod extends Component
{
    /** @var \App\Models\Company */
    public $company;

    /** @var \App\Models\ClientGatewayToken */
    public $token;

    /** @var \App\Models\Client */
    public $client;

    public function mount()
    {
        $this->company = $this->client->company;

        MultiDB::setDb($this->company->db);

        // $this->is_disabled = $this->token->is_default;
    }

    public function makeDefault(): void
    {
        if ($this->token->is_default) {
            return;
        }

        $this->client->gateway_tokens()->update(['is_default' => 0]);

        $this->token->is_default = 1;
        $this->token->save();

        $this->dispatch('UpdateDefaultMethod::method-updated');
    }

    public function render()
    {
        return render('components.livewire.update-default-payment-method');
    }
}
