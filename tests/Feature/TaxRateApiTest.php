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

use App\Models\TaxRate;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers App\Http\Controllers\TaxRateController
 */
class TaxRateApiTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    public $faker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();
    }

    public function testRemovingDefaultTaxes()
    {
        $t = TaxRate::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'name' => 'nastytax1',
            'rate' => 10,
        ]);

        $settings = $this->company->settings;
        $settings->tax_rate1 = $t->rate;
        $settings->tax_name1 = $t->name;

        $this->company->saveSettings($settings, $this->company);

        $this->company->fresh();

        $this->assertEquals('nastytax1', $this->company->settings->tax_name1);
        $this->assertEquals(10, $this->company->settings->tax_rate1);

        $data = [
            'ids' => [$this->encodePrimaryKey($t->id)],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/tax_rates/bulk?action=archive', $data);

        $response->assertStatus(200);

        $this->company = $this->company->fresh();

        $this->assertEquals('', $this->company->getSetting('tax_name1'));
        $this->assertEquals(0, $this->company->getSetting('tax_rate1'));

    }

    public function testTaxRatesGetFilter()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/tax_rates?filter=gst');

        $response->assertStatus(200);
    }

    public function testTaxRatePost()
    {
        $rate_name = $this->faker->firstName();

        $data = [
            'name' => $rate_name,
            'rate' => 10,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/tax_rates', $data);

        $arr = $response->json();
        $response->assertStatus(200);

        $this->assertEquals($rate_name, $arr['data']['name']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/tax_rates/'.$arr['data']['id'], $data);

        $response->assertStatus(200);

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/tax_rates', $data);

            $arr = $response->json();
        } catch (ValidationException $e) {
            $response->assertStatus(302);
        }

        $this->assertNotEmpty($arr['data']['name']);
    }

    public function testTaxRatePostWithActionStart()
    {
        $data = [
            'name' => $this->faker->firstName(),
            'rate' => rand(1, 20),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/tax_rates', $data);

        $arr = $response->json();
        $response->assertStatus(200);
    }

    public function testTaxRatePut()
    {
        $data = [
            'name' => $this->faker->firstName(),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/tax_rates/'.$this->encodePrimaryKey($this->tax_rate->id), $data);

        $response->assertStatus(200);
    }

    public function testTaxRatesGet()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/tax_rates');

        $response->assertStatus(200);
    }

    public function testTaxRateGet()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/tax_rates/'.$this->encodePrimaryKey($this->tax_rate->id));

        $response->assertStatus(200);
    }

    public function testTaxRateNotArchived()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/tax_rates/'.$this->encodePrimaryKey($this->tax_rate->id));

        $arr = $response->json();

        $this->assertEquals(0, $arr['data']['archived_at']);
    }

    public function testTaxRateArchived()
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->tax_rate->id)],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/tax_rates/bulk?action=archive', $data);

        $arr = $response->json();

        $this->assertNotNull($arr['data'][0]['archived_at']);
    }

    public function testTaxRateRestored()
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->tax_rate->id)],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/tax_rates/bulk?action=restore', $data);

        $arr = $response->json();

        $this->assertEquals(0, $arr['data'][0]['archived_at']);
    }

    public function testTaxRateDeleted()
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->tax_rate->id)],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/tax_rates/bulk?action=delete', $data);

        $arr = $response->json();

        $this->assertTrue($arr['data'][0]['is_deleted']);
    }
}
