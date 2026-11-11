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

namespace App\Services\ClientPortal;

use App\Models\Company;
use App\Utils\Helpers;
use Illuminate\Validation\Rule;

class CustomFieldService
{
    /**
     * Build a structured list of custom field definitions for a company's client fields.
     *
     * Each entry contains: key, label, required, type, options.
     * Only fields that are required or visible in client_registration_fields are included.
     */
    public function buildFields(?Company $company): array
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

    /**
     * Build a map of validation rules keyed by field key.
     *
     * @param  array<int, array{key: string, required: bool, type: string, options: array<string>}>  $fields
     * @return array<string, array<mixed>>
     */
    public function buildRules(array $fields): array
    {
        return collect($fields)
            ->mapWithKeys(fn ($field) => [$field['key'] => $this->rulesForField($field)])
            ->all();
    }

    /**
     * Return validation rules for a single field definition.
     *
     * @param  array{key: string, required: bool, type: string, options: array<string>}  $field
     * @return array<mixed>
     */
    public function rulesForField(array $field): array
    {
        $base = ($field['required'])
            ? ['bail', 'required']
            : ['sometimes', 'nullable'];

        return match ($field['type']) {
            'date' => array_merge($base, ['date']),
            'dropdown' => array_merge($base, [Rule::in($field['options'])]),
            'switch' => array_merge($base, [Rule::in(['yes', 'no', ''])]),
            default => array_merge($base, ['string', 'max:1000']),
        };
    }

    /**
     * Parse a custom field definition string of the form "Label|type".
     *
     * Returns ['label', 'type', 'options'] where type is one of:
     * 'date', 'text', 'switch', 'textarea', 'dropdown'.
     *
     * @return array{label: string, type: string, options: array<string>}
     */
    public function parseCustomFieldDefinition(string $definition): array
    {
        $parts = explode('|', $definition, 2);
        $label = $parts[0];

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
