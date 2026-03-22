<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Feature\Vendor;

use Faker\Factory;
use Tests\TestCase;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Account;
use App\Models\Company;
use App\Models\Country;
use App\Models\VendorContact;
use App\Utils\Traits\AppSetup;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class VendorMergeTest extends TestCase
{
    use DatabaseTransactions;
    use AppSetup;

    private $user;

    private $company;

    private $account;

    public $vendor;

    private $primary_contact;

    public $faker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = Factory::create();

        if (Country::count() == 0) {
            Artisan::call('migrate:fresh', ['--seed' => true]);
        }
    }

    public function testSearchingForContacts()
    {
        $account = Account::factory()->create();

        $this->user = User::factory()->create([
            'account_id' => $account->id,
            'email' => $this->faker->safeEmail(),
        ]);

        $this->company = Company::factory()->create([
            'account_id' => $account->id,
        ]);

        $this->vendor = Vendor::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
        ]);

        $this->primary_contact = VendorContact::factory()->create([
            'user_id' => $this->user->id,
            'vendor_id' => $this->vendor->id,
            'company_id' => $this->company->id,
            'is_primary' => 1,
        ]);

        VendorContact::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'vendor_id' => $this->vendor->id,
            'company_id' => $this->company->id,
        ]);

        VendorContact::factory()->create([
            'user_id' => $this->user->id,
            'vendor_id' => $this->vendor->id,
            'company_id' => $this->company->id,
            'email' => 'search@gmail.com',
        ]);

        $this->assertEquals(4, $this->vendor->contacts->count());
        $this->assertTrue($this->vendor->contacts->contains(function ($contact) {
            return $contact->email == 'search@gmail.com';
        }));

        $this->assertFalse($this->vendor->contacts->contains(function ($contact) {
            return $contact->email == 'false@gmail.com';
        }));
    }

    public function testMergeVendors()
    {
        $account = Account::factory()->create();

        $user = User::factory()->create([
            'account_id' => $account->id,
            'email' => $this->faker->safeEmail(),
        ]);

        $company = Company::factory()->create([
            'account_id' => $account->id,
        ]);

        $vendor = Vendor::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
        ]);

        $primary_contact = VendorContact::factory()->create([
            'user_id' => $user->id,
            'vendor_id' => $vendor->id,
            'company_id' => $company->id,
            'is_primary' => 1,
        ]);

        VendorContact::factory()->count(2)->create([
            'user_id' => $user->id,
            'vendor_id' => $vendor->id,
            'company_id' => $company->id,
        ]);

        VendorContact::factory()->create([
            'user_id' => $user->id,
            'vendor_id' => $vendor->id,
            'company_id' => $company->id,
            'email' => 'search@gmail.com',
        ]);
        //4contacts

        $mergable_vendor = Vendor::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
        ]);

        $primary_contact = VendorContact::factory()->create([
            'user_id' => $user->id,
            'vendor_id' => $mergable_vendor->id,
            'company_id' => $company->id,
            'is_primary' => 1,
        ]);

        VendorContact::factory()->count(2)->create([
            'user_id' => $user->id,
            'vendor_id' => $mergable_vendor->id,
            'company_id' => $company->id,
        ]);

        VendorContact::factory()->create([
            'user_id' => $user->id,
            'vendor_id' => $mergable_vendor->id,
            'company_id' => $company->id,
            'email' => 'search@gmail.com',
        ]);
        //4 contacts

        $this->assertEquals(4, $vendor->contacts->count());
        $this->assertEquals(4, $mergable_vendor->contacts->count());

        $vendor = $vendor->service()->merge($mergable_vendor)->save();

        // nlog($vendor->contacts->fresh()->toArray());
        // $this->assertEquals(7, $vendor->fresh()->contacts->count());
    }
}
