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

namespace App\Rules\EInvoice;

use App\Models\Company;
use App\Utils\Ninja;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

final class FranceReportingEnabled implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function __construct(private readonly Company $company) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $enabled = self::normalize($value);

        if (! is_bool($enabled)) {
            return;
        }

        if (! Ninja::isHosted()) {
            return;
        }

        if ((bool) $this->company->getSetting('france_reporting_enabled')) {
            if (! $enabled) {
                $fail('France reporting cannot be disabled.');
            }

            return;
        }

        if (! $enabled) {
            return;
        }

        $eInvoiceType = (string) data_get(
            $this->data,
            'settings.e_invoice_type',
            $this->company->getSetting('e_invoice_type'),
        );

        if ($eInvoiceType !== 'PEPPOL' || is_null($this->company->legal_entity_id)) {
            $fail('France reporting can only be enabled for a company connected to PEPPOL.');
        }
    }

    public static function normalize(mixed $value): mixed
    {
        if (! is_scalar($value)
            || (is_string($value) && trim($value) === '')) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $value;
    }
}
