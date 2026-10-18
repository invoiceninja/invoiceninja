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

use App\Http\Middleware\PasswordProtection;
use App\Http\Requests\SelfUpdate\SelfUpdateRequest;
use App\Models\CompanyUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

class SelfUpdateAuthorizationTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testAdminIsAuthorizedToRequestSelfUpdate(): void
    {
        $this->assertTrue((new SelfUpdateRequest())->authorize());
    }

    public function testNonAdminIsNotAuthorizedToRequestSelfUpdate(): void
    {
        $this->demoteUser();

        $this->assertFalse((new SelfUpdateRequest())->authorize());
    }

    public function testSelfUpdateEndpointRejectsNonAdminBeforeRunningUpdate(): void
    {
        $this->demoteUser();

        $this->withoutMiddleware(PasswordProtection::class)
            ->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])
            ->postJson('/api/v1/self-update')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'This action is unauthorized.');
    }

    private function demoteUser(): void
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
