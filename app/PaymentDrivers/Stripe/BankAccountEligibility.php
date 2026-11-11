<?php

namespace App\PaymentDrivers\Stripe;

use Stripe\Charge;
use Stripe\Customer;
use Stripe\Mandate;
use Stripe\PaymentMethod;
use Stripe\SetupIntent;

/** Customer/account-scoped evidence cache, discarded after each import. */
final class BankAccountEligibility
{
    private ?array $setupMandates = null;
    private ?array $paymentMandates = null;
    private array $mandates = [];

    public function __construct(private Customer $customer, private array $requestOptions)
    {
    }

    public function resolve(PaymentMethod $method): ?Mandate
    {
        if (
            $method->type !== 'us_bank_account'
            || $this->id($method->customer ?? null) !== $this->customer->id
            || !is_bool($method->livemode)
            || $method->livemode !== $this->customer->livemode
            || ($method->us_bank_account ?? null) === null
            || data_get($method, 'us_bank_account.status_details.blocked') !== null
        ) {
            return null;
        }

        foreach ($this->setupMandates()[$method->id] ?? [] as $mandateId) {
            if ($mandate = $this->activeMandate($mandateId, $method)) {
                return $mandate;
            }
        }

        foreach ($this->paymentMandates()[$method->id] ?? [] as $mandateId) {
            if ($mandate = $this->activeMandate($mandateId, $method)) {
                return $mandate;
            }
        }

        return null;
    }

    private function setupMandates(): array
    {
        if ($this->setupMandates !== null) {
            return $this->setupMandates;
        }

        $candidates = [];
        $intents = SetupIntent::all(['customer' => $this->customer->id, 'limit' => 100], $this->requestOptions);

        foreach ($intents->autoPagingIterator() as $intent) {
            if (
                $intent->status !== 'succeeded'
                || $intent->usage !== 'off_session'
                || $this->id($intent->customer ?? null) !== $this->customer->id
                || $intent->livemode !== $this->customer->livemode
                || !in_array('us_bank_account', $intent->payment_method_types ?? [], true)
            ) {
                continue;
            }

            $methodId = $this->id($intent->payment_method ?? null);
            $mandateId = $this->id($intent->mandate ?? null);
            if ($methodId && $mandateId) {
                $candidates[$methodId][$mandateId] = $mandateId;
            }
        }

        return $this->setupMandates = $candidates;
    }

    private function paymentMandates(): array
    {
        if ($this->paymentMandates !== null) {
            return $this->paymentMandates;
        }

        $candidates = [];
        $charges = Charge::all(['customer' => $this->customer->id, 'limit' => 100], $this->requestOptions);

        foreach ($charges->autoPagingIterator() as $charge) {
            if (
                $charge->status !== 'succeeded'
                || $charge->paid !== true
                || $this->id($charge->customer ?? null) !== $this->customer->id
                || $charge->livemode !== $this->customer->livemode
                || data_get($charge, 'payment_method_details.type') !== 'us_bank_account'
            ) {
                continue;
            }

            $methodId = $this->id($charge->payment_method ?? null);
            $mandateId = $this->id(data_get($charge, 'payment_method_details.us_bank_account.mandate'));
            if ($methodId && $mandateId) {
                $candidates[$methodId][$mandateId] = $mandateId;
            }
        }

        return $this->paymentMandates = $candidates;
    }

    private function activeMandate(string $mandateId, PaymentMethod $method): ?Mandate
    {
        $mandate = $this->mandates[$mandateId] ??= Mandate::retrieve($mandateId, $this->requestOptions);
        $onBehalfOf = $this->id($mandate->on_behalf_of ?? null);

        if (
            $mandate->status !== 'active'
            || $mandate->type !== 'multi_use'
            || $this->id($mandate->payment_method ?? null) !== $method->id
            || $mandate->livemode !== $method->livemode
            || ($onBehalfOf !== null && $onBehalfOf !== ($this->requestOptions['stripe_account'] ?? null))
        ) {
            return null;
        }

        return $mandate;
    }

    private function id(mixed $value): ?string
    {
        if (is_string($value)) {
            return $value !== '' ? $value : null;
        }

        return is_object($value) ? ($value->id ?? null) : null;
    }
}
