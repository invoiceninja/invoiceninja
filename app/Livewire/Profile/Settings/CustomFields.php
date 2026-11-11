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

namespace App\Livewire\Profile\Settings;

use App\Services\ClientPortal\CustomFieldService;
use Livewire\Attributes\Computed;
use Livewire\Component;

class CustomFields extends Component
{
    public $custom_value1;

    public $custom_value2;

    public $custom_value3;

    public $custom_value4;

    public $saved;

    public function client()
    {
        $client = auth()->guard('contact')->user()->client;
        $client->loadMissing('company');

        return $client;
    }

    public function mount(): void
    {
        $client = $this->client();

        $this->fill([
            'custom_value1' => $client->custom_value1 ?: '',
            'custom_value2' => $client->custom_value2 ?: '',
            'custom_value3' => $client->custom_value3 ?: '',
            'custom_value4' => $client->custom_value4 ?: '',
            'saved' => ctrans('texts.save'),
        ]);
    }

    public function render()
    {
        return render('profile.settings.custom-fields', [
            'custom_field_definitions' => $this->customFieldDefinitions(),
        ]);
    }

    public function submit(): void
    {
        $client = $this->client();
        $fields = $this->customFieldDefinitions();
        $rules = app(CustomFieldService::class)->buildRules($fields);

        if (empty($rules)) {
            return;
        }

        $data = $this->validate($rules);

        $client
            ->fill($data)
            ->save();

        $this->saved = ctrans('texts.saved_at', ['time' => now()->toTimeString()]);
    }

    #[Computed]
    public function customFieldDefinitions(): array
    {
        return app(CustomFieldService::class)->buildFields($this->client()->company);
    }
}
