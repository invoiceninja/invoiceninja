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

namespace Tests\Feature\CompanyUser;

use App\DataMapper\CompanySettings;
use App\Factory\CompanyUserFactory;
use App\Models\Account;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @see App\Models\CompanyUser
 * @see App\Models\User::companies()
 */
class CompanyUserAccountIdOnCreateTest extends TestCase
{
    use DatabaseTransactions;
    use MakesHash;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testBelongsToManyAttachSetsAccountIdFromCompanyWhenPivotOmitsAccountId(): void
    {
        $company = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $user = User::factory()->create([
            'account_id' => $this->account->id,
            'confirmation_code' => $this->createDbHash(config('database.default')),
            'email' => uniqid('attach').'@gmail.com',
        ]);

        $user->companies()->attach($company->id, [
            'is_owner' => 1,
            'account_id' => $this->account->id,
            'is_admin' => 1,
            'is_locked' => 0,
            'notifications' => CompanySettings::notificationDefaults(),
            'settings' => null,
        ]);

        $company_user = CompanyUser::query()
            ->whereUserId($user->id)
            ->whereCompanyId($company->id)
            ->firstOrFail();

        $this->assertSame($company->account_id, $company_user->account_id);
        $this->assertSame($this->account->id, $company_user->account_id);
    }

    public function testBelongsToManyAttachDoesNotPersistForeignAccountIdInPivotAttributes(): void
    {
        $foreign_account = Account::factory()->create([
            'hosted_client_count' => 1000,
            'hosted_company_count' => 1000,
        ]);

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $user = User::factory()->create([
            'account_id' => $this->account->id,
            'confirmation_code' => $this->createDbHash(config('database.default')),
            'email' => uniqid('attach_foreign').'@gmail.com',
        ]);

        $user->companies()->attach($company->id, [
            'account_id' => $this->account->id,
            'is_owner' => 0,
            'is_admin' => 1,
            'is_locked' => 0,
            'notifications' => CompanySettings::notificationDefaults(),
            'settings' => null,
        ]);

        $company_user = CompanyUser::query()
            ->whereUserId($user->id)
            ->whereCompanyId($company->id)
            ->firstOrFail();

        $this->assertSame($company->account_id, $company_user->account_id);
        $this->assertNotSame($foreign_account->id, $company_user->account_id);
    }

    public function testCompanyUserFactoryPersistsExplicitAccountId(): void
    {
        $company = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $user = User::factory()->create([
            'account_id' => $this->account->id,
            'confirmation_code' => $this->createDbHash(config('database.default')),
            'email' => uniqid('factory').'@gmail.com',
        ]);

        CompanyUserFactory::create($user->id, $company->id, $this->account->id)
            ->forceFill(['is_owner' => false, 'is_admin' => true])
            ->save();

        $company_user = CompanyUser::query()
            ->whereUserId($user->id)
            ->whereCompanyId($company->id)
            ->firstOrFail();

        $this->assertSame($this->account->id, $company_user->account_id);
    }

    public function testCreatingHookSetsAccountIdWhenOnlyCompanyAndUserArePresent(): void
    {
        $company = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $user = User::factory()->create([
            'account_id' => $this->account->id,
            'confirmation_code' => $this->createDbHash(config('database.default')),
            'email' => uniqid('direct').'@gmail.com',
        ]);

        $company_user = new CompanyUser();
        $company_user->user_id = $user->id;
        $company_user->account_id = $this->account->id;
        $company_user->company_id = $company->id;
        $company_user->notifications = CompanySettings::notificationDefaults();
        $company_user->save();

        $this->assertSame($company->account_id, $company_user->account_id);

        $persisted = CompanyUser::query()
            ->whereUserId($user->id)
            ->whereCompanyId($company->id)
            ->firstOrFail();

        $this->assertSame($company->account_id, $persisted->account_id);
    }
}
