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
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

final class PeppolLegalEntityState implements ValidationRule
{
    private const ABSENT = 'absent';

    private const PRESENT = 'present';

    /**
     * Indicates whether the rule should be implicit.
     *
     * @var bool
     */
    public $implicit = true;

    private function __construct(
        private readonly Company $company,
        private readonly string $requiredState,
    ) {}

    public static function absent(Company $company): self
    {
        return new self($company, self::ABSENT);
    }

    public static function present(Company $company): self
    {
        return new self($company, self::PRESENT);
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->requiredState === self::ABSENT && $this->company->legal_entity_id !== null) {
            $fail('A Peppol legal entity is already configured for this company.');

            return;
        }

        if ($this->requiredState === self::PRESENT && $this->company->legal_entity_id === null) {
            $fail('No Peppol legal entity is configured for this company.');
        }
    }
}
