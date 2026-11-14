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

namespace App\PaymentDrivers\Stripe\Terminal;

use App\DataProviders\CAProvinces;
use App\DataProviders\USStates;
use App\Exceptions\TerminalException;
use App\Models\GatewayType;
use App\Models\Invoice;
use App\PaymentDrivers\Stripe\CardPresent;

/* Where, and how much, an in-person credit card surcharge may be charged */
class SurchargePolicy
{
    /* Percent of the amount. Credit cards only, and nowhere else */
    public const CAPS = ['US' => 3.0, 'CA' => 2.4];

    public const REGION_CAPS = ['US' => ['CO' => 2.0]];

    public const BANNED_REGIONS = ['US' => ['CT', 'MA', 'ME'], 'CA' => ['QC']];

    public function __construct(private CardPresent $card_present)
    {
    }

    /**
     * @return array{allowed: bool, reason: ?string, cap: ?float}
     */
    public function check(?Invoice $invoice = null, ?array $account = null): array
    {
        $deny = fn ($reason) => ['allowed' => false, 'reason' => $reason, 'cap' => null];

        $company_gateway = $this->card_present->stripe->company_gateway;
        $fees_and_limits = $company_gateway->getFeesAndLimits(GatewayType::CARD_PRESENT);

        if (!$fees_and_limits || (floatval($fees_and_limits->fee_amount ?? 0) <= 0 && floatval($fees_and_limits->fee_percent ?? 0) <= 0)) {
            return $deny('not_configured');
        }

        if ($fees_and_limits->adjust_fee_percent ?? false) {
            return $deny('adjust_fee_percent');
        }

        $country = ($account ?? $this->card_present->account())['country'];
        $company = $this->card_present->company();
        $region = $this->region($country, $company->settings->state ?? '');

        if (!isset(self::CAPS[$country]) || $region == '' || in_array($region, self::BANNED_REGIONS[$country])) {
            return $deny('region');
        }

        if (!isset($company_gateway->settings->terminal_surcharge_acknowledged_at)) {
            return $deny('not_acknowledged');
        }

        if ($invoice && ($invoice->is_proforma || $company->verifactuEnabled() || strlen($invoice->backup->guid ?? '') > 0 || $invoice->isLocked())) {
            return $deny('invoice');
        }

        return ['allowed' => true, 'reason' => null, 'cap' => self::REGION_CAPS[$country][$region] ?? self::CAPS[$country]];
    }

    /* The figures the device shows before the card is read */
    public function quote(Invoice $invoice, float $amount, bool $apply_fee, bool $strict = false): array
    {
        $client = $this->card_present->stripe->client;
        $precision = $client->currency()->precision;
        $policy = $this->check($invoice);
        $reason = $policy['reason'];
        $fee = ['net' => 0, 'gross' => 0];

        if ($apply_fee && $policy['allowed']) {
            $quote = $invoice->service()->quoteGatewayFee($this->card_present->stripe->company_gateway, GatewayType::CARD_PRESENT, $amount);
            $cap = floor(round($amount * $policy['cap'] * pow(10, $precision) / 100, 6)) / pow(10, $precision);

            if ($quote['gross'] > $cap) {
                $reason = 'over_cap';
            } else {
                $fee = ['net' => round($quote['net'], $precision), 'gross' => round($quote['gross'], $precision)];
            }
        }

        if ($apply_fee && $fee['gross'] <= 0 && $strict) {
            throw new TerminalException('fee_not_allowed', 422, ['reason' => $reason]);
        }

        return [
            'amount' => round($amount, $precision),
            'fee_gross' => $fee['gross'],
            'fee_net' => $fee['net'],
            'total' => round($amount + $fee['gross'], $precision),
            'currency_id' => $client->getSetting('currency_id'),
            'surcharge_allowed' => $policy['allowed'] && $reason === null,
            'reason' => $reason,
        ];
    }

    /* The company's state or province as a code; anything unrecognised is never surcharged */
    private function region(string $country, string $state): string
    {
        return match ($country) {
            'US' => USStates::getAbbreviation($state),
            'CA' => CAProvinces::getAbbreviation($state),
            default => '',
        };
    }
}
