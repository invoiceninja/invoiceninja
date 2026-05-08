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

namespace Tests\Feature\VendorPortal;

use App\Livewire\PurchaseOrdersTable;
use App\Models\Account;
use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorContact;
use App\Utils\Traits\AppSetup;
use Faker\Factory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class PurchaseOrdersTest extends TestCase
{
    use DatabaseTransactions;
    use AppSetup;

    public $faker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = Factory::create();
    }

    public function testPurchaseOrderTableFilters()
    {
        $account = Account::factory()->create();

        $user = User::factory()->create(
            ['account_id' => $account->id, 'email' => $this->faker->safeEmail()]
        );

        $company = Company::factory()->create(['account_id' => $account->id]);
        $company->settings->language_id = '1';
        $company->save();

        $vendor = Vendor::factory()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'currency_id' => 1,
        ]);

        VendorContact::factory()->create([
            'user_id' => $user->id,
            'vendor_id' => $vendor->id,
            'company_id' => $company->id,
            'is_primary' => 1,
            'send_email' => true,
        ]);

        $sent = PurchaseOrder::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'vendor_id' => $vendor->id,
            'number' => 'po-testing-number-01',
            'status_id' => PurchaseOrder::STATUS_SENT,
        ]);

        $accepted = PurchaseOrder::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'vendor_id' => $vendor->id,
            'number' => 'po-testing-number-02',
            'status_id' => PurchaseOrder::STATUS_ACCEPTED,
        ]);

        $sent->load('vendor');
        $accepted->load('vendor');

        $this->actingAs($vendor->contacts()->first(), 'vendor');

        Livewire::test(PurchaseOrdersTable::class, ['company_id' => $company->id, 'db' => $company->db])
            ->assertSee($sent->number)
            ->assertSee($accepted->number);

        Livewire::test(PurchaseOrdersTable::class, ['company_id' => $company->id, 'db' => $company->db])
            ->set('status', ['accepted'])
            ->assertSee($accepted->number)
            ->assertDontSee($sent->number);

        $account->delete();
    }

    public function testSelectionResetsOnPagination()
    {
        $account = Account::factory()->create();

        $user = User::factory()->create(
            ['account_id' => $account->id, 'email' => $this->faker->safeEmail()]
        );

        $company = Company::factory()->create(['account_id' => $account->id]);
        $company->settings->language_id = '1';
        $company->save();

        $vendor = Vendor::factory()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'currency_id' => 1,
        ]);

        VendorContact::factory()->create([
            'user_id' => $user->id,
            'vendor_id' => $vendor->id,
            'company_id' => $company->id,
            'is_primary' => 1,
            'send_email' => true,
        ]);

        PurchaseOrder::factory()->count(15)->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'vendor_id' => $vendor->id,
            'status_id' => PurchaseOrder::STATUS_SENT,
        ]);

        $this->actingAs($vendor->contacts()->first(), 'vendor');

        Livewire::test(PurchaseOrdersTable::class, ['company_id' => $company->id, 'db' => $company->db])
            ->set('select_all', true)
            ->assertSet('select_all', true)
            ->tap(fn ($c) => $this->assertCount(10, $c->get('selected')))
            ->call('setPage', 2)
            ->assertSet('selected', [])
            ->assertSet('select_all', false);

        Livewire::test(PurchaseOrdersTable::class, ['company_id' => $company->id, 'db' => $company->db])
            ->set('selected', ['abc', 'def'])
            ->set('per_page', 15)
            ->assertSet('selected', []);

        Livewire::test(PurchaseOrdersTable::class, ['company_id' => $company->id, 'db' => $company->db])
            ->set('selected', ['abc'])
            ->call('sortBy', 'number')
            ->assertSet('selected', [])
            ->assertSet('select_all', false);

        $account->delete();
    }
}
