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

namespace Tests\Integration;

use App\Factory\CompanyUserFactory;
use App\Models\CompanyToken;
use App\Models\CompanyUser;
use App\Models\User;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * App\Http\Controllers\CompanyUserController
 */
class UpdateCompanyUserTest extends TestCase
{
    use MakesHash;
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testUpdatingCompanyUserReactSettings()
    {
        $settings = [
            'react_settings' => [
                'show_pdf_preview' => true,
                'react_notification_link' => false,
            ],
        ];

        $response = $this->putCompanyUserPreferences($settings);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertTrue($arr['data']['company_user']['react_settings']['show_pdf_preview']);
        $this->assertFalse($arr['data']['company_user']['react_settings']['react_notification_link']);

        $settings = [
            'react_settings' => [
                'show_pdf_preview' => false,
                'react_notification_link' => true,
            ],
        ];

        $response = $this->putCompanyUserPreferences($settings);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertFalse($arr['data']['company_user']['react_settings']['show_pdf_preview']);
        $this->assertTrue($arr['data']['company_user']['react_settings']['react_notification_link']);
    }

    public function testUpdateCompanyUserRequiresCompanyUser()
    {
        $response = $this->putCompanyUser([]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['company_user']);
    }

    public function testUpdatingCompanyUserAsAdmin()
    {
        CompanyUser::whereUserId($this->user->id)
            ->whereCompanyId($this->company->id)
            ->update(['is_admin' => true]);

        $response = $this->putCompanyUser([
            'company_user' => [
                'settings' => [
                    'invoice' => 'ninja',
                ],
            ],
        ]);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('ninja', $arr['data']['settings']['invoice']);

        $company_user = CompanyUser::whereUserId($this->user->id)
            ->whereCompanyId($this->company->id)
            ->first();

        $this->assertEquals('ninja', $company_user->settings->invoice);
    }

    public function testAdminCanUpdateCompanyUserPermissions()
    {
        CompanyUser::whereUserId($this->user->id)
            ->whereCompanyId($this->company->id)
            ->update(['is_admin' => true]);

        $response = $this->putCompanyUser([
            'company_user' => [
                'permissions' => 'create_client,create_invoice',
            ],
        ]);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('create_client,create_invoice', $arr['data']['permissions']);
    }

    public function testUpdatingCompanyUserAsNonAdmin()
    {
        CompanyUser::whereUserId($this->user->id)
            ->whereCompanyId($this->company->id)
            ->update([
                'is_admin' => false,
                'permissions' => 'view_client',
            ]);

        $response = $this->putCompanyUser([
            'company_user' => [
                'settings' => [
                    'invoice' => 'user_setting',
                ],
                'notifications' => [
                    'email' => ['invoice_sent_all'],
                ],
                'react_settings' => [
                    'show_pdf_preview' => true,
                ],
                'permissions' => 'create_client',
            ],
        ]);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('user_setting', $arr['data']['settings']['invoice']);
        $this->assertEquals(['invoice_sent_all'], $arr['data']['notifications']['email']);
        $this->assertTrue($arr['data']['react_settings']['show_pdf_preview']);
        $this->assertEquals('view_client', $arr['data']['permissions']);
    }

    public function testNonAdminCannotUpdateAnotherCompanyUser()
    {
        $other_user = User::factory()->create([
            'account_id' => $this->account->id,
            'confirmation_code' => $this->createDbHash(config('database.default')),
            'email' => uniqid('testuser') . '@gmail.com',
        ]);

        CompanyUserFactory::create($other_user->id, $this->company->id, $this->account->id)->save();

        CompanyUser::whereUserId($this->user->id)
            ->whereCompanyId($this->company->id)
            ->update(['is_admin' => false]);

        $response = $this->putCompanyUser([
            'company_user' => [
                'settings' => [
                    'invoice' => 'blocked',
                ],
            ],
        ], $other_user);

        $response->assertStatus(401);
    }

    private function putCompanyUser(array $payload, ?User $user = null)
    {
        $user ??= $this->user;

        return $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/company_users/'.$this->encodePrimaryKey($user->id), $payload);
    }

    private function putCompanyUserPreferences(array $payload)
    {
        return $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/company_users/'.$this->encodePrimaryKey($this->user->id).'/preferences?include=company_user', $payload);
    }
}
