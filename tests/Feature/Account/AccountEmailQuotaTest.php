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

namespace Tests\Feature\Account;

use Tests\TestCase;
use App\Models\User;
use App\Utils\Ninja;
use App\Models\Account;
use App\Models\Company;
use App\DataMapper\CompanySettings;
use App\Factory\CompanyUserFactory;
use Illuminate\Support\Facades\Cache;
use App\DataMapper\ClientRegistrationFields;

class AccountEmailQuotaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }


    public function testIfQuotaBreached()
    {
        config([
            'ninja.production' => true
        ]);

        $account = Account::factory()->create([
            'hosted_client_count' => 1000,
            'hosted_company_count' => 1000,
            'is_flagged' => false,
            'key' => '123ifyouknowwhatimean',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $account->num_users = 3;
        $account->save();

        $company = Company::factory()->create([
            'account_id' => $account->id,
        ]);

        $hash = \Illuminate\Support\Str::random(32);

        $user = User::factory()->create([
            'account_id' => $account->id,
            'confirmation_code' => $hash,
            'email' =>  "{$hash}@example.com",
        ]);

        $cu = CompanyUserFactory::create($user->id, $company->id, $account->id);
        $cu->is_owner = true;
        $cu->is_admin = true;
        $cu->is_locked = false;
        $cu->save();

        $company->client_registration_fields = ClientRegistrationFields::generate();

        $settings = CompanySettings::defaults();

        $settings->company_logo = 'https://pdf.invoicing.co/favicon-v2.png';
        $settings->website = 'www.invoiceninja.com';
        $settings->address1 = 'Address 1';
        $settings->address2 = 'Address 2';
        $settings->city = 'City';
        $settings->state = 'State';
        $settings->postal_code = 'Postal Code';
        $settings->phone = '555-343-2323';
        $settings->email = 'nothingtoofancy@acme.com';
        $settings->country_id = '840';
        $settings->vat_number = 'vat number';
        $settings->id_number = 'id number';
        $settings->use_credits_payment = 'always';
        $settings->timezone_id = '1';
        $settings->entity_send_time = 0;

        $company->track_inventory = true;
        $company->settings = $settings;
        $company->save();
        /** @vart \App\Models\Account $account */
        $account->default_company_id = $company->id;
        $account->save();


        Cache::put("email_quota".$account->key, 3000);

        $this->assertFalse($account->isPaid());
        $this->assertTrue(Ninja::isNinja());
        $this->assertEquals(20, $account->getDailyEmailLimit());

        $this->assertEquals(3000, Cache::get("email_quota".$account->key));
        $this->assertTrue($account->emailQuotaExceeded());

        Cache::forget("email_quota".'123ifyouknowwhatimean');
    }

    public function testQuotaValidRule()
    {
        $account = Account::factory()->create([
            'hosted_client_count' => 1000,
            'hosted_company_count' => 1000,
            'is_flagged' => false,
            'key' => '123ifyouknowwhatimean',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $account->num_users = 3;
        $account->save();

        Cache::increment("email_quota".$account->key);

        $this->assertFalse($account->emailQuotaExceeded());

        Cache::forget("email_quota".'123ifyouknowwhatimean');
    }

    public function testEmailSentCount()
    {
        $account = Account::factory()->create([
            'hosted_client_count' => 1000,
            'hosted_company_count' => 1000,
            'is_flagged' => false,
            'key' => '123ifyouknowwhatimean',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $account->num_users = 3;
        $account->save();


        Cache::put("email_quota".$account->key, 3000);

        $count = $account->emailsSent();

        $this->assertEquals(3000, $count);

        Cache::forget("email_quota".'123ifyouknowwhatimean');
    }
}
