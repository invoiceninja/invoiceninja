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

namespace App\PaymentDrivers\Stripe;

use App\Models\Activity;
use App\Models\Client;
use App\Models\ClientGatewayToken;
use App\Repositories\ClientGatewayTokenRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PaymentMethodSyncService
{
    public function __construct(
        private ClientGatewayTokenRepository $clientGatewayTokenRepository
    ) {
    }

    public function removePaymentMethod(
        Collection $companyGateways,
        string $paymentMethodId
    ): void {
        // nlog("removePaymentMethod: {$paymentMethodId}");
        DB::transaction(function () use ($companyGateways, $paymentMethodId): void {
            $tokens = $this->activeTokens($companyGateways)
                ->where('token', $paymentMethodId)
                ->with('client')
                ->lockForUpdate()
                ->get();

            if ($tokens->isNotEmpty()) {
                $this->removeTokens($tokens, false);
            }
        });
    }

    public function removeCustomerPaymentMethods(
        Collection $companyGateways,
        string $customerId
    ): void {
        // nlog("removeCustomerPaymentMethods: {$customerId}");
        DB::transaction(function () use ($companyGateways, $customerId): void {
            $tokens = $this->activeTokens($companyGateways)
                ->where('gateway_customer_reference', $customerId)
                ->with('client')
                ->lockForUpdate()
                ->get();

            if ($tokens->isNotEmpty()) {
                $this->removeTokens($tokens, true);
            }
        });
    }

    public function updatePaymentMethod(
        Collection $companyGateways,
        object $paymentMethod,
        bool $automaticallyUpdated = false
    ): void {
        $paymentMethodId = data_get($paymentMethod, 'id');

        if (!is_string($paymentMethodId) || $paymentMethodId === '') {
            return;
        }

        DB::transaction(function () use ($companyGateways, $paymentMethod, $paymentMethodId, $automaticallyUpdated): void {
            $tokens = $this->activeTokens($companyGateways)
                ->where('token', $paymentMethodId)
                ->with('client')
                ->lockForUpdate()
                ->get();

            if ($tokens->isEmpty()) {
                return;
            }

            foreach ($tokens->groupBy('client_id') as $clientTokens) {
                $firstToken = $clientTokens->first();
                $before = $this->describeToken($firstToken);
                $changed = false;

                foreach ($clientTokens as $token) {
                    $meta = $this->updatedMeta($token, $paymentMethod);

                    if ($meta == $token->meta) {
                        continue;
                    }

                    $token->meta = $meta;
                    $token->save();
                    $changed = true;
                }

                if (!$changed || !$firstToken->client) {
                    continue;
                }

                $after = $this->describeToken($firstToken->fresh());
                $prefix = $automaticallyUpdated
                    ? 'Stripe automatically updated'
                    : 'Stripe updated';

                $this->createActivity(
                    $firstToken->client,
                    Activity::PAYMENT_METHOD_UPDATED,
                    "{$prefix} saved payment method from {$before} to {$after}"
                );
            }
        });
    }

    private function removeTokens(Collection $tokens, bool $customerDeleted): void
    {
        // nlog("removeTokens: {$tokens->count()} tokens");

        $affectedDefaults = $tokens
            ->where('is_default', true)
            ->map(fn (ClientGatewayToken $token): string => "{$token->client_id}:{$token->company_gateway_id}")
            ->unique();

        $defaultClientIds = $tokens
            ->where('is_default', true)
            ->pluck('client_id')
            ->map(fn ($clientId): int => (int) $clientId)
            ->unique();

        $descriptions = $tokens
            ->mapWithKeys(fn (ClientGatewayToken $token): array => [$token->id => $this->describeToken($token)]);

        foreach ($tokens as $token) {
            $this->clientGatewayTokenRepository->delete($token);
        }

        $replacementDescriptions = [];

        foreach ($affectedDefaults as $affectedDefault) {
            [$clientId, $companyGatewayId] = array_map('intval', explode(':', $affectedDefault));

            $replacement = ClientGatewayToken::query()
                ->where('client_id', $clientId)
                ->where('company_gateway_id', $companyGatewayId)
                ->where('is_deleted', false)
                ->withoutTrashed()
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if ($replacement) {
                $replacement->is_default = true;
                $replacement->save();
                $replacementDescriptions[$clientId][] = $this->describeToken($replacement);
            }
        }

        foreach ($tokens->groupBy('client_id') as $clientId => $clientTokens) {
            $client = $clientTokens->first()->client;

            if (!$client) {
                continue;
            }

            $notes = $customerDeleted
                ? $this->customerDeletedNotes(
                    $clientTokens,
                    $replacementDescriptions[(int) $clientId] ?? [],
                    $defaultClientIds->contains((int) $clientId)
                )
                : $this->paymentMethodDetachedNotes(
                    $clientTokens,
                    $descriptions,
                    $replacementDescriptions[(int) $clientId] ?? [],
                    $defaultClientIds->contains((int) $clientId)
                );

            $this->createActivity($client, Activity::PAYMENT_METHOD_REMOVED, $notes);
        }
    }

    private function updatedMeta(ClientGatewayToken $token, object $paymentMethod): object
    {
        // nlog("updatedMeta: {$token->id}");

        $meta = clone ($token->meta ?? new \stdClass());
        $type = data_get($paymentMethod, 'type');

        if ($type === 'card') {
            $meta->brand = (string) data_get($paymentMethod, 'card.brand', $meta->brand ?? '');
            $meta->last4 = (string) data_get($paymentMethod, 'card.last4', $meta->last4 ?? '');
            $meta->exp_month = (string) data_get($paymentMethod, 'card.exp_month', $meta->exp_month ?? '');
            $meta->exp_year = (string) data_get($paymentMethod, 'card.exp_year', $meta->exp_year ?? '');
        } elseif ($type === 'sepa_debit') {
            $bankCode = data_get($paymentMethod, 'sepa_debit.bank_code');

            if ($bankCode) {
                $meta->brand = (string) sprintf('%s (%s)', $bankCode, ctrans('texts.sepa'));
            }

            $meta->last4 = (string) data_get($paymentMethod, 'sepa_debit.last4', $meta->last4 ?? '');
        } elseif ($type === 'us_bank_account') {
            $bankName = data_get($paymentMethod, 'us_bank_account.bank_name');

            if ($bankName) {
                $meta->brand = (string) sprintf('%s (%s)', $bankName, ctrans('texts.ach'));
            }

            $meta->last4 = (string) data_get($paymentMethod, 'us_bank_account.last4', $meta->last4 ?? '');
        } elseif ($type === 'bacs_debit') {
            $meta->brand = (string) data_get($paymentMethod, 'bacs_debit.sort_code', $meta->brand ?? '');
            $meta->last4 = (string) data_get($paymentMethod, 'bacs_debit.last4', $meta->last4 ?? '');
        }

        return $meta;
    }

    private function paymentMethodDetachedNotes(
        Collection $tokens,
        Collection $descriptions,
        array $replacementDescriptions,
        bool $removedDefault
    ): string {
        // nlog("paymentMethodDetachedNotes: {$tokens->first()->id}");
        $description = $descriptions->get($tokens->first()->id, 'a saved payment method');

        if (!$removedDefault) {
            return "Stripe removed saved payment method {$description}.";
        }

        $replacements = array_values(array_unique($replacementDescriptions));

        if (count($replacements) === 1) {
            return "Stripe removed default payment method {$description}. {$replacements[0]} is now the default.";
        }

        if (count($replacements) > 1) {
            return "Stripe removed default payment method {$description}. Replacement default payment methods were assigned.";
        }

        return "Stripe removed default payment method {$description}. No saved payment method remains.";
    }

    private function customerDeletedNotes(
        Collection $tokens,
        array $replacementDescriptions,
        bool $removedDefault
    ): string {
        // nlog("customerDeletedNotes: {$tokens->first()->id}");
        $count = $tokens->pluck('token')->unique()->count();
        $label = $count === 1 ? 'payment method was' : 'payment methods were';
        $notes = "The Stripe customer was deleted. {$count} saved {$label} removed from Invoice Ninja.";

        if (!$removedDefault) {
            return $notes;
        }

        $replacements = array_values(array_unique($replacementDescriptions));

        if (count($replacements) === 1) {
            return "{$notes} {$replacements[0]} is now the default.";
        }

        if (count($replacements) > 1) {
            return "{$notes} Replacement default payment methods were assigned.";
        }

        return "{$notes} No saved payment method remains.";
    }

    private function describeToken(ClientGatewayToken $token): string
    {
        $meta = $token->meta;
        $brand = trim((string) ($meta->brand ?? ''));
        $last4 = trim((string) ($meta->last4 ?? ''));
        $description = trim("{$brand} •••• {$last4}");

        if ($description === '••••') {
            $description = 'saved payment method';
        }

        $expMonth = trim((string) ($meta->exp_month ?? ''));
        $expYear = trim((string) ($meta->exp_year ?? ''));

        if ($expMonth !== '' && $expYear !== '') {
            $description .= " (expires {$expMonth}/{$expYear})";
        }

        return $description;
    }

    private function createActivity(Client $client, int $activityTypeId, string $notes): void
    {
        $activity = new Activity();
        $activity->client_id = $client->id;
        $activity->user_id = $client->user_id;
        $activity->company_id = $client->company_id;
        $activity->account_id = $client->company->account_id;
        $activity->activity_type_id = $activityTypeId;
        $activity->notes = $notes;
        $activity->ip = ' ';
        $activity->is_system = true;
        $activity->save();
    }

    /**
     * @return Builder<ClientGatewayToken>
     */
    private function activeTokens(Collection $companyGateways): Builder
    {
        return ClientGatewayToken::query()
            ->whereIn('company_gateway_id', $companyGateways->pluck('id'))
            ->where('is_deleted', false)
            ->withoutTrashed();
    }
}
