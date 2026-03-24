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

namespace Tests\Feature\Export;

use App\DataMapper\CompanySettings;
use App\Export\CSV\ClientExport;
use App\Export\CSV\LocationExport;
use App\Factory\CompanyUserFactory;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\CompanyToken;
use App\Models\Location;
use App\Models\User;
use App\Utils\Traits\MakesHash;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use League\Csv\Reader;
use League\Csv\ResultSet;
use Tests\TestCase;

class LocationExportTest extends TestCase
{
    use MakesHash;

    public $faker;

    public $company;

    public $user;

    public $account;

    public $client;

    public $token;

    public $cu;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = \Faker\Factory::create();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->withoutExceptionHandling();

        config(['queue.default' => 'sync']);

        $this->buildData();

        if (config('ninja.testvars.travis') !== false) {
            $this->markTestSkipped('Skip test no company gateways installed');
        }
    }

    private function buildData()
    {
        if ($this->account ?? false) {
            $this->account->forceDelete();
        }

        /** @var \App\Models\Account $account */
        $this->account = Account::factory()->create([
            'hosted_client_count' => 1000,
            'hosted_company_count' => 1000,
        ]);

        $this->account->num_users = 3;
        $this->account->save();

        $this->user = User::factory()->create([
            'account_id' => $this->account->id,
            'confirmation_code' => 'xyz123',
            'email' => \Illuminate\Support\Str::random(32) . '@example.com',
        ]);

        $settings = CompanySettings::defaults();
        $settings->client_online_payment_notification = false;
        $settings->client_manual_payment_notification = false;

        $this->company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
        ]);

        $this->company->settings = $settings;
        $this->company->save();

        $this->cu = CompanyUserFactory::create($this->user->id, $this->company->id, $this->account->id);
        $this->cu->is_owner = true;
        $this->cu->is_admin = true;
        $this->cu->is_locked = false;
        $this->cu->save();

        $this->token = \Illuminate\Support\Str::random(64);

        $company_token = new CompanyToken();
        $company_token->user_id = $this->user->id;
        $company_token->company_id = $this->company->id;
        $company_token->account_id = $this->account->id;
        $company_token->name = 'test token';
        $company_token->token = $this->token;
        $company_token->is_system = true;
        $company_token->save();

        $this->client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'is_deleted' => 0,
            'name' => 'Test Client',
            'address1' => '123 Main St',
            'city' => 'Dallas',
            'state' => 'TX',
            'postal_code' => '75201',
        ]);

        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'is_primary' => 1,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
        ]);
    }

    private function createLocation(array $overrides = []): Location
    {
        return Location::factory()->create(array_merge([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ], $overrides));
    }

    private function poll($hash)
    {
        $response = Http::retry(100, 200, throw: false)
                    ->withHeaders([
                        'X-API-SECRET' => config('ninja.api_secret'),
                        'X-API-TOKEN' => $this->token,
                    ])->post(config('ninja.app_url') . "/api/v1/exports/preview/{$hash}");

        return $response;
    }

    private function getFirstValueByColumn($csv, $column)
    {
        $reader = Reader::fromString($csv);
        $reader->setHeaderOffset(0);

        $res = ResultSet::from($reader)->fetchColumn($column);
        $res = iterator_to_array($res, true);

        return $res[1];
    }

    public function testLocationApiRouteReturns200()
    {
        $this->createLocation();

        $data = [
            'send_email' => false,
            'date_range' => 'all',
            'report_keys' => [],
            'user_id' => $this->user->id,
        ];

        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/reports/locations', $data)
        ->assertStatus(200);

        $this->account->forceDelete();
    }

    public function testLocationAlternateApiRouteReturns200()
    {
        $this->createLocation();

        $data = [
            'send_email' => false,
            'date_range' => 'all',
            'report_keys' => [],
            'user_id' => $this->user->id,
        ];

        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/reports/client_locations', $data)
        ->assertStatus(200);

        $this->account->forceDelete();
    }

    public function testLocationCsvGenerationWithDefaultKeys()
    {
        $this->createLocation([
            'name' => 'HQ Office',
            'address1' => '456 Corporate Blvd',
            'city' => 'Austin',
            'state' => 'TX',
            'postal_code' => '73301',
        ]);

        $data = [
            'date_range' => 'all',
            'report_keys' => [],
            'send_email' => false,
            'user_id' => $this->user->id,
        ];

        $export = new LocationExport($this->company, $data);
        $csv = $export->run();

        $reader = Reader::fromString($csv);
        $reader->setHeaderOffset(0);

        $headers = $reader->getHeader();
        $this->assertNotEmpty($headers);

        $records = iterator_to_array($reader->getRecords());
        $this->assertCount(1, $records);

        $this->account->forceDelete();
    }

    public function testLocationCsvContentValues()
    {
        $this->createLocation([
            'name' => 'Main Office',
            'address1' => '100 Test Street',
            'city' => 'Denver',
            'state' => 'CO',
            'postal_code' => '80201',
        ]);

        $data = [
            'date_range' => 'all',
            'report_keys' => [
                'location.name',
                'location.address1',
                'location.city',
                'location.state',
                'location.postal_code',
            ],
            'send_email' => false,
            'user_id' => $this->user->id,
        ];

        $export = new LocationExport($this->company, $data);
        $csv = $export->run();

        $this->assertEquals('Main Office', $this->getFirstValueByColumn($csv, 'Location Name'));
        $this->assertEquals('100 Test Street', $this->getFirstValueByColumn($csv, 'Location Street'));
        $this->assertEquals('Denver', $this->getFirstValueByColumn($csv, 'Location City'));
        $this->assertEquals('CO', $this->getFirstValueByColumn($csv, 'Location State/Province'));
        $this->assertEquals('80201', $this->getFirstValueByColumn($csv, 'Location Postal Code'));

        $this->account->forceDelete();
    }

    public function testMultipleLocationsProduceMultipleRows()
    {
        $this->createLocation([
            'name' => 'Location A',
            'city' => 'CityA',
        ]);

        $this->createLocation([
            'name' => 'Location B',
            'city' => 'CityB',
        ]);

        $this->createLocation([
            'name' => 'Location C',
            'city' => 'CityC',
        ]);

        $data = [
            'date_range' => 'all',
            'report_keys' => ['location.name', 'location.city'],
            'send_email' => false,
            'user_id' => $this->user->id,
        ];

        $export = new LocationExport($this->company, $data);
        $csv = $export->run();

        $reader = Reader::fromString($csv);
        $reader->setHeaderOffset(0);
        $records = iterator_to_array($reader->getRecords());

        $this->assertCount(3, $records);

        $cities = array_column($records, 'Location City');
        $this->assertContains('CityA', $cities);
        $this->assertContains('CityB', $cities);
        $this->assertContains('CityC', $cities);

        $this->account->forceDelete();
    }

    public function testDeletedClientLocationsAreExcluded()
    {
        $deletedClient = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'is_deleted' => 1,
            'name' => 'Deleted Client',
        ]);

        $this->createLocation([
            'name' => 'Visible Location',
        ]);

        Location::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $deletedClient->id,
            'name' => 'Hidden Location',
        ]);

        $data = [
            'date_range' => 'all',
            'report_keys' => ['location.name'],
            'send_email' => false,
            'user_id' => $this->user->id,
        ];

        $export = new LocationExport($this->company, $data);
        $csv = $export->run();

        $reader = Reader::fromString($csv);
        $reader->setHeaderOffset(0);
        $records = iterator_to_array($reader->getRecords());

        $this->assertCount(1, $records);
        $this->assertEquals('Visible Location', $records[1]['Location Name']);

        $this->account->forceDelete();
    }

    public function testShippingLocationFlag()
    {
        $this->createLocation([
            'name' => 'Shipping Warehouse',
            'is_shipping_location' => true,
        ]);

        $data = [
            'date_range' => 'all',
            'report_keys' => ['location.name', 'location.is_shipping_location'],
            'send_email' => false,
            'user_id' => $this->user->id,
        ];

        $export = new LocationExport($this->company, $data);
        $csv = $export->run();

        $reader = Reader::fromString($csv);
        $reader->setHeaderOffset(0);
        $records = iterator_to_array($reader->getRecords());

        $this->assertCount(1, $records);
        $this->assertEquals('Shipping Warehouse', $records[1]['Location Name']);

        $this->account->forceDelete();
    }

    public function testLocationExportDirectRun()
    {
        $this->createLocation([
            'name' => 'Direct Test Location',
            'address1' => '555 Export Ave',
            'city' => 'Portland',
            'state' => 'OR',
        ]);

        $data = [
            'date_range' => 'all',
            'report_keys' => [
                'location.name',
                'location.address1',
                'location.city',
            ],
            'send_email' => false,
            'user_id' => $this->user->id,
        ];

        $export = new LocationExport($this->company, $data);
        $csv = $export->run();

        $reader = Reader::fromString($csv);
        $reader->setHeaderOffset(0);
        $records = iterator_to_array($reader->getRecords());

        $this->assertCount(1, $records);

        $row = reset($records);
        $this->assertEquals('Direct Test Location', $row['Location Name']);
        $this->assertEquals('555 Export Ave', $row['Location Street']);
        $this->assertEquals('Portland', $row['Location City']);

        $this->account->forceDelete();
    }

    public function testLocationExportReturnJson()
    {
        $this->createLocation([
            'name' => 'JSON Test Location',
            'city' => 'Seattle',
        ]);

        $data = [
            'date_range' => 'all',
            'report_keys' => [
                'location.name',
                'location.city',
            ],
            'send_email' => false,
            'user_id' => $this->user->id,
        ];

        $export = new LocationExport($this->company, $data);
        $result = $export->returnJson();

        $this->assertArrayHasKey('columns', $result);
        $this->assertCount(2, $result['columns']);

        $dataRows = array_filter($result, fn($key) => $key !== 'columns', ARRAY_FILTER_USE_KEY);
        $this->assertNotEmpty($dataRows);

        $this->account->forceDelete();
    }

    public function testVendorAndClientLocationsAreIncluded()
    {
        $this->createLocation([
            'name' => 'Client Location',
        ]);

        $vendor = \App\Models\Vendor::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'name' => 'Some Vendor',
        ]);

        Location::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => null,
            'vendor_id' => $vendor->id,
            'name' => 'Vendor Location',
        ]);

        $data = [
            'date_range' => 'all',
            'report_keys' => ['location.name'],
            'send_email' => false,
            'user_id' => $this->user->id,
        ];

        $export = new LocationExport($this->company, $data);
        $csv = $export->run();

        $reader = Reader::fromString($csv);
        $reader->setHeaderOffset(0);
        $records = iterator_to_array($reader->getRecords());

        $this->assertCount(2, $records);

        $names = array_column($records, 'Location Name');
        $this->assertContains('Client Location', $names);
        $this->assertContains('Vendor Location', $names);

        $this->account->forceDelete();
    }

    public function testOrphanedLocationsAreExcluded()
    {
        $this->createLocation([
            'name' => 'Valid Location',
        ]);

        // Location with no client or vendor
        Location::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => null,
            'vendor_id' => null,
            'name' => 'Orphaned Location',
        ]);

        $data = [
            'date_range' => 'all',
            'report_keys' => ['location.name'],
            'send_email' => false,
            'user_id' => $this->user->id,
        ];

        $export = new LocationExport($this->company, $data);
        $csv = $export->run();

        $reader = Reader::fromString($csv);
        $reader->setHeaderOffset(0);
        $records = iterator_to_array($reader->getRecords());

        $this->assertCount(1, $records);
        $this->assertEquals('Valid Location', reset($records)['Location Name']);

        $this->account->forceDelete();
    }

    public function testEmptyLocationExport()
    {
        $data = [
            'date_range' => 'all',
            'report_keys' => ['location.name'],
            'send_email' => false,
            'user_id' => $this->user->id,
        ];

        $export = new LocationExport($this->company, $data);
        $csv = $export->run();

        $reader = Reader::fromString($csv);
        $reader->setHeaderOffset(0);
        $records = iterator_to_array($reader->getRecords());

        $this->assertCount(0, $records);

        $this->account->forceDelete();
    }

    public function testClientExportWithLocationFields()
    {
        $this->createLocation([
            'name' => 'HQ Location',
            'address1' => '999 Location Blvd',
            'city' => 'Miami',
            'state' => 'FL',
            'postal_code' => '33101',
        ]);

        $data = [
            'date_range' => 'all',
            'report_keys' => [
                'client.name',
                'location.name',
                'location.address1',
                'location.city',
                'location.state',
                'location.postal_code',
            ],
            'send_email' => false,
            'include_deleted' => false,
            'user_id' => $this->user->id,
        ];

        $export = new ClientExport($this->company, $data);
        $csv = $export->run();

        $reader = Reader::fromString($csv);
        $reader->setHeaderOffset(0);
        $records = iterator_to_array($reader->getRecords());

        $this->assertCount(1, $records);

        $row = reset($records);
        $this->assertEquals('Test Client', $row['Name']);
        $this->assertEquals('HQ Location', $row['Location Name']);
        $this->assertEquals('999 Location Blvd', $row['Location Street']);
        $this->assertEquals('Miami', $row['Location City']);
        $this->assertEquals('FL', $row['Location State/Province']);
        $this->assertEquals('33101', $row['Location Postal Code']);

        $this->account->forceDelete();
    }

    public function testClientExportWithNoLocationReturnsEmptyLocationFields()
    {
        $data = [
            'date_range' => 'all',
            'report_keys' => [
                'client.name',
                'location.name',
                'location.city',
            ],
            'send_email' => false,
            'include_deleted' => false,
            'user_id' => $this->user->id,
        ];

        $export = new ClientExport($this->company, $data);
        $csv = $export->run();

        $reader = Reader::fromString($csv);
        $reader->setHeaderOffset(0);
        $records = iterator_to_array($reader->getRecords());

        $this->assertCount(1, $records);

        $row = reset($records);
        $this->assertEquals('Test Client', $row['Name']);
        $this->assertEmpty($row['Location Name']);
        $this->assertEmpty($row['Location City']);

        $this->account->forceDelete();
    }

    public function testLocationExportIncludesClientName()
    {
        $this->createLocation([
            'name' => 'Client HQ',
            'city' => 'Boston',
        ]);

        $data = [
            'date_range' => 'all',
            'report_keys' => [
                'location.name',
                'location.city',
                'client.name',
            ],
            'send_email' => false,
            'user_id' => $this->user->id,
        ];

        $export = new LocationExport($this->company, $data);
        $csv = $export->run();

        $reader = Reader::fromString($csv);
        $reader->setHeaderOffset(0);
        $records = iterator_to_array($reader->getRecords());

        $this->assertCount(1, $records);

        $row = reset($records);
        $this->assertEquals('Client HQ', $row['Location Name']);
        $this->assertEquals('Test Client', $row['Client Name']);

        $this->account->forceDelete();
    }

    public function testLocationExportIncludesVendorName()
    {
        $vendor = \App\Models\Vendor::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'name' => 'Test Vendor',
        ]);

        Location::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => null,
            'vendor_id' => $vendor->id,
            'name' => 'Vendor Warehouse',
            'city' => 'Chicago',
        ]);

        $data = [
            'date_range' => 'all',
            'report_keys' => [
                'location.name',
                'location.city',
                'vendor.name',
            ],
            'send_email' => false,
            'user_id' => $this->user->id,
        ];

        $export = new LocationExport($this->company, $data);
        $csv = $export->run();

        $reader = Reader::fromString($csv);
        $reader->setHeaderOffset(0);
        $records = iterator_to_array($reader->getRecords());

        $row = collect($records)->firstWhere('Location Name', 'Vendor Warehouse');
        $this->assertNotNull($row);
        $this->assertEquals('Test Vendor', $row['Vendor Name']);

        $this->account->forceDelete();
    }

    public function testLocationExportWithBothClientAndVendorNames()
    {
        $this->createLocation([
            'name' => 'Client Office',
        ]);

        $vendor = \App\Models\Vendor::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'name' => 'Acme Vendor',
        ]);

        Location::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => null,
            'vendor_id' => $vendor->id,
            'name' => 'Vendor Depot',
        ]);

        $data = [
            'date_range' => 'all',
            'report_keys' => [
                'location.name',
                'client.name',
                'vendor.name',
            ],
            'send_email' => false,
            'user_id' => $this->user->id,
        ];

        $export = new LocationExport($this->company, $data);
        $csv = $export->run();

        $reader = Reader::fromString($csv);
        $reader->setHeaderOffset(0);
        $records = iterator_to_array($reader->getRecords());

        $this->assertCount(2, $records);

        $clientRow = collect($records)->firstWhere('Location Name', 'Client Office');
        $this->assertEquals('Test Client', $clientRow['Client Name']);
        $this->assertEmpty($clientRow['Vendor Name']);

        $vendorRow = collect($records)->firstWhere('Location Name', 'Vendor Depot');
        $this->assertEmpty($vendorRow['Client Name']);
        $this->assertEquals('Acme Vendor', $vendorRow['Vendor Name']);

        $this->account->forceDelete();
    }
}
