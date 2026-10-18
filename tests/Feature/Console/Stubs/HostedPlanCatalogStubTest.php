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

namespace Tests\Feature\Console\Stubs;

use App\Console\Stubs\HostedPlanCatalogStub;
use App\DataMapper\CompanySettings;
use App\Factory\CompanyUserFactory;
use App\Factory\GroupSettingFactory;
use App\Models\Account;
use App\Models\Company;
use App\Models\GroupSetting;
use App\Models\Product;
use App\Models\RecurringInvoice;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class HostedPlanCatalogStubTest extends TestCase
{
    use DatabaseTransactions;

    private const TEST_SUBSCRIPTION_ID = 9_999_991;

    private Account $account;

    private Company $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);

        if(!config('admin-api.products')) {
            $this->markTestSkipped('Admin API products are not configured.');
        }

        $this->account = Account::factory()->create();
        $this->user = User::factory()->create([
            'account_id' => $this->account->id,
            'email' => Str::random(32).'@gmail.com',
        ]);

        $settings = CompanySettings::defaults();

        $this->company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
        ]);

        CompanyUserFactory::create($this->user->id, $this->company->id, $this->account->id);
    }

    public function test_it_seeds_catalog_products_from_admin_api_config(): void
    {
        (new HostedPlanCatalogStub())->seed($this->company, $this->user);

        foreach ($this->expectedProductKeysFromConfig() as $product_key) {
            $this->assertDatabaseHas('products', [
                'company_id' => $this->company->id,
                'product_key' => $product_key,
            ]);
        }
    }

    public function test_it_creates_subscriptions_for_available_catalog_ids(): void
    {
        config([
            'admin-api.products' => [
                'test_plan' => [
                    'price' => 99,
                    'description' => 'Test Plan - Monthly',
                    'subscription_id' => self::TEST_SUBSCRIPTION_ID,
                    'users' => 1,
                    'plan' => 'pro',
                    'term' => 'month',
                ],
            ],
        ]);

        $this->assertNull(Subscription::query()->find(self::TEST_SUBSCRIPTION_ID));

        (new HostedPlanCatalogStub())->seed($this->company, $this->user);

        $subscription = Subscription::query()->find(self::TEST_SUBSCRIPTION_ID);
        $group = GroupSetting::query()->withTrashed()->find($subscription->group_id);

        $this->assertNotNull($subscription);
        $this->assertNotNull($group);
        $this->assertSame($this->company->id, $subscription->company_id);
        $this->assertSame($this->company->id, $group->company_id);
        $this->assertSame(RecurringInvoice::FREQUENCY_MONTHLY, $subscription->frequency_id);
        $this->assertDatabaseHas('products', [
            'company_id' => $this->company->id,
            'product_key' => 'test_plan',
        ]);
    }

    public function test_it_skips_subscriptions_that_already_exist(): void
    {
        config([
            'admin-api.products' => [
                'test_plan' => [
                    'price' => 99,
                    'description' => 'Test Plan - Monthly',
                    'subscription_id' => self::TEST_SUBSCRIPTION_ID,
                    'users' => 2,
                    'plan' => 'enterprise',
                    'term' => 'year',
                ],
            ],
        ]);

        $stub = new HostedPlanCatalogStub();

        $stub->seed($this->company, $this->user);

        $initial_count = Subscription::query()->count();
        $existing_subscription = Subscription::query()->find(self::TEST_SUBSCRIPTION_ID);

        $this->assertNotNull($existing_subscription);

        $stub->seed($this->company, $this->user);

        $this->assertSame($initial_count, Subscription::query()->count());
        $this->assertSame(
            $existing_subscription->updated_at,
            Subscription::query()->find(self::TEST_SUBSCRIPTION_ID)->updated_at,
        );
    }

    public function test_it_reuses_existing_products_when_seeding_subscriptions(): void
    {
        config([
            'admin-api.products' => [
                'test_plan' => [
                    'price' => 99,
                    'description' => 'Test Plan - Monthly',
                    'subscription_id' => self::TEST_SUBSCRIPTION_ID,
                    'users' => 1,
                    'plan' => 'pro',
                    'term' => 'month',
                ],
            ],
        ]);

        Product::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'product_key' => 'test_plan',
            'notes' => 'Existing Test Plan Product',
            'price' => 99,
            'cost' => 99,
        ]);

        (new HostedPlanCatalogStub())->seedSubscriptions($this->company, $this->user);

        $this->assertSame(1, Product::query()->where('company_id', $this->company->id)->where('product_key', 'test_plan')->count());
        $this->assertNotNull(Subscription::query()->find(self::TEST_SUBSCRIPTION_ID));
    }

    public function test_it_leaves_existing_plan_groups_unchanged(): void
    {
        $monthly_group_id = HostedPlanCatalogStub::MONTHLY_PLAN_GROUP_ID;
        $existing_monthly = GroupSetting::query()->withTrashed()->find($monthly_group_id);

        if (! $existing_monthly) {
            $this->markTestSkipped('Plan group 6 is not present in this database.');
        }

        (new HostedPlanCatalogStub())->seed($this->company, $this->user);

        $monthly_group = GroupSetting::query()->withTrashed()->find($monthly_group_id);

        $this->assertNotNull($monthly_group);
        $this->assertSame($existing_monthly->company_id, $monthly_group->company_id);
    }

    public function test_it_assigns_company_owned_plan_group_when_reserved_id_belongs_to_another_company(): void
    {
        config([
            'admin-api.products' => [
                'test_plan' => [
                    'price' => 99,
                    'description' => 'Test Plan - Monthly',
                    'subscription_id' => self::TEST_SUBSCRIPTION_ID,
                    'users' => 1,
                    'plan' => 'pro',
                    'term' => 'month',
                ],
            ],
        ]);

        $reserved_group_id = HostedPlanCatalogStub::MONTHLY_PLAN_GROUP_ID;
        $reserved_group = GroupSetting::query()->withTrashed()->find($reserved_group_id);

        if (! $reserved_group) {
            $foreign_account = Account::factory()->create();
            $foreign_user = User::factory()->create([
                'account_id' => $foreign_account->id,
                'email' => Str::random(32).'@gmail.com',
            ]);
            $foreign_company = Company::factory()->create([
                'account_id' => $foreign_account->id,
                'settings' => CompanySettings::defaults(),
            ]);

            $reserved_group = GroupSettingFactory::create($foreign_company->id, $foreign_user->id);
            $reserved_group->id = $reserved_group_id;
            $reserved_group->name = 'Monthly Plans';
            $reserved_group->save();
        }

        $this->assertNotSame($this->company->id, $reserved_group->company_id);

        (new HostedPlanCatalogStub())->seed($this->company, $this->user);

        $subscription = Subscription::query()->find(self::TEST_SUBSCRIPTION_ID);
        $group = GroupSetting::query()->withTrashed()->find($subscription->group_id);

        $this->assertNotNull($subscription);
        $this->assertNotNull($group);
        $this->assertSame($this->company->id, $subscription->company_id);
        $this->assertSame($this->company->id, $group->company_id);
        $this->assertNotSame($reserved_group_id, $subscription->group_id);
    }

    /**
     * @return array<int, string>
     */
    private function expectedProductKeysFromConfig(): array
    {
        $products = config('admin-api.products');

        return collect($products)
            ->keys()
            ->filter(fn ($key) => is_string($key) && $key !== 'docuninja_beta_code')
            ->values()
            ->all();
    }
}
