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

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\SystemLog;
use App\Models\User;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 *
 *  App\Http\Controllers\SystemLogController
 */
class SystemLogApiTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }


    public function testFilters()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/system_logs?type_id=3')
        ->assertStatus(200);
        ;
    }

    public function testSystemLogRoutes()
    {
        $sl = [
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->client->user_id,
            'log' => 'thelog',
            'category_id' => 1,
            'event_id' => 1,
            'type_id' => 1,
        ];

        SystemLog::create($sl);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/system_logs');

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertTrue(count($arr['data']) >= 1);

        $hashed_id = $arr['data'][0]['id'];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/system_logs/'.$hashed_id);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals($hashed_id, $arr['data']['id']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/system_logs/'.$hashed_id, $sl)->assertStatus(400);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->delete('/api/v1/system_logs/'.$hashed_id)->assertStatus(400);
    }

    public function testStoreRouteFails()
    {
        $sl = [
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->client->user_id,
            'log' => 'thelog',
            'category_id' => 1,
            'event_id' => 1,
            'type_id' => 1,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/system_logs', $sl)->assertStatus(400);
    }

    public function testCreateRouteFails()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/system_logs/create')->assertStatus(400);
    }

    public function testCategoryIdAndEventIdFilters()
    {
        $matchA = SystemLog::create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'log' => 'cat_match',
            'category_id' => 5,
            'event_id' => 7,
            'type_id' => 1,
        ]);

        $matchB = SystemLog::create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'log' => 'event_match',
            'category_id' => 9,
            'event_id' => 7,
            'type_id' => 1,
        ]);

        $other = SystemLog::create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'log' => 'other',
            'category_id' => 1,
            'event_id' => 1,
            'type_id' => 1,
        ]);

        $response = $this->withHeaders(['X-API-TOKEN' => $this->token])
            ->get('/api/v1/system_logs?category_id=5&per_page=500')
            ->assertStatus(200);
        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($this->encodePrimaryKey($matchA->id), $ids);
        $this->assertNotContains($this->encodePrimaryKey($matchB->id), $ids);
        $this->assertNotContains($this->encodePrimaryKey($other->id), $ids);

        $response = $this->withHeaders(['X-API-TOKEN' => $this->token])
            ->get('/api/v1/system_logs?event_id=7&per_page=500')
            ->assertStatus(200);
        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($this->encodePrimaryKey($matchA->id), $ids);
        $this->assertContains($this->encodePrimaryKey($matchB->id), $ids);
        $this->assertNotContains($this->encodePrimaryKey($other->id), $ids);
    }

    public function testAdminCanShowSystemLogInTheirCompany(): void
    {
        $system_log = $this->createSystemLog($this->company->id, $this->user->id);

        $this->withHeaders($this->apiHeaders())
            ->getJson('/api/v1/system_logs/'.$this->encodePrimaryKey($system_log->id))
            ->assertOk()
            ->assertJsonPath('data.id', $this->encodePrimaryKey($system_log->id));
    }

    public function testNonAdminCannotShowSystemLog(): void
    {
        $system_log = $this->createSystemLog($this->company->id, $this->user->id);
        $this->demoteCurrentUser();

        $this->withHeaders($this->apiHeaders())
            ->getJson('/api/v1/system_logs/'.$this->encodePrimaryKey($system_log->id))
            ->assertUnauthorized()
            ->assertJsonPath('message', 'This action is unauthorized.');
    }

    public function testCannotShowSystemLogFromAnotherCompany(): void
    {
        $foreign_account = Account::factory()->create();
        $foreign_company = Company::factory()->create([
            'account_id' => $foreign_account->id,
        ]);
        $foreign_user = User::factory()->create([
            'account_id' => $foreign_account->id,
            'email' => uniqid('foreign').'@example.test',
        ]);
        $system_log = $this->createSystemLog($foreign_company->id, $foreign_user->id);

        $this->withHeaders($this->apiHeaders())
            ->getJson('/api/v1/system_logs/'.$this->encodePrimaryKey($system_log->id))
            ->assertStatus(400);
    }

    /** @return array<string, string> */
    private function apiHeaders(): array
    {
        return [
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ];
    }

    private function createSystemLog(int $company_id, int $user_id): SystemLog
    {
        return SystemLog::create([
            'client_id' => $this->client->id,
            'company_id' => $company_id,
            'user_id' => $user_id,
            'log' => 'thelog',
            'category_id' => 1,
            'event_id' => 1,
            'type_id' => 1,
        ]);
    }

    private function demoteCurrentUser(): void
    {
        CompanyUser::query()
            ->where('user_id', $this->user->id)
            ->where('company_id', $this->company->id)
            ->update([
                'is_owner' => false,
                'is_admin' => false,
            ]);
    }
}
