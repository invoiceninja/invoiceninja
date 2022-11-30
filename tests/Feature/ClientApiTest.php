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

use App\DataMapper\ClientSettings;
use App\Factory\ClientFactory;
use App\Http\Requests\Client\StoreClientRequest;
use App\Models\Client;
use App\Models\Country;
use App\Repositories\ClientContactRepository;
use App\Repositories\ClientRepository;
use App\Utils\Number;
use App\Utils\Traits\ClientGroupSettingsSaver;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers App\Http\Controllers\ClientController
 */
class ClientApiTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;
    use ClientGroupSettingsSaver;

    protected function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();
    }

    public function testCsvImportRepositoryPersistance()
    {
        Client::unguard();

        $data = [
          'company_id' => $this->company->id,
          'name' => 'Christian xx',
          'phone' => '',
          'address1' => '',
          'address2' => '',
          'postal_code' => '',
          'city' => '',
          'state' => '',
          'shipping_address1' => '',
          'shipping_address2' => '',
          'shipping_city' => '',
          'shipping_state' => '',
          'shipping_postal_code' => '',
          'public_notes' => '',
          'private_notes' => '',
          'website' => '',
          'vat_number' => '',
          'id_number' => '',
          'custom_value1' => '',
          'custom_value2' => '',
          'custom_value3' => '',
          'custom_value4' => '',
          'balance' => '0',
          'paid_to_date' => '0',
          'credit_balance' => 0,
          'settings' => [
             'entity' => 'App\\Models\\Client',
             'currency_id' => '3',
          ],
          'client_hash' => 'xx',
          'contacts' => 
          [
            [
              'first_name' => '',
              'last_name' => '',
              'email' => '',
              'phone' => '',
              'custom_value1' => '',
              'custom_value2' => '',
              'custom_value3' => '',
              'custom_value4' => '',
            ]
          ],
          'country_id' => NULL,
          'shipping_country_id' => NULL,
          'user_id' => $this->user->id,
        ];

        $repository_name = ClientRepository::class;
        $factory_name = ClientFactory::class;

        $repository = app()->make($repository_name);
        $repository->import_mode = true;

        $c = $repository->save(array_diff_key($data, ['user_id' => false]), ClientFactory::create($this->company->id, $this->user->id));

        Client::reguard();

        $c->refresh();

        $this->assertEquals("3", $c->settings->currency_id);

    }

    public function testClientSettingsSave()
    {
        
        $std = new \stdClass;
        $std->entity = 'App\\Models\\Client';
        $std->currency_id = 3;

        $this->settings = $this->client->settings;

        $this->saveSettings($std, $this->client);

        $this->assertTrue(true);

    }


    public function testClientSettingsSave2()
    {

        $std = new \stdClass;
        $std->entity = 'App\\Models\\Client';
        $std->industry_id = '';
        $std->size_id = '';
        $std->currency_id = 3;

        $this->settings = $this->client->settings;

        $this->saveSettings($std, $this->client);

        $this->assertTrue(true);

    }

    public function testClientStoreValidation()
    {

         auth()->login($this->user, false);
         auth()->user()->setCompany($this->company);

        $data = array (
          'company_id' => $this->company->id,
          'name' => 'Christian xx',
          'phone' => '',
          'address1' => '',
          'address2' => '',
          'postal_code' => '',
          'city' => '',
          'state' => '',
          'shipping_address1' => '',
          'shipping_address2' => '',
          'shipping_city' => '',
          'shipping_state' => '',
          'shipping_postal_code' => '',
          'public_notes' => '',
          'private_notes' => '',
          'website' => '',
          'vat_number' => '',
          'id_number' => '',
          'custom_value1' => '',
          'custom_value2' => '',
          'custom_value3' => '',
          'custom_value4' => '',
          'balance' => '0',
          'paid_to_date' => '0',
          'credit_balance' => 0,
          'settings' => 
          (object) array(
             'entity' => 'App\\Models\\Client',
             'currency_id' => '3',
          ),
          'client_hash' => 'xx',
          'contacts' => 
          array (
            0 => 
            array (
              'first_name' => '',
              'last_name' => '',
              'email' => '',
              'phone' => '',
              'custom_value1' => '',
              'custom_value2' => '',
              'custom_value3' => '',
              'custom_value4' => '',
            ),
          ),
          'country_id' => NULL,
          'shipping_country_id' => NULL,
          'user_id' => $this->user->id,
        );


        $request_name = StoreClientRequest::class;
        $repository_name = ClientRepository::class;
        $factory_name = ClientFactory::class;

        $repository = app()->make($repository_name);
        $repository->import_mode = true;

        $_syn_request_class = new $request_name;
        $_syn_request_class->setContainer(app());
        $_syn_request_class->initialize($data);
        $_syn_request_class->prepareForValidation();

        $validator = Validator::make($_syn_request_class->all(), $_syn_request_class->rules());

        $_syn_request_class->setValidator($validator);

        $this->assertFalse($validator->fails());


    }



    public function testClientImportDataStructure()
    {


        $data = array (
          'company_id' => $this->company->id,
          'name' => 'Christian xx',
          'phone' => '',
          'address1' => '',
          'address2' => '',
          'postal_code' => '',
          'city' => '',
          'state' => '',
          'shipping_address1' => '',
          'shipping_address2' => '',
          'shipping_city' => '',
          'shipping_state' => '',
          'shipping_postal_code' => '',
          'public_notes' => '',
          'private_notes' => '',
          'website' => '',
          'vat_number' => '',
          'id_number' => '',
          'custom_value1' => '',
          'custom_value2' => '',
          'custom_value3' => '',
          'custom_value4' => '',
          'balance' => '0',
          'paid_to_date' => '0',
          'credit_balance' => 0,
          'settings' => 
          (object) array(
             'entity' => 'App\\Models\\Client',
             'currency_id' => '3',
          ),
          'client_hash' => 'xx',
          'contacts' => 
          array (
            0 => 
            array (
              'first_name' => '',
              'last_name' => '',
              'email' => '',
              'phone' => '',
              'custom_value1' => '',
              'custom_value2' => '',
              'custom_value3' => '',
              'custom_value4' => '',
            ),
          ),
          'country_id' => NULL,
          'shipping_country_id' => NULL,
          'user_id' => $this->user->id,
        );

        $crepo = new ClientRepository(new ClientContactRepository());

        $c = $crepo->save(array_diff_key($data, ['user_id' => false]), ClientFactory::create($this->company->id, $this->user->id));
        $c->saveQuietly();

        $this->assertEquals('Christian xx', $c->name);
        $this->assertEquals('3', $c->settings->currency_id);
    }

    public function testClientCsvImport()
    {

        $settings = ClientSettings::defaults();
        $settings->currency_id = "840";

        $data = [
            'name' => $this->faker->firstName(),
            'id_number' => 'Coolio',
            'settings' => (array)$settings,
            'contacts' => [
                [
                  'first_name' => '',
                  'last_name' => '',
                  'email' => '',
                  'phone' => '',
                  'custom_value1' => '',
                  'custom_value2' => '',
                  'custom_value3' => '',
                  'custom_value4' => '',
              ]
            ]
        ];

        $response = false;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/clients/', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            nlog($message);
        }

        $response->assertStatus(200);

        $crepo = new ClientRepository(new ClientContactRepository());

        $c = $crepo->save($data, ClientFactory::create($this->company->id, $this->user->id));
        $c->saveQuietly();


    }




    public function testIllegalPropertiesInClientSettings()
    {
        $settings = [
            'currency_id' => '1',
            'translations' => [
                'email' => 'legal@eagle.com',
            ],
        ];

        $data = [
            'name' => $this->faker->firstName(),
            'settings' => $settings,
        ];

        $response = false;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/clients/', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            nlog($message);
        }

        $response->assertStatus(200);
        $arr = $response->json();

        $this->assertFalse(array_key_exists('translations', $arr['data']['settings']));
    }

    public function testClientLanguageCodeIllegal()
    {
        $data = [
            'name' => $this->faker->firstName(),
            'id_number' => 'Coolio',
            'language_code' => 'not_really_a_VALID-locale',
        ];

        $response = false;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/clients/', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            nlog($message);
        }

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertFalse(array_key_exists('language_id', $arr['data']['settings']));
    }

    public function testClientLanguageCodeValidationTrue()
    {
        $data = [
            'name' => $this->faker->firstName(),
            'id_number' => 'Coolio',
            'language_code' => 'de',
        ];

        $response = false;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/clients/', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            nlog($message);
        }

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('3', $arr['data']['settings']['language_id']);
    }

    public function testClientCountryCodeValidationTrue()
    {
        $data = [
            'name' => $this->faker->firstName(),
            'id_number' => 'Coolio',
            'country_code' => 'AM',
        ];

        $response = false;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/clients/', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            nlog($message);
        }

        $response->assertStatus(200);
    }

    public function testClientNoneValidation()
    {
        $data = [
            'name' => $this->faker->firstName(),
            'number' => '',
        ];

        $response = false;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/clients/', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            nlog($message);
        }

        $response->assertStatus(200);
    }

    public function testClientNullValidation()
    {
        $data = [
            'name' => $this->faker->firstName(),
            'number' => null,
        ];

        $response = false;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/clients/', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            nlog($message);
        }

        $response->assertStatus(200);
    }

    public function testClientCountryCodeValidationTrueIso3()
    {
        $data = [
            'name' => $this->faker->firstName(),
            'id_number' => 'Coolio',
            'country_code' => 'ARM',
        ];

        $response = false;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/clients/', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            nlog($message);
        }

        $response->assertStatus(200);
    }

    public function testClientCountryCodeValidationFalse()
    {
        $data = [
            'name' => $this->faker->firstName(),
            'id_number' => 'Coolio',
            'country_code' => 'AdfdfdfM',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/clients/', $data);

        $response->assertStatus(302);
    }

    public function testClientPost()
    {
        $data = [
            'name' => $this->faker->firstName(),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/clients', $data);

        $response->assertStatus(200);
    }

    public function testDuplicateNumberCatch()
    {
        $data = [
            'name' => $this->faker->firstName(),
            'number' => 'iamaduplicate',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/clients', $data);

        $response->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/clients', $data);

        $response->assertStatus(302);
    }

    public function testClientPut()
    {
        $data = [
            'name' => $this->faker->firstName(),
            'id_number' => 'Coolio',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/clients/'.$this->encodePrimaryKey($this->client->id), $data);

        $response->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/clients/'.$this->encodePrimaryKey($this->client->id), $data);

        $response->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/clients/', $data);

        $response->assertStatus(302);
    }

    public function testClientGet()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/clients/'.$this->encodePrimaryKey($this->client->id));

        $response->assertStatus(200);
    }

    public function testClientNotArchived()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/clients/'.$this->encodePrimaryKey($this->client->id));

        $arr = $response->json();

        $this->assertEquals(0, $arr['data']['archived_at']);
    }

    public function testClientArchived()
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->client->id)],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/clients/bulk?action=archive', $data);

        $arr = $response->json();

        $this->assertNotNull($arr['data'][0]['archived_at']);
    }

    public function testClientRestored()
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->client->id)],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/clients/bulk?action=restore', $data);

        $arr = $response->json();

        $this->assertEquals(0, $arr['data'][0]['archived_at']);
    }

    public function testClientDeleted()
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->client->id)],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/clients/bulk?action=delete', $data);

        $arr = $response->json();

        $this->assertTrue($arr['data'][0]['is_deleted']);
    }

    public function testClientCurrencyCodeValidationTrue()
    {
        $data = [
            'name' => $this->faker->firstName(),
            'id_number' => 'Coolio',
            'currency_code' => 'USD',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/clients/', $data);

        $response->assertStatus(200);
    }

    public function testClientCurrencyCodeValidationFalse()
    {
        $data = [
            'name' => $this->faker->firstName(),
            'id_number' => 'Coolio',
            'currency_code' => 'R',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/clients/', $data);

        $response->assertStatus(302);
    }

    public function testRoundingDecimalsTwo()
    {
        $currency = $this->company;

        $x = Number::formatValueNoTrailingZeroes(0.05, $currency);

        $this->assertEquals(0.05, $x);
    }

    public function testRoundingDecimalsThree()
    {
        $currency = $this->company;

        $x = Number::formatValueNoTrailingZeroes(0.005, $currency);

        $this->assertEquals(0.005, $x);
    }

    public function testRoundingDecimalsFour()
    {
        $currency = $this->company;

        $x = Number::formatValueNoTrailingZeroes(0.0005, $currency);

        $this->assertEquals(0.0005, $x);
    }

    public function testRoundingDecimalsFive()
    {
        $currency = $this->company;

        $x = Number::formatValueNoTrailingZeroes(0.00005, $currency);

        $this->assertEquals(0.00005, $x);
    }

    public function testRoundingDecimalsSix()
    {
        $currency = $this->company;

        $x = Number::formatValueNoTrailingZeroes(0.000005, $currency);

        $this->assertEquals(0.000005, $x);
    }

    public function testRoundingDecimalsSeven()
    {
        $currency = $this->company;

        $x = Number::formatValueNoTrailingZeroes(0.0000005, $currency);

        $this->assertEquals(0.0000005, $x);
    }

    public function testRoundingDecimalsEight()
    {
        $currency = $this->company;

        $x = Number::formatValueNoTrailingZeroes(0.00000005, $currency);

        $this->assertEquals(0.00000005, $x);
    }

    public function testRoundingPositive()
    {
        $currency = $this->company;

        $x = Number::formatValueNoTrailingZeroes(1.5, $currency);
        $this->assertEquals(1.5, $x);

        $x = Number::formatValueNoTrailingZeroes(1.50, $currency);
        $this->assertEquals(1.5, $x);

        $x = Number::formatValueNoTrailingZeroes(1.500, $currency);
        $this->assertEquals(1.5, $x);

        $x = Number::formatValueNoTrailingZeroes(1.50005, $currency);
        $this->assertEquals(1.50005, $x);

        $x = Number::formatValueNoTrailingZeroes(1.50000005, $currency);
        $this->assertEquals(1.50000005, $x);
    }


}
