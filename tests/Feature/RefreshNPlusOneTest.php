<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Feature;

use App\Factory\CompanyUserFactory;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Tests\MockUnitData;
use Tests\TestCase;

class RefreshNPlusOneTest extends TestCase
{
    use DatabaseTransactions;
    use MockUnitData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
        Session::start();
        Model::reguard();
    }

    public function testRefreshBatchesCompanyUserIncludes(): void
    {
        $this->addCompanyUsers(2);

        [$baselineQueries, $baselineUserCount] = $this->measureRefreshQueries();

        $this->addCompanyUsers(6);

        [$expandedQueries, $expandedUserCount] = $this->measureRefreshQueries();

        $this->assertSame($baselineUserCount + 6, $expandedUserCount);

        $perUserLookups = array_filter(
            $expandedQueries,
            fn (array $query): bool => $this->isPerUserCompanyUserLookup($query['query'])
        );

        $this->assertLessThanOrEqual(
            1,
            count($perUserLookups),
            "Refresh queried CompanyUser once per company user instead of once for the root user:\n"
                . implode("\n", array_column($perUserLookups, 'query'))
        );

        $this->assertLessThanOrEqual(
            count($baselineQueries) + 2,
            count($expandedQueries),
            sprintf(
                'Refresh query count grew from %d queries for %d users to %d queries for %d users.',
                count($baselineQueries),
                $baselineUserCount,
                count($expandedQueries),
                $expandedUserCount
            )
        );
    }

    public function testRefreshUsesTheMatchingPreloadedCompanyUser(): void
    {
        $expectedPortalUrls = $this->addCompanyUsers(3);

        [, , $responseUsers] = $this->measureRefreshQueries();
        $responseUsersById = collect($responseUsers)->keyBy('id');

        foreach ($expectedPortalUrls as $userId => $portalUrl) {
            $this->assertSame(
                $portalUrl,
                data_get($responseUsersById->get($userId), 'company_user.ninja_portal_url')
            );
        }
    }

    /**
     * @return array{0: array<int, array<string, mixed>>, 1: int, 2: array<int, array<string, mixed>>}
     */
    private function measureRefreshQueries(): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/refresh?current_company=true&first_load=true&updated_at='.now()->addMinute()->timestamp);

        $response->assertOk();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $users = $response->json('data.0.company.users');

        $this->assertIsArray($users);

        return [$queries, count($users), $users];
    }

    /**
     * @return array<string, string>
     */
    private function addCompanyUsers(int $count): array
    {
        $portalUrls = [];

        for ($i = 0; $i < $count; $i++) {
            $user = User::factory()->create([
                'account_id' => $this->account->id,
                'email' => Str::uuid().'@example.test',
            ]);

            $companyUser = CompanyUserFactory::create(
                $user->id,
                $this->company->id,
                $this->account->id
            );
            $companyUser->is_admin = false;
            $companyUser->is_owner = false;
            $companyUser->is_locked = false;
            $companyUser->ninja_portal_url = 'https://example.test/refresh-user/'.Str::uuid();
            $companyUser->save();

            $portalUrls[$user->hashed_id] = $companyUser->ninja_portal_url;
        }

        return $portalUrls;
    }

    private function isPerUserCompanyUserLookup(string $query): bool
    {
        $normalized = strtolower(str_replace(['`', '"'], '', $query));

        return str_contains($normalized, 'from company_user where company_user.user_id = ?')
            && str_contains($normalized, 'company_id = ?')
            && str_contains($normalized, 'limit 1')
            && ! str_contains($normalized, 'deleted_at');
    }
}
