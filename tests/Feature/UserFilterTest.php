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

namespace Tests\Feature;

use App\Models\CompanyToken;
use App\Models\CompanyUser;
use App\Models\User;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * Coverage for filter methods on App\Filters\UserFilters.
 */
class UserFilterTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        Session::start();
        Model::reguard();

        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function attachUser(User $user, bool $isOwner = false, bool $isDeleted = false): void
    {
        $cu = new CompanyUser();
        $cu->user_id = $user->id;
        $cu->company_id = $this->company->id;
        $cu->account_id = $this->account->id;
        $cu->is_owner = $isOwner;
        $cu->is_admin = false;
        $cu->is_locked = false;
        $cu->permissions = '';
        $cu->notifications = (array) \App\DataMapper\CompanySettings::notificationAdminDefaults();
        $cu->settings = null;
        if ($isDeleted) {
            $cu->deleted_at = now();
        }
        $cu->save();
    }

    public function testHideOwnerUsersFilter()
    {
        $nonOwner = User::factory()->create([
            'account_id' => $this->account->id,
            'email' => 'nonowner_' . uniqid() . '@example.test',
        ]);
        $this->attachUser($nonOwner, isOwner: false);

        $extraOwner = User::factory()->create([
            'account_id' => $this->account->id,
            'email' => 'owner_' . uniqid() . '@example.test',
        ]);
        $this->attachUser($extraOwner, isOwner: true);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/users?hideOwnerUsers=true&per_page=500')->assertStatus(200);

        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($nonOwner->hashed_id, $ids);
        $this->assertNotContains($extraOwner->hashed_id, $ids);
    }

    public function testHideRemovedUsersFilter()
    {
        $active = User::factory()->create([
            'account_id' => $this->account->id,
            'email' => 'active_' . uniqid() . '@example.test',
        ]);
        $this->attachUser($active, isOwner: false, isDeleted: false);

        $removed = User::factory()->create([
            'account_id' => $this->account->id,
            'email' => 'removed_' . uniqid() . '@example.test',
        ]);
        $this->attachUser($removed, isOwner: false, isDeleted: true);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/users?hideRemovedUsers=true&per_page=500')->assertStatus(200);

        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($active->hashed_id, $ids);
        $this->assertNotContains($removed->hashed_id, $ids);
    }

    public function testSendingUsersFilter()
    {
        $hasOauth = User::factory()->create([
            'account_id' => $this->account->id,
            'email' => 'oauth_' . uniqid() . '@example.test',
            'oauth_user_refresh_token' => 'refresh_' . uniqid(),
        ]);
        $this->attachUser($hasOauth, isOwner: false);

        $noOauth = User::factory()->create([
            'account_id' => $this->account->id,
            'email' => 'plain_' . uniqid() . '@example.test',
            'oauth_user_refresh_token' => null,
        ]);
        $this->attachUser($noOauth, isOwner: false);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/users?sending_users=true&per_page=500')->assertStatus(200);

        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($hasOauth->hashed_id, $ids);
        $this->assertNotContains($noOauth->hashed_id, $ids);
    }

    public function testShowAccountUsersFilter()
    {
        $sameAccountOtherCompany = User::factory()->create([
            'account_id' => $this->account->id,
            'email' => 'sameacct_' . uniqid() . '@example.test',
        ]);

        $otherAccount = \App\Models\Account::factory()->create();
        $otherAccountUser = User::factory()->create([
            'account_id' => $otherAccount->id,
            'email' => 'otheracct_' . uniqid() . '@example.test',
        ]);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/users?showAccountUsers=true&per_page=500')->assertStatus(200);

        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($sameAccountOtherCompany->hashed_id, $ids);
        $this->assertNotContains($otherAccountUser->hashed_id, $ids);
    }
}
