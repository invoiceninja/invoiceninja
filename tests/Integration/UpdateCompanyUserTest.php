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
 * @test
 * @covers App\Http\Controllers\CompanyUserController
 */
class UpdateCompanyUserTest extends TestCase
{
    use MakesHash;
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testUpdatingCompanyUserAsAdmin()
    {
        // User::unguard();

        $settings = new \stdClass;
        $settings->invoice = 'ninja';

        $company_user = CompanyUser::whereUserId($this->user->id)->whereCompanyId($this->company->id)->first();
        $company_user->settings = $settings;

        $this->user->company_user = $company_user;

        $user['company_user'] = $company_user->toArray();

        $response = null;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->put('/api/v1/company_users/'.$this->encodePrimaryKey($this->user->id), $user);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            $this->assertNotNull($message);
        }

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('ninja', $arr['data']['settings']['invoice']);
    }
}
