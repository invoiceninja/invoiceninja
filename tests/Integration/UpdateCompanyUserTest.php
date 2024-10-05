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

use App\Models\CompanyUser;
use App\Models\User;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * 
 *  App\Http\Controllers\CompanyUserController
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

        $company_user = CompanyUser::whereUserId($this->user->id)->whereCompanyId($this->company->id)->first();

        $this->user->company_user = $company_user;

        $settings = [
            'react_settings' => [
                'show_pdf_preview' => true,
                'react_notification_link' => false
            ],
        ];

        $response = null;

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/company_users/'.$this->encodePrimaryKey($this->user->id).'/preferences?include=company_user', $settings);
    

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertTrue($arr['data']['company_user']['react_settings']['show_pdf_preview']);
        $this->assertFalse($arr['data']['company_user']['react_settings']['react_notification_link']);

        $settings = [
            'react_settings' => [
                'show_pdf_preview' => false,
                'react_notification_link' => true
            ],
        ];

        $response = null;

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/company_users/'.$this->encodePrimaryKey($this->user->id).'/preferences?include=company_user', $settings);
    
        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertFalse($arr['data']['company_user']['react_settings']['show_pdf_preview']);
        $this->assertTrue($arr['data']['company_user']['react_settings']['react_notification_link']);

    }


    // public function testUpdatingCompanyUserAsAdmin()
    // {

    //     $settings = new \stdClass();
    //     $settings->invoice = 'ninja';

    //     $company_user = CompanyUser::query()
    //                     ->where('user_id', $this->user->id)
    //                     ->where('company_id', $this->company->id)
    //                     ->first();

    //     $this->assertNotNull($company_user);

    //     $company_user->settings = $settings;

    //     // $this->user->company_user = $company_user;
    //     $this->user->setRelation('company_user', $company_user);
    //     $user = $this->user->toArray();
    //     $user['company_user'] = $company_user->toArray();

    //     $response = null;

    //     $response = $this->withHeaders([
    //         'X-API-SECRET' => config('ninja.api_secret'),
    //         'X-API-TOKEN' => $this->token,
    //     ])->putJson("/api/v1/company_users/{$this->user->hashed_id}", $user);
    
    //     $response->assertStatus(200);

    //     $arr = $response->json();

    //     $this->assertEquals('ninja', $arr['data']['settings']['invoice']);
    // }


}
