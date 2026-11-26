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

    public function testFlutterSettingsArePatchedByPath()
    {
        $response = $this->putCompanyUserFlutterSettings([
            'flutter_settings' => [
                'dashboard' => ['panels' => ['overview|1']],
                'saved_views.view_a' => ['name' => 'Overdue'],
                'welcome' => ['v' => 1],
            ],
        ]);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals(['overview|1'], $arr['data']['company_user']['flutter_settings']['dashboard']['panels']);
        $this->assertEquals('Overdue', $arr['data']['company_user']['flutter_settings']['saved_views']['view_a']['name']);

        // A second device changes one view, adds another and removes a key:
        // everything it does not name is left as it was.
        $response = $this->putCompanyUserFlutterSettings([
            'flutter_settings' => [
                'saved_views.view_a.name' => 'Past due',
                'saved_views.view_b' => ['name' => 'Drafts'],
                'welcome' => null,
            ],
        ]);

        $response->assertStatus(200);

        $flutter_settings = $response->json()['data']['company_user']['flutter_settings'];

        $this->assertEquals(['overview|1'], $flutter_settings['dashboard']['panels']);
        $this->assertEquals('Past due', $flutter_settings['saved_views']['view_a']['name']);
        $this->assertEquals('Drafts', $flutter_settings['saved_views']['view_b']['name']);
        $this->assertArrayNotHasKey('welcome', $flutter_settings);

        $company_user = CompanyUser::whereUserId($this->user->id)
            ->whereCompanyId($this->company->id)
            ->first();

        $this->assertEquals('Drafts', $company_user->flutter_settings->saved_views->view_b->name);
    }

    public function testRemovingTheLastFlutterSettingOfAnObjectRemovesTheObject()
    {
        $this->putCompanyUserFlutterSettings([
            'flutter_settings' => [
                'saved_views.view_a' => ['name' => 'Overdue'],
                'welcome' => 1,
            ],
        ])->assertStatus(200);

        $response = $this->putCompanyUserFlutterSettings([
            'flutter_settings' => ['saved_views.view_a' => null],
        ]);

        $response->assertStatus(200);

        $this->assertArrayNotHasKey('saved_views', $response->json()['data']['company_user']['flutter_settings']);
        $this->assertSame(1, $response->json()['data']['company_user']['flutter_settings']['welcome']);
    }

    public function testSettingAFlutterSettingPathThroughAValueReplacesTheValue()
    {
        $this->putCompanyUserFlutterSettings([
            'flutter_settings' => ['welcome' => 1],
        ])->assertStatus(200);

        $response = $this->putCompanyUserFlutterSettings([
            'flutter_settings' => ['welcome.v' => 2],
        ]);

        $response->assertStatus(200);

        $this->assertSame(['v' => 2], $response->json()['data']['company_user']['flutter_settings']['welcome']);
    }

    public function testAnUnrelatedFlutterSettingsPatchKeepsObjectsAndLists()
    {
        $this->putCompanyUserFlutterSettings([
            'flutter_settings' => [
                'filters' => new \stdClass(),
                'order' => (object) ['0' => 'a', '1' => 'b'],
                'columns' => ['number', 'date'],
            ],
        ])->assertStatus(200);

        $response = $this->putCompanyUserFlutterSettings([
            'flutter_settings' => ['welcome' => 1, 'order.2' => 'c'],
        ]);

        $response->assertStatus(200);

        // Decoded without assoc, so {} and [] stay apart.
        $returned = json_decode($response->getContent())->data->company_user->flutter_settings;
        $stored = json_decode(CompanyUser::whereUserId($this->user->id)
            ->whereCompanyId($this->company->id)
            ->first()
            ->getRawOriginal('flutter_settings'));

        foreach ([$returned, $stored] as $flutter_settings) {
            $this->assertInstanceOf(\stdClass::class, $flutter_settings->filters);
            $this->assertSame([], get_object_vars($flutter_settings->filters));
            $this->assertInstanceOf(\stdClass::class, $flutter_settings->order);
            $this->assertEquals(['0' => 'a', '1' => 'b', '2' => 'c'], get_object_vars($flutter_settings->order));
            $this->assertSame(['number', 'date'], $flutter_settings->columns);
            $this->assertSame(1, $flutter_settings->welcome);
        }
    }

    public function testAnEmptyStringFlutterSettingIsKeptAndOnlyNullRemovesAPath()
    {
        $this->putCompanyUserFlutterSettings([
            'flutter_settings' => ['search' => 'acme', 'view' => ['name' => 'Overdue']],
        ])->assertStatus(200);

        // Through the HTTP middleware, which turns "" into null in the parsed input.
        $response = $this->putCompanyUserFlutterSettings([
            'flutter_settings' => ['search' => '', 'view.name' => ''],
        ]);

        $response->assertStatus(200);

        $flutter_settings = json_decode($response->getContent())->data->company_user->flutter_settings;

        $this->assertSame('', $flutter_settings->search);
        $this->assertSame('', $flutter_settings->view->name);

        $response = $this->putCompanyUserFlutterSettings([
            'flutter_settings' => ['search' => null],
        ]);

        $response->assertStatus(200);

        $flutter_settings = json_decode($response->getContent())->data->company_user->flutter_settings;

        $this->assertObjectNotHasProperty('search', $flutter_settings);
        $this->assertSame('', $flutter_settings->view->name);
    }

    public function testFlutterSettingsMustBeAnObjectOfValidPaths()
    {
        $this->putCompanyUserFlutterSettings([
            'flutter_settings' => ['dark'],
        ])->assertStatus(422)->assertJsonValidationErrors(['flutter_settings']);

        $this->putCompanyUserFlutterSettings([
            'flutter_settings' => ['saved_views..view_a' => 1],
        ])->assertStatus(422)->assertJsonValidationErrors(['flutter_settings']);

        $this->putCompanyUserFlutterSettings([
            'flutter_settings' => new \stdClass(),
        ])->assertStatus(200);
    }

    public function testFlutterSettingsAreNotOverwrittenByACompanyUserSave()
    {
        $this->putCompanyUserFlutterSettings([
            'flutter_settings' => ['saved_views.view_a' => ['name' => 'Overdue']],
        ])->assertStatus(200);

        // Clients save the whole company user from the copy they loaded, which
        // may hold an older flutter_settings (or none).
        $response = $this->putCompanyUser([
            'company_user' => [
                'settings' => ['accent_color' => '#ff0000'],
                'flutter_settings' => new \stdClass(),
            ],
        ]);

        $response->assertStatus(200);

        $this->assertEquals('#ff0000', $response->json()['data']['settings']['accent_color']);
        $this->assertEquals('Overdue', $response->json()['data']['flutter_settings']['saved_views']['view_a']['name']);

        CompanyUser::whereUserId($this->user->id)
            ->whereCompanyId($this->company->id)
            ->update(['is_owner' => false, 'is_admin' => false]);

        $this->putCompanyUser([
            'company_user' => [
                'settings' => ['accent_color' => '#00ff00'],
                'notifications' => ['email' => []],
                'flutter_settings' => new \stdClass(),
            ],
        ])->assertStatus(200);

        $company_user = CompanyUser::whereUserId($this->user->id)
            ->whereCompanyId($this->company->id)
            ->first();

        $this->assertEquals('Overdue', $company_user->flutter_settings->saved_views->view_a->name);
    }

    public function testUpdatingFlutterSettingsRequiresFlutterSettings()
    {
        $response = $this->putCompanyUserFlutterSettings([]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['flutter_settings']);
    }

    public function testCannotUpdateTheFlutterSettingsOfAnotherUser()
    {
        $other_user = User::factory()->create([
            'account_id' => $this->account->id,
            'confirmation_code' => $this->createDbHash(config('database.default')),
            'email' => uniqid('testuser') . '@gmail.com',
        ]);

        CompanyUserFactory::create($other_user->id, $this->company->id, $this->account->id)->save();

        $response = $this->putCompanyUserFlutterSettings([
            'flutter_settings' => ['welcome' => ['v' => 1]],
        ], $other_user);

        $response->assertStatus(401);
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
                'is_owner' => false,
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

    private function putCompanyUserFlutterSettings(array $payload, ?User $user = null)
    {
        $user ??= $this->user;

        return $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/company_users/'.$this->encodePrimaryKey($user->id).'/flutter_settings?include=company_user', $payload);
    }

    private function putCompanyUserPreferences(array $payload)
    {
        return $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/company_users/'.$this->encodePrimaryKey($this->user->id).'/preferences?include=company_user', $payload);
    }
}
