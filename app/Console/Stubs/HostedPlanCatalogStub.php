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

namespace App\Console\Stubs;

use App\Factory\GroupSettingFactory;
use App\Factory\SubscriptionFactory;
use App\Models\Company;
use App\Models\GroupSetting;
use App\Models\Product;
use App\Models\RecurringInvoice;
use App\Models\Subscription;
use App\Models\User;
use App\Repositories\SubscriptionRepository;
use Closure;

class HostedPlanCatalogStub
{
    public const MONTHLY_PLAN_GROUP_ID = 6;

    public const ANNUAL_PLAN_GROUP_ID = 31;

    /**
     * @param  Closure(string): void|null  $log
     */
    public function seed(Company $company, User $user, ?Closure $log = null): void
    {
        $products = config('admin-api.products');

        if (! is_array($products) || $products === []) {
            return;
        }

        $this->ensurePlanGroups($company, $user, $log);

        foreach ($products as $product_key => $product_config) {
            if (! is_string($product_key) || ! is_array($product_config)) {
                continue;
            }

            if ($product_key === 'docuninja_beta_code') {
                continue;
            }

            $this->ensureProduct($company, $user, $product_key, $product_config, $log);
        }

        $this->seedSubscriptions($company, $user, $log);
    }

    /**
     * Create missing hosted-plan subscriptions from admin-api.products.
     * Existing subscription ids are skipped. Products must already exist.
     *
     * @param  Closure(string): void|null  $log
     */
    public function seedSubscriptions(Company $company, User $user, ?Closure $log = null): void
    {
        $products = config('admin-api.products');

        if (! is_array($products) || $products === []) {
            return;
        }

        $webhook_config = [
            'post_purchase_url' => config('ninja.app_url').'/api/admin/plan',
            'post_purchase_rest_method' => 'post',
            'post_purchase_headers' => [config('ninja.ninja_hosted_header') => config('ninja.ninja_hosted_secret')],
        ];

        $subscription_repository = new SubscriptionRepository();

        foreach ($products as $product_key => $product_config) {
            if (! is_string($product_key) || ! is_array($product_config)) {
                continue;
            }

            if ($product_key === 'docuninja_beta_code') {
                continue;
            }

            $subscription_id = (int) ($product_config['subscription_id'] ?? 0);

            if ($subscription_id <= 0) {
                continue;
            }

            $existing = Subscription::query()->find($subscription_id);

            if ($existing) {
                if ((int) $existing->company_id === (int) $company->id) {
                    $this->log($log, "Skipping subscription {$subscription_id} ({$product_key}) - already exists for company");
                } else {
                    $this->log($log, "Skipping subscription {$subscription_id} ({$product_key}) - id already reserved");
                }

                continue;
            }

            $product = $this->findCatalogProduct($company, $product_key);

            if (! $product) {
                $this->log($log, "Skipping subscription {$subscription_id} ({$product_key}) - product not found");

                continue;
            }

            $subscription = SubscriptionFactory::create($company->id, $user->id);
            $subscription->id = $subscription_id;
            $subscription->name = (string) ($product_config['description'] ?? $product_key);
            $subscription->group_id = $this->resolveGroupId($company, $user, $product_config['term'] ?? null, $log);
            $subscription->recurring_product_ids = (string) $product->hashed_id;
            $subscription->webhook_configuration = $webhook_config;
            $subscription->allow_plan_changes = true;
            $subscription->frequency_id = $this->resolveFrequencyId($product_config['term'] ?? null);
            $subscription->max_seats_limit = (int) ($product_config['users'] ?? 1);
            $subscription->per_seat_enabled = ($product_config['plan'] ?? null) === 'enterprise';
            $subscription->save();

            $subscription_repository->save($subscription->toArray(), $subscription);

            $this->log($log, "Created subscription {$subscription_id} ({$product_key})");
        }
    }

