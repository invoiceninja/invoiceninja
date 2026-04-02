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

namespace Tests\Unit\Services\Quickbooks;

use Mockery;
use ReflectionClass;
use Tests\TestCase;
use Tests\MockAccountData;
use App\Models\Client;
use App\DataMapper\ClientSync;
use App\DataMapper\QuickbooksSettings;
use App\Services\Quickbooks\Models\QbClient;
use App\Services\Quickbooks\QuickbooksService;
use QuickBooksOnline\API\DataService\DataService;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class QbClientDuplicateNameTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTestData();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeServiceWithMockSdk(): array
    {
        $this->company->quickbooks = new QuickbooksSettings([
            'accessTokenKey' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'realmID' => 'test-realm',
            'accessTokenExpiresAt' => time() + 3600,
            'refreshTokenExpiresAt' => time() + 86400,
            'baseURL' => 'https://sandbox-quickbooks.api.intuit.com',
            'companyName' => 'Test Company',
            'settings' => [],
        ]);
        $this->company->save();

        $this->app['config']->set('services.quickbooks.client_id', null);

        $service = new QuickbooksService($this->company);

        $mockSdk = Mockery::mock(DataService::class)->makePartial();
        $ref = new ReflectionClass($service);
        $prop = $ref->getProperty('sdk');
        $prop->setValue($service, $mockSdk);

        return [$service, $mockSdk];
    }

    private function makeClient(string $name): Client
    {
        $client = \App\Factory\ClientFactory::create($this->company->id, $this->user->id);
        $client->name = $name;
        $client->saveQuietly();

        $contact = \App\Factory\ClientContactFactory::create($this->company->id, $this->user->id);
        $contact->client_id = $client->id;
        $contact->first_name = 'Test';
        $contact->last_name = 'Contact';
        $contact->email = 'test-' . uniqid() . '@test.com';
        $contact->is_primary = true;
        $contact->saveQuietly();

        return $client->fresh();
    }

    /**
     * Test that single quotes in client names are escaped in the QB query.
     * Without escaping, a name like O'Brien breaks the query and causes 6240 errors.
     */
    public function test_find_client_by_name_escapes_single_quotes(): void
    {
        [$service, $mockSdk] = $this->makeServiceWithMockSdk();

        $nameWithQuote = "O'Brien Construction";
        $expectedQuery = "SELECT Id FROM Customer WHERE DisplayName = 'O\\'Brien Construction'";

        $mockSdk->shouldReceive('Query')
            ->once()
            ->with($expectedQuery, 1, 1)
            ->andReturn([(object) ['Id' => '999']]);

        $mockSdk->shouldReceive('Add')->never();

        $client = $this->makeClient($nameWithQuote);

        $qbClient = new QbClient($service);
        $qb_id = $qbClient->createQbClient($client);

        $this->assertEquals('999', $qb_id);

        $client->refresh();
        $this->assertEquals('999', $client->sync->qb_id);
    }

    /**
     * Test that names with multiple single quotes are all escaped.
     */
    public function test_find_client_by_name_escapes_multiple_single_quotes(): void
    {
        [$service, $mockSdk] = $this->makeServiceWithMockSdk();

        $nameWithQuotes = "Smith's & O'Malley's LLC";
        $expectedQuery = "SELECT Id FROM Customer WHERE DisplayName = 'Smith\\'s & O\\'Malley\\'s LLC'";

        $mockSdk->shouldReceive('Query')
            ->once()
            ->with($expectedQuery, 1, 1)
            ->andReturn([(object) ['Id' => '888']]);

        $mockSdk->shouldReceive('Add')->never();

        $client = $this->makeClient($nameWithQuotes);

        $qbClient = new QbClient($service);
        $qb_id = $qbClient->createQbClient($client);

        $this->assertEquals('888', $qb_id);
    }

    /**
     * Test that the 6240 duplicate name error is caught and resolved
     * by finding and linking the existing QB customer.
     */
    public function test_duplicate_name_error_6240_is_recovered(): void
    {
        [$service, $mockSdk] = $this->makeServiceWithMockSdk();

        $clientName = 'Acme Corp';

        // findClientIdByName returns nothing on first call (before Add)
        // Then on the recovery attempt in catch block, it finds the customer
        $mockSdk->shouldReceive('Query')
            ->with("SELECT Id FROM Customer WHERE DisplayName = 'Acme Corp'", 1, 1)
            ->twice()
            ->andReturn(null, [(object) ['Id' => '777']]);

        // Add throws 6240 duplicate error (as QB would)
        $mockSdk->shouldReceive('Add')
            ->once()
            ->andThrow(new \Exception(
                'Request is not made successful. Response Code:[400] with body: [' .
                '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
                '<IntuitResponse xmlns="http://schema.intuit.com/finance/v3">' .
                '<Fault type="ValidationFault"><Error code="6240">' .
                '<Message>Duplicate Name Exists Error</Message>' .
                '<Detail>The name supplied already exists. : null</Detail>' .
                '</Error></Fault></IntuitResponse>].'
            ));

        $client = $this->makeClient($clientName);

        $qbClient = new QbClient($service);
        $qb_id = $qbClient->createQbClient($client);

        $this->assertEquals('777', $qb_id);

        // Verify client was linked
        $client->refresh();
        $this->assertEquals('777', $client->sync->qb_id);
    }

    /**
     * Test that a 6240 error with a name containing single quotes
     * is still recovered (escaping + catch block work together).
     */
    public function test_duplicate_name_error_with_single_quote_is_recovered(): void
    {
        [$service, $mockSdk] = $this->makeServiceWithMockSdk();

        $clientName = "O'Brien LLC";
        $escapedQuery = "SELECT Id FROM Customer WHERE DisplayName = 'O\\'Brien LLC'";

        // First call: findClientIdByName returns nothing
        // Second call (in catch recovery): finds the customer
        $mockSdk->shouldReceive('Query')
            ->with($escapedQuery, 1, 1)
            ->twice()
            ->andReturn(null, [(object) ['Id' => '555']]);

        // Add throws 6240 duplicate error
        $mockSdk->shouldReceive('Add')
            ->once()
            ->andThrow(new \Exception(
                'Response Code:[400] with body: [<Error code="6240">' .
                '<Message>Duplicate Name Exists Error</Message></Error>]'
            ));

        $client = $this->makeClient($clientName);

        $qbClient = new QbClient($service);
        $qb_id = $qbClient->createQbClient($client);

        $this->assertEquals('555', $qb_id);

        $client->refresh();
        $this->assertEquals('555', $client->sync->qb_id);
    }

    /**
     * Test 6240 when name collides with a Vendor/Employee (not a Customer).
     * The Customer lookup returns nothing, so we retry Add() with a unique DisplayName.
     */
    public function test_duplicate_name_vendor_collision_retries_with_unique_name(): void
    {
        [$service, $mockSdk] = $this->makeServiceWithMockSdk();

        $clientName = 'SORCA';

        // First call (before Add): no existing Customer found
        // Second call (in catch recovery): still no Customer found (it's a Vendor)
        $mockSdk->shouldReceive('Query')
            ->with("SELECT Id FROM Customer WHERE DisplayName = 'SORCA'", 1, 1)
            ->twice()
            ->andReturn(null, null);

        // First Add: throws 6240 (name collides with Vendor)
        // Second Add: succeeds with unique name "SORCA (C)"
        $mockSdk->shouldReceive('Add')
            ->twice()
            ->andReturnUsing(function () {
                static $call = 0;
                $call++;
                if ($call === 1) {
                    throw new \Exception(
                        'Request is not made successful. Response Code:[400] with body: [' .
                        '<Error code="6240"><Message>Duplicate Name Exists Error</Message>' .
                        '<Detail>The name supplied already exists. : null</Detail></Error>]'
                    );
                }
                return (object) ['Id' => '456'];
            });

        $client = $this->makeClient($clientName);

        $qbClient = new QbClient($service);
        $qb_id = $qbClient->createQbClient($client);

        $this->assertEquals('456', $qb_id);

        $client->refresh();
        $this->assertEquals('456', $client->sync->qb_id);
    }

    /**
     * Test that non-6240 errors are still thrown (not swallowed).
     */
    public function test_non_duplicate_errors_are_still_thrown(): void
    {
        [$service, $mockSdk] = $this->makeServiceWithMockSdk();

        $mockSdk->shouldReceive('Query')
            ->with("SELECT Id FROM Customer WHERE DisplayName = 'Test Client'", 1, 1)
            ->once()
            ->andReturn(null);

        $mockSdk->shouldReceive('Add')
            ->once()
            ->andThrow(new \Exception('Response Code:[401] Unauthorized'));

        $client = $this->makeClient('Test Client');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unauthorized');

        $qbClient = new QbClient($service);
        $qbClient->createQbClient($client);
    }

    /**
     * Test that a normal client name without quotes works as before.
     */
    public function test_client_without_special_chars_creates_normally(): void
    {
        [$service, $mockSdk] = $this->makeServiceWithMockSdk();

        // No existing customer found
        $mockSdk->shouldReceive('Query')
            ->with("SELECT Id FROM Customer WHERE DisplayName = 'Normal Corp'", 1, 1)
            ->once()
            ->andReturn(null);

        // Add succeeds
        $mockSdk->shouldReceive('Add')
            ->once()
            ->andReturn((object) ['Id' => '123']);

        $client = $this->makeClient('Normal Corp');

        $qbClient = new QbClient($service);
        $qb_id = $qbClient->createQbClient($client);

        $this->assertEquals('123', $qb_id);

        $client->refresh();
        $this->assertEquals('123', $client->sync->qb_id);
    }
}
