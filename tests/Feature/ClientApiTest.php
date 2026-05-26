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
use App\Factory\CompanyUserFactory;
use App\Http\Requests\Client\StoreClientRequest;
use App\Models\Account;
use App\Models\Client;
use App\Models\Company;
use App\Models\ClientContact;
use App\Models\CompanyToken;
use App\Models\GroupSetting;
use App\Models\User;
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

class ClientApiTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;
    use ClientGroupSettingsSaver;

    public $settings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        Model::reguard();
    }

    public function testBalanceFilter()
    {
        Client::where('company_id', $this->company->id)->update(['balance' => 0]);

        $low = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'balance' => 50,
        ]);

        $mid = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'balance' => 100,
        ]);

        $high = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'balance' => 200,
        ]);

        $response = $this->withHeaders([
            'X-API-TOKEN' => $this->token,
        ])->getJson('/api/v1/clients?balance=gt:100&per_page=100')
          ->assertStatus(200);

        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($high->hashed_id, $ids);
        $this->assertNotContains($mid->hashed_id, $ids);
        $this->assertNotContains($low->hashed_id, $ids);

        $response = $this->withHeaders([
            'X-API-TOKEN' => $this->token,
        ])->getJson('/api/v1/clients?balance=lt:100&per_page=100')
          ->assertStatus(200);

        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($low->hashed_id, $ids);
        $this->assertNotContains($mid->hashed_id, $ids);
        $this->assertNotContains($high->hashed_id, $ids);

        $response = $this->withHeaders([
            'X-API-TOKEN' => $this->token,
        ])->getJson('/api/v1/clients?balance=eq:100&per_page=100')
          ->assertStatus(200);

        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($mid->hashed_id, $ids);
        $this->assertNotContains($low->hashed_id, $ids);
        $this->assertNotContains($high->hashed_id, $ids);

        $response = $this->withHeaders([
            'X-API-TOKEN' => $this->token,
        ])->getJson('/api/v1/clients?balance=gte:100&per_page=100')
          ->assertStatus(200);

        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($mid->hashed_id, $ids);
        $this->assertContains($high->hashed_id, $ids);
        $this->assertNotContains($low->hashed_id, $ids);
    }

    public function testBetweenBalanceFilter()
    {
        Client::where('company_id', $this->company->id)->update(['balance' => 0]);

        $below = Client::factory()->create(['user_id' => $this->user->id, 'company_id' => $this->company->id, 'balance' => 10]);
        $inside = Client::factory()->create(['user_id' => $this->user->id, 'company_id' => $this->company->id, 'balance' => 100]);
        $above = Client::factory()->create(['user_id' => $this->user->id, 'company_id' => $this->company->id, 'balance' => 500]);

        $response = $this->withHeaders(['X-API-TOKEN' => $this->token])
            ->getJson('/api/v1/clients?between_balance=50:200&per_page=100')
            ->assertStatus(200);

        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($inside->hashed_id, $ids);
        $this->assertNotContains($below->hashed_id, $ids);
        $this->assertNotContains($above->hashed_id, $ids);
    }

    public function testNameFilter()
    {
        $needle = 'UniqueNameNeedle_' . uniqid();
        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'name' => $needle,
        ]);

        $response = $this->withHeaders(['X-API-TOKEN' => $this->token])
            ->getJson('/api/v1/clients?name=' . urlencode(substr($needle, 0, 18)) . '&per_page=100')
            ->assertStatus(200);

        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($client->hashed_id, $ids);
        $this->assertNotContains($this->client->hashed_id, $ids);
    }

    public function testEmailFilter()
    {
        $needleEmail = 'unique_' . uniqid() . '@example.test';

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
        ]);

        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'email' => $needleEmail,
            'is_primary' => 1,
        ]);

        $response = $this->withHeaders(['X-API-TOKEN' => $this->token])
            ->getJson('/api/v1/clients?email=' . urlencode($needleEmail) . '&per_page=100')
            ->assertStatus(200);

        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($client->hashed_id, $ids);
        $this->assertNotContains($this->client->hashed_id, $ids);
    }

    public function testClientIdFilter()
    {
        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
        ]);

        $response = $this->withHeaders(['X-API-TOKEN' => $this->token])
            ->getJson('/api/v1/clients?client_id=' . $client->hashed_id . '&per_page=100')
            ->assertStatus(200);

        $ids = array_column($response->json('data'), 'id');
        $this->assertSame([$client->hashed_id], $ids);
    }

    public function testIdNumberFilter()
    {
        $needle = 'IDN_' . uniqid();
        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'id_number' => $needle,
        ]);

        $response = $this->withHeaders(['X-API-TOKEN' => $this->token])
            ->getJson('/api/v1/clients?id_number=' . urlencode($needle) . '&per_page=100')
            ->assertStatus(200);

        $ids = array_column($response->json('data'), 'id');
        $this->assertSame([$client->hashed_id], $ids);
    }

    public function testNumberFilter()
    {
        $needle = 'NUM_' . uniqid();
        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'number' => $needle,
        ]);

        $response = $this->withHeaders(['X-API-TOKEN' => $this->token])
            ->getJson('/api/v1/clients?number=' . urlencode($needle) . '&per_page=100')
            ->assertStatus(200);

        $ids = array_column($response->json('data'), 'id');
        $this->assertSame([$client->hashed_id], $ids);
    }

    public function testGroupFilter()
    {
        $gs = new GroupSetting();
        $gs->name = 'FilterGroup_' . uniqid();
        $gs->company_id = $this->company->id;
        $gs->settings = ClientSettings::buildClientSettings($this->company->settings, $this->client->settings);
        $gs->save();

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'group_settings_id' => $gs->id,
        ]);

        $response = $this->withHeaders(['X-API-TOKEN' => $this->token])
            ->getJson('/api/v1/clients?group=' . $this->encodePrimaryKey($gs->id) . '&per_page=100')
            ->assertStatus(200);

        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($client->hashed_id, $ids);
        $this->assertNotContains($this->client->hashed_id, $ids);
    }

    public function testFilterDeprecatedSearch()
    {
        $needle = 'SearchNeedle' . uniqid();

        $byName = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'name' => $needle . ' co',
        ]);

        $byIdNumber = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'id_number' => $needle,
        ]);

        $byNumber = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'number' => $needle,
        ]);

        $byCustom = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'custom_value1' => $needle,
        ]);

        $byContact = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
        ]);
        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $byContact->id,
            'email' => $needle . '@example.test',
            'is_primary' => 1,
        ]);

        $response = $this->withHeaders(['X-API-TOKEN' => $this->token])
            ->getJson('/api/v1/clients?filter=' . urlencode($needle) . '&per_page=100')
            ->assertStatus(200);

        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($byName->hashed_id, $ids);
        $this->assertContains($byIdNumber->hashed_id, $ids);
        $this->assertContains($byNumber->hashed_id, $ids);
        $this->assertContains($byCustom->hashed_id, $ids);
        $this->assertContains($byContact->hashed_id, $ids);
        $this->assertNotContains($this->client->hashed_id, $ids);
    }

    public function testSortFilter()
    {
        Client::where('company_id', $this->company->id)->delete();

        $a = Client::factory()->create(['user_id' => $this->user->id, 'company_id' => $this->company->id, 'name' => 'AAA Sort']);
        $b = Client::factory()->create(['user_id' => $this->user->id, 'company_id' => $this->company->id, 'name' => 'MMM Sort']);
        $c = Client::factory()->create(['user_id' => $this->user->id, 'company_id' => $this->company->id, 'name' => 'ZZZ Sort']);

        $asc = $this->withHeaders(['X-API-TOKEN' => $this->token])
            ->getJson('/api/v1/clients?sort=name|asc&per_page=100')
            ->assertStatus(200)
            ->json('data');
        $ascIds = array_column($asc, 'id');
        $this->assertSame(
            [$a->hashed_id, $b->hashed_id, $c->hashed_id],
            array_values(array_intersect($ascIds, [$a->hashed_id, $b->hashed_id, $c->hashed_id]))
        );

        $desc = $this->withHeaders(['X-API-TOKEN' => $this->token])
            ->getJson('/api/v1/clients?sort=name|desc&per_page=100')
            ->assertStatus(200)
            ->json('data');
        $descIds = array_column($desc, 'id');
        $this->assertSame(
            [$c->hashed_id, $b->hashed_id, $a->hashed_id],
            array_values(array_intersect($descIds, [$a->hashed_id, $b->hashed_id, $c->hashed_id]))
        );
    }

    public function testFilterDetails()
    {
        $response = $this->withHeaders(['X-API-TOKEN' => $this->token])
            ->getJson('/api/v1/clients?filter_details=true&per_page=5')
            ->assertStatus(200);

        $data = $response->json('data');
        $this->assertNotEmpty($data);

        $first = $data[0];
        $this->assertArrayHasKey('id', $first);
        $this->assertArrayHasKey('name', $first);
        $this->assertArrayHasKey('number', $first);
        $this->assertArrayHasKey('id_number', $first);
    }

    public function testCurrencyCodePassesValidation()
    {
        $data = [
            'name' => 'name of client',
            'currency_code' => strtoupper('usd'),
        ];

        $response = $this->withHeaders([
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/clients/", $data)
        ->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals("1", $arr['data']['settings']['currency_id']);

    }

    public function testCurrencyIdRequired()
    {
        $data = [
            'name' => 'name of client',
            'settings' => [
                'pdf_variables' => 'xx',
            ],
        ];

        $response = $this->withHeaders([
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/clients/", $data)
        ->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals("1", $arr['data']['settings']['currency_id']);

    }


    public function testCurrencyIdValidationPut()
    {
        $data = [
            'name' => 'name of client',
            'settings' => [
                'pdf_variables' => 'xx',
                'currency_id' => '1000',
            ],
        ];

        $response = $this->withHeaders([
            'X-API-TOKEN' => $this->token,
        ])->putJson("/api/v1/clients/".$this->client->hashed_id, $data)
        ->assertStatus(422);

    }

    public function testCurrencyIdValidationPost()
    {
        $data = [
            'name' => 'name of client',
            'settings' => [
                'pdf_variables' => 'xx',
                'currency_id' => '1000',
            ],
        ];


        $response = $this->withHeaders([
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/clients/", $data)
        ->assertStatus(422);

    }

    public function testPdfVariablesUnset()
    {
        $data = [
            'name' => 'name of client',
            'settings' => [
                'pdf_variables' => 'xx',
                'currency_id' => '2'
            ],
        ];


        $response = $this->withHeaders([
            'X-API-TOKEN' => $this->token,
        ])->putJson("/api/v1/clients/".$this->client->hashed_id, $data)
        ->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals("2", $arr['data']['settings']['currency_id']);
        $this->assertArrayNotHasKey('pdf_variables', $arr['data']['settings']);


    }

    public function testBulkUpdates()
    {
        Client::factory()->count(3)->create([
            "company_id" => $this->company->id,
            "user_id" => $this->user->id,
        ]);

        $client_count = Client::query()->where('company_id', $this->company->id)->count();

        $data = [
            "column" => "public_notes",
            "new_value" => "THISISABULKUPDATE",
            "action" => "bulk_update",
            "ids" => Client::where('company_id', $this->company->id)->get()->pluck("hashed_id")
        ];

        $response = $this->withHeaders([
            'X-API-TOKEN' => $this->token,
            ])->postJson("/api/v1/clients/bulk", $data);


        $response->assertStatus(200);

        $this->assertEquals($client_count, Client::query()->where('public_notes', "THISISABULKUPDATE")->where('company_id', $this->company->id)->count());

    }

    public function testCountryCodeValidation()
    {

        $data = [
            'name' => 'name of client',
            'country_code' => 'USA',
            'id_number' => 'x-1-11a'
        ];

        $response = $this->withHeaders([
            'X-API-TOKEN' => $this->token,
            ])->postJson("/api/v1/clients/", $data)
            ->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals("840", $arr['data']['country_id']);

        $data = [
            'name' => 'name of client',
            'country_code' => 'aaaaaaaaaa',
            'id_number' => 'x-1-11a'
        ];

        $response = $this->withHeaders([
            'X-API-TOKEN' => $this->token,
            ])->postJson("/api/v1/clients/", $data)
            ->assertStatus(422);

        $this->assertEquals($this->company->settings->country_id, $arr['data']['country_id']);


        $data = [
                'name' => 'name of client',
                'country_code' => 'aaaaaaaaaa',
            ];

        $response = $this->withHeaders([
            'X-API-TOKEN' => $this->token,
            ])->putJson("/api/v1/clients/".$this->client->hashed_id, $data)
            ->assertStatus(200);


        $this->assertEquals($this->company->settings->country_id, $arr['data']['country_id']);

    }

    public function testIdNumberPutValidation()
    {

        $data = [
            'name' => 'name of client',
            'country_id' => '840',
            'id_number' => 'x-1-11a'
        ];

        $response = $this->withHeaders([
        'X-API-TOKEN' => $this->token,
        ])->putJson("/api/v1/clients/".$this->client->hashed_id, $data)
        ->assertStatus(200);


        $data = [
            'name' => 'name of client',
            'country_id' => '840',
        ];

        $response = $this->withHeaders([
        'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/clients/", $data)
        ->assertStatus(200);

        $arr = $response->json();

        $data = [
            'name' => 'name of client',
            'country_id' => '840',
            'id_number' => 'x-1-11a'
        ];

        $response = $this->withHeaders([
        'X-API-TOKEN' => $this->token,
        ])->putJson("/api/v1/clients/".$arr['data']['id'], $data)
        ->assertStatus(422);

    }

    public function testNumberPutValidation()
    {

        $data = [
            'name' => 'name of client',
            'country_id' => '840',
            'number' => 'x-1-11a'
        ];

        $response = $this->withHeaders([
        'X-API-TOKEN' => $this->token,
        ])->putJson("/api/v1/clients/".$this->client->hashed_id, $data)
        ->assertStatus(200);


        $data = [
            'name' => 'name of client',
            'country_id' => '840',
        ];

        $response = $this->withHeaders([
        'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/clients/", $data)
        ->assertStatus(200);

        $arr = $response->json();

        $data = [
            'name' => 'name of client',
            'country_id' => '840',
            'number' => 'x-1-11a'
        ];

        $response = $this->withHeaders([
        'X-API-TOKEN' => $this->token,
        ])->putJson("/api/v1/clients/".$arr['data']['id'], $data)
        ->assertStatus(422);

    }

    public function testNumberValidation()
    {
        $data = [
            'name' => 'name of client',
            'country_id' => '840',
            'number' => 'x-1-11'
        ];

        $response = $this->withHeaders([
          'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/clients/", $data)
        ->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals("x-1-11", $arr['data']['number']);

        $data = [
                    'name' => 'name of client',
                    'country_id' => '840',
                    'number' => 'x-1-11'
                ];

        $response = $this->withHeaders([
        'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/clients/", $data)
        ->assertStatus(422);

        $data = [
                    'name' => 'name of client',
                    'country_id' => '840',
                    'number' => ''
                ];

        $response = $this->withHeaders([
        'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/clients/", $data)
        ->assertStatus(200);

        $data = [
                    'name' => 'name of client',
                    'country_id' => '840',
                    'number' => null
                ];

        $response = $this->withHeaders([
        'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/clients/", $data)
        ->assertStatus(200);

    }

    public function testCountryStore4()
    {
        $data = [
            'name' => 'name of client',
            'country_id' => '840',
        ];

        $response = $this->withHeaders([
          'X-API-TOKEN' => $this->token,
      ])->putJson("/api/v1/clients/".$this->client->hashed_id, $data)
      ->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals("840", $arr['data']['country_id']);

    }

    public function testCountryStore3()
    {
        $data = [
            'name' => 'name of client',
            'country_id' => 'A',
        ];

        $response = $this->withHeaders([
          'X-API-TOKEN' => $this->token,
      ])->putJson("/api/v1/clients/".$this->client->hashed_id, $data)
      ->assertStatus(422);

    }


    public function testCountryStore2()
    {
        $data = [
            'name' => 'name of client',
            'country_id' => 'A',
        ];

        $response = $this->withHeaders([
          'X-API-TOKEN' => $this->token,
      ])->postJson("/api/v1/clients/", $data)
      ->assertStatus(422);

    }


    public function testCountryStore()
    {
        $data = [
            'name' => 'name of client',
            'country_id' => '8',
        ];

        $response = $this->withHeaders([
          'X-API-TOKEN' => $this->token,
      ])->postJson("/api/v1/clients/", $data)
      ->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals("8", $arr['data']['country_id']);

    }

    public function testCurrencyStores8()
    {
        $data = [
            'name' => 'name of client',
            'settings' => [
                'currency_id' => '2'
            ],
        ];

        $response = $this->withHeaders([
          'X-API-TOKEN' => $this->token,
      ])->postJson("/api/v1/clients/", $data)
      ->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals("2", $arr['data']['settings']['currency_id']);

    }

    public function testCurrencyStores7()
    {
        $data = [
            'name' => 'name of client',
            'settings' => [
                'currency_id' => '2'
            ],
        ];

        $response = $this->withHeaders([
          'X-API-TOKEN' => $this->token,
      ])->putJson("/api/v1/clients/".$this->client->hashed_id, $data)
      ->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals("2", $arr['data']['settings']['currency_id']);

    }

    public function testCurrencyStores6()
    {
        $data = [
            'name' => 'name of client',
            'settings' => [
                'currency_id' => '1'
            ],
        ];

        $response = $this->withHeaders([
          'X-API-TOKEN' => $this->token,
      ])->putJson("/api/v1/clients/".$this->client->hashed_id, $data)
      ->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals("1", $arr['data']['settings']['currency_id']);

    }

    public function testCurrencyStores5()
    {
        $data = [
            'name' => 'name of client',
            'settings' => [
                'currency_id' => ''
            ],
        ];

        $response = $this->withHeaders([
          'X-API-TOKEN' => $this->token,
      ])->putJson("/api/v1/clients/".$this->client->hashed_id, $data)
      ->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals($this->company->settings->currency_id, $arr['data']['settings']['currency_id']);

    }

    public function testCurrencyStores4()
    {
        $data = [
            'name' => 'name of client',
            'settings' => [
                'currency_id' => 'A'
            ],
        ];

        $response = $this->withHeaders([
          'X-API-TOKEN' => $this->token,
      ])->putJson("/api/v1/clients/".$this->client->hashed_id, $data)
      ->assertStatus(422);

        $arr = $response->json();

        //   $this->assertEquals($this->company->settings->currency_id, $arr['data']['settings']['currency_id']);

    }

    public function testCurrencyStores3()
    {
        $data = [
            'name' => 'name of client',
            'settings' => [
                'currency_id' => 'A'
            ],
        ];

        $response = $this->withHeaders([
          'X-API-TOKEN' => $this->token,
      ])->postJson("/api/v1/clients", $data)
      ->assertStatus(422);

        $arr = $response->json();

        //   $this->assertEquals($this->company->settings->currency_id, $arr['data']['settings']['currency_id']);

    }

    public function testCurrencyStores2()
    {
        $data = [
            'name' => 'name of client',
            'settings' => [
                'currency_id' => ''
            ],
        ];

        $response = $this->withHeaders([
          'X-API-TOKEN' => $this->token,
      ])->postJson("/api/v1/clients", $data)
      ->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals($this->company->settings->currency_id, $arr['data']['settings']['currency_id']);

    }

    public function testCurrencyStores()
    {
        $data = [
            'name' => 'name of client',
            'settings' => [],
        ];

        $response = $this->withHeaders([
          'X-API-TOKEN' => $this->token,
      ])->postJson("/api/v1/clients", $data)
      ->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals($this->company->settings->currency_id, $arr['data']['settings']['currency_id']);

    }

    public function testDocumentValidation()
    {
        $data = [
            'name' => 'name of client',
            'documents' => [],
        ];

        $response = $this->withHeaders([
          'X-API-TOKEN' => $this->token,
      ])->postJson("/api/v1/clients", $data)
      ->assertStatus(200);

    }

    public function testDocumentValidationFails()
    {
        $data = [
            'name' => 'name of client',
            'documents' => 'wut',
        ];

        $response = $this->withHeaders([
          'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/clients", $data)
        ->assertStatus(422);

        $data = [
            'name' => 'name of client',
            'documents' => null,
        ];

        $response = $this->withHeaders([
        'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/clients", $data)
        ->assertStatus(422);

    }

    public function testDocumentValidationPutFails()
    {

        $data = [
            'name' => 'name of client',
            'documents' => null,
        ];

        $response = $this->withHeaders([
        'X-API-TOKEN' => $this->token,
        ])->putJson("/api/v1/clients/{$this->client->hashed_id}", $data)
        ->assertStatus(422);

        $data = [
                'name' => 'name of client',
                'documents' => [],
            ];

        $response = $this->withHeaders([
        'X-API-TOKEN' => $this->token,
        ])->putJson("/api/v1/clients/{$this->client->hashed_id}", $data)
        ->assertStatus(200);

    }

    public function testClientDocumentQuery()
    {

        $d = \App\Models\Document::factory()->create([
           'company_id' => $this->company->id,
           'user_id' => $this->user->id,
       ]);

        $this->invoice->documents()->save($d);

        $response = $this->withHeaders([
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/clients/{$this->client->hashed_id}/documents")
        ->assertStatus(200);

        $arr = $response->json();

        $this->assertCount(1, $arr['data']);

        $d = \App\Models\Document::factory()->create([
        'company_id' => $this->company->id,
        'user_id' => $this->user->id,
            ]);

        $this->client->documents()->save($d);

        $response = $this->withHeaders([
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/clients/{$this->client->hashed_id}/documents")
        ->assertStatus(200);

        $arr = $response->json();

        $this->assertCount(2, $arr['data']);


        $d = \App\Models\Document::factory()->create([
        'company_id' => $this->company->id,
        'user_id' => $this->user->id,
            ]);

        $this->client->documents()->save($d);

        $response = $this->withHeaders([
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/clients/{$this->client->hashed_id}/documents")
        ->assertStatus(200);

        $arr = $response->json();

        $this->assertCount(3, $arr['data']);

        $d = \App\Models\Document::factory()->create([
        'company_id' => $this->company->id,
        'user_id' => $this->user->id,
            ]);

        $this->quote->documents()->save($d);

        $response = $this->withHeaders([
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/clients/{$this->client->hashed_id}/documents")
        ->assertStatus(200);

        $arr = $response->json();

        $this->assertCount(4, $arr['data']);



        $d = \App\Models\Document::factory()->create([
                'company_id' => $this->company->id,
                'user_id' => $this->user->id,
                    ]);

        $this->credit->documents()->save($d);

        $response = $this->withHeaders([
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/clients/{$this->client->hashed_id}/documents")
        ->assertStatus(200);

        $arr = $response->json();

        $this->assertCount(5, $arr['data']);



        $d = \App\Models\Document::factory()->create([
                'company_id' => $this->company->id,
                'user_id' => $this->user->id,
        ]);


        $e = \App\Models\Expense::factory()->create([
                'company_id' => $this->company->id,
                'user_id' => $this->user->id,
                'client_id' => $this->client->id,
                'amount' => 100
        ]);


        $e->documents()->save($d);

        $response = $this->withHeaders([
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/clients/{$this->client->hashed_id}/documents")
        ->assertStatus(200);

        $arr = $response->json();

        $this->assertCount(6, $arr['data']);


        $d = \App\Models\Document::factory()->create([
                'company_id' => $this->company->id,
                'user_id' => $this->user->id,
        ]);


        $t = \App\Models\Task::factory()->create([
                'company_id' => $this->company->id,
                'user_id' => $this->user->id,
                'client_id' => $this->client->id,
        ]);


        $t->documents()->save($d);

        $response = $this->withHeaders([
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/clients/{$this->client->hashed_id}/documents")
        ->assertStatus(200);

        $arr = $response->json();

        $this->assertCount(7, $arr['data']);




    }

    public function testCrossCompanyBulkActionsFail()
    {
        $account = Account::factory()->create([
            'hosted_client_count' => 1000,
            'hosted_company_count' => 1000,
        ]);

        $account->num_users = 3;
        $account->save();

        $company = Company::factory()->create([
            'account_id' => $account->id,
        ]);

        $user = User::factory()->create([
            'account_id' => $account->id,
            'confirmation_code' => '123',
            'email' =>  $this->faker->safeEmail(),
        ]);

        $cu = CompanyUserFactory::create($user->id, $company->id, $account->id);
        $cu->is_owner = true;
        $cu->is_admin = true;
        $cu->is_locked = true;
        $cu->permissions = '["view_client"]';
        $cu->save();

        $different_company_token = \Illuminate\Support\Str::random(64);

        $company_token = new CompanyToken();
        $company_token->user_id = $user->id;
        $company_token->company_id = $company->id;
        $company_token->account_id = $account->id;
        $company_token->name = 'test token';
        $company_token->token = $different_company_token;
        $company_token->is_system = true;
        $company_token->save();

        $data = [
            'action' => 'archive',
            'ids' => [
                $this->client->id
            ]
        ];

        $response = $this->withHeaders([
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/clients/bulk', $data)
          ->assertStatus(302);

        //using existing permissions, they must pass the ->edit guard()
        $this->client->fresh();
        $this->assertNull($this->client->deleted_at);

        $rules = [
            'ids' => 'required|bail|array|exists:clients,id,company_id,'.$company->id,
            'action' => 'in:archive,restore,delete'
        ];

        $v = $this->app['validator']->make($data, $rules);

        $this->assertFalse($v->passes());
    }


    public function testClientBulkActionValidation()
    {
        $data = [
            'action' => 'muppet',
            'ids' => [
                $this->client->hashed_id
            ]
        ];

        $rules = [
            'ids' => 'required|bail|array',
            'action' => 'in:archive,restore,delete'
        ];

        $v = $this->app['validator']->make($data, $rules);
        $this->assertFalse($v->passes());

        $data = [
            'action' => 'archive',
            'ids' => [
                $this->client->hashed_id
            ]
        ];

        $v = $this->app['validator']->make($data, $rules);
        $this->assertTrue($v->passes());


        $data = [
            'action' => 'archive',
            'ids' =>
                $this->client->hashed_id

        ];

        $v = $this->app['validator']->make($data, $rules);
        $this->assertFalse($v->passes());
    }

    public function testClientStatement()
    {
        $response = null;

        $data  = [
            'client_id' => $this->client->hashed_id,
            'start_date' => '2000-01-01',
            'end_date' => '2023-01-01',
            'show_aging_table' => true,
            'show_payments_table' => true,
            'status' => 'paid',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/client_statement', $data);

        $response->assertStatus(200);

        $this->assertTrue($response->headers->get('content-type') == 'application/pdf');


    }

    public function testClientStatementEmail()
    {
        $response = null;

        $data  = [
            'client_id' => $this->client->hashed_id,
            'start_date' => '2000-01-01',
            'end_date' => '2023-01-01',
            'show_aging_table' => true,
            'show_payments_table' => true,
            'status' => 'paid',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/client_statement?send_email=true', $data);


        $response->assertJson([
            'message' => ctrans('texts.email_queued'),
        ]);


        $response->assertStatus(200);
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
          'country_id' => null,
          'shipping_country_id' => null,
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
        $std = new \stdClass();
        $std->entity = 'App\\Models\\Client';
        $std->currency_id = 3;

        $this->settings = $this->client->settings;

        $this->saveSettings($std, $this->client);

        $this->assertTrue(true);
    }


    public function testClientSettingsSave2()
    {
        $std = new \stdClass();
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
          'settings' =>
          (object) [
             'entity' => 'App\\Models\\Client',
             'currency_id' => '3',
          ],
          'client_hash' => 'xx',
          'contacts' =>
          [
            0 =>
            [
              'first_name' => '',
              'last_name' => '',
              'email' => '',
              'phone' => '',
              'custom_value1' => '',
              'custom_value2' => '',
              'custom_value3' => '',
              'custom_value4' => '',
            ],
          ],
          'country_id' => null,
          'shipping_country_id' => null,
          'user_id' => $this->user->id,
        ];


        $request_name = StoreClientRequest::class;
        $repository_name = ClientRepository::class;
        $factory_name = ClientFactory::class;

        $repository = app()->make($repository_name);
        $repository->import_mode = true;

        $_syn_request_class = new $request_name();
        $_syn_request_class->setContainer(app());
        $_syn_request_class->initialize($data);
        $_syn_request_class->prepareForValidation();

        $validator = Validator::make($_syn_request_class->all(), $_syn_request_class->rules());

        $_syn_request_class->setValidator($validator);

        $this->assertFalse($validator->fails());
    }



    public function testClientImportDataStructure()
    {
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
          'settings' =>
          (object) [
             'entity' => 'App\\Models\\Client',
             'currency_id' => '3',
          ],
          'client_hash' => 'xx',
          'contacts' =>
          [
            0 =>
            [
              'first_name' => '',
              'last_name' => '',
              'email' => '',
              'phone' => '',
              'custom_value1' => '',
              'custom_value2' => '',
              'custom_value3' => '',
              'custom_value4' => '',
            ],
          ],
          'country_id' => null,
          'shipping_country_id' => null,
          'user_id' => $this->user->id,
        ];

        $crepo = new ClientRepository(new ClientContactRepository());

        $c = $crepo->save(array_diff_key($data, ['user_id' => false]), ClientFactory::create($this->company->id, $this->user->id));
        $c->saveQuietly();

        $this->assertEquals('Christian xx', $c->name);
        $this->assertEquals('3', $c->settings->currency_id);
    }

    public function testClientCsvImport()
    {
        $settings = ClientSettings::defaults();
        $settings->currency_id = "1";

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

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/clients/', $data);


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

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/clients/', $data);


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

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/clients/', $data);


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

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/clients/', $data);


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

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/clients/', $data);


        $response->assertStatus(200);
    }

    public function testClientNoneValidation()
    {
        $data = [
            'name' => $this->faker->firstName(),
            'number' => '',
        ];

        $response = false;

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/clients/', $data);

        $response->assertStatus(200);
    }

    public function testClientNullValidation()
    {
        $data = [
            'name' => $this->faker->firstName(),
            'number' => null,
        ];

        $response = false;

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/clients/', $data);


        $response->assertStatus(200);
    }

    public function testClientCountryCodeValidationTrueIso3()
    {
        $data = [
            'name' => $this->faker->firstName(),
            'id_number' => 'Coolio',
            'country_code' => 'ARM',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/clients/', $data);


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

        $response->assertStatus(200);
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
            'ids' => [$this->client->hashed_id],
        ];

        $response = false;

        $response = $this->withHeaders([
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/clients/bulk?action=archive', $data);

        $response->assertStatus(200);
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
        ])->postJson('/api/v1/clients/bulk?action=restore', $data);

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
        ])->postJson('/api/v1/clients/bulk?action=delete', $data);

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
        ])->postJson('/api/v1/clients/', $data);

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
        ])->postJson('/api/v1/clients/', $data);

        $arr = $response->json();

        $response->assertStatus(422);
        // $this->assertEquals($this->company->settings->country_id, $arr['data']['country_id']);
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
