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

use App\Utils\Helpers;
use Illuminate\Validation\Rule;
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
            'custom_field_definitions' => $this->customFieldDefinitions,
        ]);
    }

    public function submit(): void
    {
        $client = $this->client();
        $fields = $this->customFieldDefinitions;
        $rules = $this->buildRules($fields);

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
        return $this->buildFields($this->client()->company);
    }

    private function buildRules(array $fields): array
    {
        return collect($fields)
            ->mapWithKeys(fn ($field) => [$field['key'] => $this->rulesForField($field)])
            ->all();
    }

    private function rulesForField(array $field): array
    {
        $base = ($field['required'] ?? false)
            ? ['bail', 'required']
            : ['sometimes', 'nullable'];

        return match ($field['type']) {
            'date' => array_merge($base, ['date']),
            'dropdown' => array_merge($base, [Rule::in($field['options'])]),
            'switch' => array_merge($base, [Rule::in(['yes', 'no', ''])]),
            default => array_merge($base, ['string', 'max:1000']),
        };
    }

    private function buildFields($company): array
    {
        if ($company === null) {
            return [];
        }

        $helper = app(Helpers::class);
        $fields = [];

        $registration_fields = collect($company->client_registration_fields ?? [])->keyBy('key');

        foreach (['custom_value1', 'custom_value2', 'custom_value3', 'custom_value4'] as $key) {
            $field = $registration_fields->get($key);

            if ($field === null) {
                continue;
            }

            $isShown = ($field['required'] ?? false) || ($field['visible'] ?? true);

            if (! $isShown) {
                continue;
            }

            $definition_key = str_replace('custom_value', 'client', $key);
            $definition = data_get($company->custom_fields, $definition_key, '');

            $parsed = $this->parseCustomFieldDefinition($definition);

            $fields[] = [
                'key' => $key,
                'label' => $helper->makeCustomField($company->custom_fields, $definition_key) ?: $parsed['label'],
                'required' => $field['required'] ?? false,
                'type' => $parsed['type'],
                'options' => $parsed['options'],
            ];
        }

        return $fields;
    }

    private function parseCustomFieldDefinition(string $definition): array
    {
        $parts = explode('|', $definition, 2);
        $label = $parts[0] ?? '';

        if (count($parts) === 1) {
            return ['label' => $label, 'type' => 'textarea', 'options' => []];
        }

        $type_part = $parts[1];

        return match ($type_part) {
            'date' => ['label' => $label, 'type' => 'date', 'options' => []],
            'single_line_text' => ['label' => $label, 'type' => 'text', 'options' => []],
            'switch' => ['label' => $label, 'type' => 'switch', 'options' => []],
            default => ['label' => $label, 'type' => 'dropdown', 'options' => array_map('trim', explode(',', $type_part))],
        };
    }
}
