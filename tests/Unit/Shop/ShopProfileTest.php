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

namespace Tests\Unit\Shop;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers \App\Http\Controllers\Shop\ProfileController
 */
class ShopProfileTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testProfileDisplays()
    {
        $this->company->enable_shop_api = true;
        $this->company->save();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-COMPANY-KEY' => $this->company->company_key,
        ])->get('/api/v1/shop/profile');

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertArrayHasKey('custom_value1', $arr['data']['settings']);
        $this->assertEquals($this->company->company_key, $arr['data']['company_key']);
    }

    public function testProfileSettingsUpdate()
    {

        $this->company->enable_shop_api = true;

        $settings = $this->company->settings;

        $trans = new \stdClass();
        $trans->product = "Service";
        $trans->products = "Services";

        $settings->translations = $trans;
        $this->company->settings = $settings;

        $this->company->save();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-COMPANY-KEY' => $this->company->company_key,
        ])->getJson('/api/v1/shop/profile');


        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals("Service", $arr['data']['settings']['product']);
        $this->assertEquals("Services", $arr['data']['settings']['products']);

    }

    public function testProfileSettingsUpdate2()
    {

        $this->company->enable_shop_api = true;

        $this->company->save();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-COMPANY-KEY' => $this->company->company_key,
        ])->getJson('/api/v1/shop/profile');


        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals("Product", $arr['data']['settings']['product']);
        $this->assertEquals("Products", $arr['data']['settings']['products']);
        $this->assertIsArray($arr['data']['settings']['client_registration_fields']);

    }


}
