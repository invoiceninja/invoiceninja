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

namespace Tests\Unit;

use App\Factory\CompanyUserFactory;
use App\Models\Account;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyToken;
use App\Models\CompanyUser;
use App\Models\Invoice;
use App\Models\RecurringInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 */
class PermissionsTest extends TestCase
{

    public User $user;

    public CompanyUser $cu;

    public Company $company;

    protected function setUp() :void
    {
        parent::setUp();
        $this->faker = \Faker\Factory::create();

        $account = Account::factory()->create([
            'hosted_client_count' => 1000,
            'hosted_company_count' => 1000,
        ]);

        $account->num_users = 3;
        $account->save();

        $this->company = Company::factory()->create([
            'account_id' => $account->id,
        ]);

        $this->user = User::factory()->create([
            'account_id' => $account->id,
            'confirmation_code' => '123',
            'email' =>  $this->faker->safeEmail(),
        ]);

        $this->cu = CompanyUserFactory::create($this->user->id, $this->company->id, $account->id);
        $this->cu->is_owner = false;
        $this->cu->is_admin = false;
        $this->cu->is_locked = false;
        $this->cu->permissions = '["view_client"]';
        $this->cu->save();

        $this->token = \Illuminate\Support\Str::random(64);

        $company_token = new CompanyToken;
        $company_token->user_id = $this->user->id;
        $company_token->company_id = $this->company->id;
        $company_token->account_id = $account->id;
        $company_token->name = 'test token';
        $company_token->token = $this->token;
        $company_token->is_system = true;
        $company_token->save();

    }

    public function testPermissionResolution()
    {
        $class = 'view'.lcfirst(class_basename(\Illuminate\Support\Str::snake(Invoice::class)));

        $this->assertEquals('view_invoice', $class);

        $class = 'view'.lcfirst(class_basename(\Illuminate\Support\Str::snake(Client::class)));
        $this->assertEquals('view_client', $class);


        $class = 'view'.lcfirst(class_basename(\Illuminate\Support\Str::snake(RecurringInvoice::class)));
        $this->assertEquals('view_recurring_invoice', $class);

    }

    public function testExactPermissions()
    {

        $this->assertTrue($this->user->hasExactPermission("view_client"));
        $this->assertFalse($this->user->hasExactPermission("view_all"));

    }

    public function testMissingPermissions()
    {

        $low_cu = CompanyUser::where(['company_id' => $this->company->id, 'user_id' => $this->user->id])->first();
        $low_cu->permissions = '[""]';
        $low_cu->save();

        $this->assertFalse($this->user->hasExactPermission("view_client"));
        $this->assertFalse($this->user->hasExactPermission("view_all"));

    }

    public function testViewAllValidPermissions()
    {

        $low_cu = CompanyUser::where(['company_id' => $this->company->id, 'user_id' => $this->user->id])->first();
        $low_cu->permissions = '["view_all"]';
        $low_cu->save();

        $this->assertTrue($this->user->hasExactPermission("view_client"));
        $this->assertTrue($this->user->hasExactPermission("view_all"));
        
    }

    public function testViewClientPermission()
    {

        $low_cu = CompanyUser::where(['company_id' => $this->company->id, 'user_id' => $this->user->id])->first();
        $low_cu->permissions = '["view_client"]';
        $low_cu->save();

        //this is aberrant
        $this->assertTrue($this->user->hasPermission("viewclient"));

    }

}