    private function findCatalogProduct(Company $company, string $product_key): ?Product
    {
        $product = Product::query()
            ->where('company_id', $company->id)
            ->where('product_key', $product_key)
            ->first();

        if ($product || $product_key !== 'free') {
            return $product;
        }

        return Product::query()
            ->where('company_id', $company->id)
            ->where('product_key', 'free_plan')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $product_config
     */
    private function ensureProduct(Company $company, User $user, string $product_key, array $product_config, ?Closure $log): Product
    {
        $existing = Product::query()
            ->where('company_id', $company->id)
            ->where('product_key', $product_key)
            ->first();

        if ($existing) {
            $this->log($log, "Using existing product {$product_key}");

            return $existing;
        }

        $legacy_product_key = $product_key === 'free' ? 'free_plan' : null;

        if ($legacy_product_key) {
            $legacy = Product::query()
                ->where('company_id', $company->id)
                ->where('product_key', $legacy_product_key)
                ->first();

            if ($legacy) {
                $legacy->product_key = $product_key;
                $legacy->notes = (string) ($product_config['description'] ?? $legacy->notes);
                $legacy->price = (float) ($product_config['price'] ?? $legacy->price);
                $legacy->cost = (float) ($product_config['price'] ?? $legacy->cost);
                $legacy->save();

                $this->log($log, "Renamed legacy product {$legacy_product_key} to {$product_key}");

                return $legacy;
            }
        }

        /** @var \App\Models\Product $product */
        $product = Product::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'product_key' => $product_key,
            'notes' => (string) ($product_config['description'] ?? $product_key),
            'cost' => (float) ($product_config['price'] ?? 0),
            'price' => (float) ($product_config['price'] ?? 0),
            'quantity' => 1,
        ]);

        $this->log($log, "Created product {$product_key}");

        return $product;
    }

    /**
     * @param  Closure(string): void|null  $log
     */
    private function ensurePlanGroups(Company $company, User $user, ?Closure $log): void
    {
        $this->ensurePlanGroup($company, $user, self::MONTHLY_PLAN_GROUP_ID, 'Monthly Plans', $log);
        $this->ensurePlanGroup($company, $user, self::ANNUAL_PLAN_GROUP_ID, 'Annual Plans', $log);
    }

    /**
     * @param  Closure(string): void|null  $log
     */
    private function ensurePlanGroup(Company $company, User $user, int $group_id, string $name, ?Closure $log): int
    {
        $existing = GroupSetting::query()->withTrashed()->find($group_id);

        if ($existing) {
            if ($existing->trashed() && (int) $existing->company_id === (int) $company->id) {
                $existing->restore();
                $this->log($log, "Restored plan group {$group_id}");
            } elseif ((int) $existing->company_id === (int) $company->id) {
                $this->log($log, "Using existing plan group {$group_id}");
            } else {
                $this->log($log, "Plan group {$group_id} belongs to another company");

                return $this->ensureCompanyPlanGroup($company, $user, $name, $log);
            }

            return $existing->id;
        }

        $group = GroupSettingFactory::create($company->id, $user->id);
        $group->id = $group_id;
        $group->name = $name;
        $group->save();

        $this->log($log, "Created plan group {$group_id} ({$name})");

        return $group->id;
    }

    private function ensureCompanyPlanGroup(Company $company, User $user, string $name, ?Closure $log): int
    {
        $existing = GroupSetting::query()
            ->withTrashed()
            ->where('company_id', $company->id)
            ->where('name', $name)
            ->first();

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
                $this->log($log, "Restored company plan group {$existing->id} ({$name})");
            } else {
                $this->log($log, "Using company plan group {$existing->id} ({$name})");
            }

            return $existing->id;
        }

        $group = GroupSettingFactory::create($company->id, $user->id);
        $group->name = $name;
        $group->save();

        $this->log($log, "Created company plan group {$group->id} ({$name})");

        return $group->id;
    }

    private function resolveGroupId(Company $company, User $user, ?string $term, ?Closure $log): ?int
    {
        return match ($term) {
            'month' => $this->ensurePlanGroup($company, $user, self::MONTHLY_PLAN_GROUP_ID, 'Monthly Plans', $log),
            'year' => $this->ensurePlanGroup($company, $user, self::ANNUAL_PLAN_GROUP_ID, 'Annual Plans', $log),
            default => null,
        };
    }

    private function resolveFrequencyId(?string $term): int
    {
        return match ($term) {
            'year' => RecurringInvoice::FREQUENCY_ANNUALLY,
            'month' => RecurringInvoice::FREQUENCY_MONTHLY,
            default => RecurringInvoice::FREQUENCY_MONTHLY,
        };
    }

    /**
     * @param  Closure(string): void|null  $log
     */
    private function log(?Closure $log, string $message): void
    {
        if ($log) {
            $log($message);
        }
    }
}
