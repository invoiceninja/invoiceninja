<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Location;
use App\Utils\HtmlEngine;
use Tests\MockAccountData;
use App\Models\ClientContact;
use Illuminate\Support\Facades\Session;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class LocationApiTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
        Session::start();
    }


    public function testResolvingNormalShippingLocationData()
    {
        $client = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'address1' => '123 Test St',
            'address2' => 'Suite 100',
            'city' => 'Test City',
            'state' => 'TS',
            'postal_code' => '12345',
            'country_id' => '840', // USA
            'shipping_address1' => 'Shipping Address 1',
            'shipping_address2' => 'Shipping Address 2',
            'shipping_city' => 'Shipping City',
            'shipping_state' => 'Shipping State',
            'shipping_postal_code' => 'Shipping Postal Code',
            'shipping_country_id' => '4',
        ]);

        $contact = ClientContact::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'first_name' => 'Test',
            'last_name' => 'Contact',
            'email' => 'test@test.com',
            'send_email' => true,
        ]);

        $location = Location::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'address1' => 'Location Address 1',
            'address2' => 'Location Address 2',
            'city' => 'Location City',
            'state' => 'Location State',
            'postal_code' => 'Location Postal Code',
            'country_id' => '4', 
            'is_shipping_location' => false,
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'location_id' => $location->id,
        ]);
        
        $invoice->service()->createInvitations()->markSent()->save();

        $invitation = $invoice->invitations()->first();

        $html_engine = new HtmlEngine($invitation);
        $html_engine->buildEntityDataArray();
        $data = $html_engine->makeValues();

        $this->assertEquals('Location Address 1', $data['$client.address1']);
        $this->assertEquals('Location Address 2', $data['$client.address2']);
        $this->assertEquals('Location City', $data['$client.city']);
        $this->assertEquals('Location State', $data['$client.state']);
        $this->assertEquals('Location Postal Code', $data['$client.postal_code']);
        $this->assertEquals('Afghanistan', $data['$client.country']);
        
        $this->assertEquals('Shipping Address 1', $data['$client.shipping_address1']);
        $this->assertEquals('Shipping Address 2', $data['$client.shipping_address2']);
        $this->assertEquals('Shipping City', $data['$client.shipping_city']);
        $this->assertEquals('Shipping State', $data['$client.shipping_state']);
        $this->assertEquals('Shipping Postal Code', $data['$client.shipping_postal_code']);
        $this->assertEquals('Afghanistan', $data['$client.shipping_country']);

    }

    public function testResolvingNormalLocationData()
    {
        $client = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'address1' => '123 Test St',
            'address2' => 'Suite 100',
            'city' => 'Test City',
            'state' => 'TS',
            'postal_code' => '12345',
            'country_id' => '840', // USA
        ]);

        $contact = ClientContact::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'first_name' => 'Test',
            'last_name' => 'Contact',
            'email' => 'test@test.com',
            'send_email' => true,
        ]);

        $location = Location::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'address1' => 'Location Address 1',
            'address2' => 'Location Address 2',
            'city' => 'Location City',
            'state' => 'Location State',
            'postal_code' => 'Location Postal Code',
            'country_id' => '4', 
            'is_shipping_location' => true,
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'location_id' => $location->id,
        ]);
        
        $invoice->service()->createInvitations()->markSent()->save();

        $invitation = $invoice->invitations()->first();

        $html_engine = new HtmlEngine($invitation);
        $html_engine->buildEntityDataArray();
        $data = $html_engine->makeValues();

        $this->assertEquals('123 Test St', $data['$client.address1']);
        $this->assertEquals('Suite 100', $data['$client.address2']);
        $this->assertEquals('Test City', $data['$client.city']);
        $this->assertEquals('TS', $data['$client.state']);
        $this->assertEquals('12345', $data['$client.postal_code']);
        $this->assertEquals('United States', $data['$client.country']);
        
        $this->assertEquals('Location Address 1', $data['$client.shipping_address1']);
        $this->assertEquals('Location Address 2', $data['$client.shipping_address2']);
        $this->assertEquals('Location City', $data['$client.shipping_city']);
        $this->assertEquals('Location State', $data['$client.shipping_state']);
        $this->assertEquals('Location Postal Code', $data['$client.shipping_postal_code']);
        $this->assertEquals('Afghanistan', $data['$client.shipping_country']);

    }


    public function testResolvingShippingLocationData()
    {
        $client = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'address1' => '123 Test St',
            'address2' => 'Suite 100',
            'city' => 'Test City',
            'state' => 'TS',
            'postal_code' => '12345',
            'country_id' => '840', // USA
        ]);

        $contact = ClientContact::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'first_name' => 'Test',
            'last_name' => 'Contact',
            'email' => 'test@test.com',
            'send_email' => true,
        ]);

        $location = Location::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'address1' => 'Location Address 1',
            'address2' => 'Location Address 2',
            'city' => 'Location City',
            'state' => 'Location State',
            'postal_code' => 'Location Postal Code',
            'country_id' => '4', 
            'is_shipping_location' => true,
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'location_id' => $location->id,
        ]);
        
        $invoice->service()->createInvitations()->markSent()->save();

        $invitation = $invoice->invitations()->first();

        $html_engine = new HtmlEngine($invitation);
        $html_engine->buildEntityDataArray();
        $data = $html_engine->makeValues();

        $this->assertEquals('Location Address 1', $data['$client.shipping_address1']);
        $this->assertEquals('Location Address 2', $data['$client.shipping_address2']);
        $this->assertEquals('Location City', $data['$client.shipping_city']);
        $this->assertEquals('Location State', $data['$client.shipping_state']);
        $this->assertEquals('Location Postal Code', $data['$client.shipping_postal_code']);
        $this->assertEquals('Afghanistan', $data['$client.shipping_country']);
        
    }

    public function testResolvingBusinessLocationData()
    {
        $client = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'address1' => '123 Test St',
            'address2' => 'Suite 100',
            'city' => 'Test City',
            'state' => 'TS',
            'postal_code' => '12345',
            'country_id' => '840', // USA
        ]);

        $contact = ClientContact::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'first_name' => 'Test',
            'last_name' => 'Contact',
            'email' => 'test@test.com',
            'send_email' => true,
        ]);

        $location = Location::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'address1' => 'Location Address 1',
            'address2' => 'Location Address 2',
            'city' => 'Location City',
            'state' => 'Location State',
            'postal_code' => 'Location Postal Code',
            'country_id' => '4', 
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'location_id' => $location->id,
        ]);
        
        $invoice->service()->createInvitations()->markSent()->save();

        $invitation = $invoice->invitations()->first();

        $html_engine = new HtmlEngine($invitation);
        $html_engine->buildEntityDataArray();
        $data = $html_engine->makeValues();

        $this->assertEquals('Location Address 1', $data['$client.address1']);
        $this->assertEquals('Location Address 2', $data['$client.address2']);
        $this->assertEquals('Location City', $data['$client.city']);
        $this->assertEquals('Location State', $data['$client.state']);
        $this->assertEquals('Location Postal Code', $data['$client.postal_code']);
        $this->assertEquals('Afghanistan', $data['$client.country']);
        
    }

    public function testLocationPost()
    {
        $data = [
            'name' => 'Test Location',
            'address1' => '123 Test St',
            'address2' => 'Suite 100',
            'city' => 'Test City',
            'state' => 'TS',
            'postal_code' => '12345',
            'country_id' => '840', // USA
        ];

        $response = $this->withHeaders([
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/locations', $data);

        $response->assertStatus(422);

         $data = [
            'name' => 'Test Location',
            'address1' => '123 Test St',
            'address2' => 'Suite 100',
            'city' => 'Test City',
            'state' => 'TS',
            'postal_code' => '12345',
            'country_id' => '840', // USA
            'client_id' => $this->client->id,
        ];

        $response = $this->withHeaders([
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/locations', $data);

        $response->assertStatus(200);

        $arr = $response->json();
        $this->assertEquals($data['name'], $arr['data']['name']);
        $this->assertEquals($data['address1'], $arr['data']['address1']);
    }

    public function testLocationGet()
    {
        $location = Location::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'country_id' => '840',
        ]);

        $response = $this->withHeaders([
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/locations/' . $this->encodePrimaryKey($location->id));

        $response->assertStatus(200);

        $arr = $response->json();
        $this->assertEquals($location->name, $arr['data']['name']);
    }

    public function testLocationPut()
    {
        $location = Location::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $data = [
            'name' => 'Updated Location',
            'address1' => '456 Update St',
            'client_id' => $this->client->id,
        ];

        $response = $this->withHeaders([
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/locations/' . $this->encodePrimaryKey($location->id), $data);

        $response->assertStatus(200);

        $arr = $response->json();
        $this->assertEquals($data['name'], $arr['data']['name']);
        $this->assertEquals($data['address1'], $arr['data']['address1']);
    }

    public function testLocationDelete()
    {
        $location = Location::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->withHeaders([
            'X-API-TOKEN' => $this->token,
        ])->deleteJson('/api/v1/locations/' . $this->encodePrimaryKey($location->id));

        $response->assertStatus(200);
    }

    public function testLocationList()
    {
        Location::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->withHeaders([
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/locations');

        $response->assertStatus(200);

        $arr = $response->json();
        $this->assertCount(3, $arr['data']);
    }

    public function testLocationValidation()
    {
        $data = [
            'name' => '', // Required field is empty
        ];

        $response = $this->withHeaders([
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/locations', $data);

        $response->assertStatus(422);
    }

    public function testBulkActions()
    {
        $locations = Location::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        $data = [
            'action' => 'archive',
            'ids' => $locations->pluck('hashed_id')->values()->toArray(),
        ];

        $response = $this->withHeaders([
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/locations/bulk', $data);

        $response->assertStatus(200);

        foreach ($locations as $location) {
            $this->assertNotNull($location->fresh()->deleted_at);
        }
    }

    public function testLocationRestore()
    {
        $location = Location::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'deleted_at' => now(),
        ]);

        $data = [
            'action' => 'restore',
            'ids' => [$location->hashed_id],
        ];

        $response = $this->withHeaders([
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/locations/bulk', $data);

        $response->assertStatus(200);

        $this->assertNull($location->fresh()->deleted_at);
    }
}
