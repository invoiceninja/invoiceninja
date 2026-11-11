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
namespace App\Services\EDocument\Gateway\Storecove\Routing;

class StorecoveRoutingRules
{
    public const COL_CLASSIFICATION = 0;

    public const COL_LEGAL_IDENTIFIER = 1;

    public const COL_TAX_IDENTIFIER = 2;

    public const COL_ROUTING_IDENTIFIER = 3;

    public function __construct(private ?array $routingRules = null)
    {
    }

    public function hasCountry(string $country): bool
    {
        return isset($this->all()[$country]);
    }

    public function isClassificationRoutable(string $country, ?string $classification): bool
    {
        return $this->routableRuleFor($country, $classification) !== null;
    }

    /**
     * Returns the effective row used by legacy routing methods.
     *
     * This intentionally preserves the old fallback behavior: a single-row
     * country returns that row regardless of classification, and a multi-row
     * country falls back to its first row when no row matches.
     *
     * @return array<int, mixed>|null
     */
    public function ruleFor(string $country, ?string $classification = 'business'): ?array
    {
        return $this->resolveRule($country, $classification, strict: false);
    }

    /**
     * @return array<int, mixed>|null
     */
    public function routableRuleFor(string $country, ?string $classification = 'business'): ?array
    {
        return $this->resolveRule($country, $classification, strict: true);
    }

    /**
     * @return array{legal_identifier: string, tax_identifier: string}
     */
    public function identifiersFor(string $country, ?string $classification = 'business'): array
    {
        $rule = $this->ruleFor($country, $classification);

        return [
            'legal_identifier' => $this->columnValue($rule, self::COL_LEGAL_IDENTIFIER),
            'tax_identifier' => $this->columnValue($rule, self::COL_TAX_IDENTIFIER),
        ];
    }

    public function legalIdentifierFor(string $country, ?string $classification = 'business'): string
    {
        return $this->columnValue($this->ruleFor($country, $classification), self::COL_LEGAL_IDENTIFIER);
    }

    public function taxIdentifierFor(string $country, ?string $classification = 'business'): string
    {
        return $this->columnValue($this->ruleFor($country, $classification), self::COL_TAX_IDENTIFIER);
    }

    public function routingIdentifierFor(string $country, ?string $classification = 'business'): string
    {
        return $this->columnValue($this->ruleFor($country, $classification), self::COL_ROUTING_IDENTIFIER);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function all(): array
    {
        $rules = $this->routingRules ?? config('einvoice.routing_rules', []);

        return is_array($rules) ? $rules : [];
    }

    /**
     * @return array<int, mixed>|null
     */
    private function resolveRule(string $country, ?string $classification, bool $strict): ?array
    {
        $rules = $this->all()[$country] ?? null;

        if (!is_array($rules) || $rules === []) {
            return null;
        }

        $code = $strict
            ? $this->routabilityCode($classification)
            : $this->classificationCode($classification);

        if (!$this->hasMultipleRows($rules)) {
            return $this->ruleMatches($rules, $code) || !$strict ? $rules : null;
        }

        foreach ($rules as $rule) {
            if (is_array($rule) && $this->ruleMatches($rule, $code)) {
                return $rule;
            }
        }

        return $strict ? null : $rules[0];
    }

    /**
     * @param  array<int, mixed>  $rules
     */
    private function hasMultipleRows(array $rules): bool
    {
        return isset($rules[0]) && is_array($rules[0]);
    }

    /**
     * @param  array<int, mixed>  $rule
     */
    private function ruleMatches(array $rule, string $code): bool
    {
        return stripos((string) ($rule[self::COL_CLASSIFICATION] ?? ''), $code) !== false;
    }

    /**
     * @param  array<int, mixed>|null  $rule
     */
    private function columnValue(?array $rule, int $column): string
    {
        if ($rule === null) {
            return '';
        }

        return !empty($rule[$column]) ? (string) $rule[$column] : '';
    }

    private function classificationCode(?string $classification): string
    {
        return match ($classification ?? 'business') {
            'government' => 'G',
            'individual' => 'C',
            default => 'B',
        };
    }

    private function routabilityCode(?string $classification): string
    {
        return $classification === 'other'
            ? 'O'
            : $this->classificationCode($classification);
    }
}
