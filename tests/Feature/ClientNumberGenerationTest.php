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

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Client;
use Tests\MockAccountData;
use App\Factory\ClientFactory;
use App\DataMapper\ClientSettings;
use App\Repositories\ClientRepository;
use App\Repositories\ClientContactRepository;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 *
 *  App\Repositories\ClientRepository
 */
class ClientNumberGenerationTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);

        $this->makeTestData();
    }

    private function setCompanyClientNumberPattern(string $pattern): void
    {
        $settings = $this->company->settings;
        $settings->client_number_pattern = $pattern;

        $this->company->settings = $settings;
        $this->company->save();
    }

    private function saveClient(ClientRepository $repository, string $name): Client
    {
        return $repository->save(
            ['name' => $name, 'settings' => ClientSettings::defaults()],
            ClientFactory::create($this->company->id, $this->user->id)
        );
    }

    public function testConsecutiveClientsReceiveDistinctNumbers()
    {
        /** @var ClientRepository $repository */
        $repository = app()->make(ClientRepository::class);

        $first = $this->saveClient($repository, 'Alpha');
        $second = $this->saveClient($repository, 'Beta');

        $this->assertNotEmpty($first->fresh()->number);
        $this->assertNotEmpty($second->fresh()->number);
        $this->assertNotEquals($first->fresh()->number, $second->fresh()->number);
    }

    public function testConsecutiveClientsAreNumberedWithAUserVarPattern()
    {
        $this->user->custom_value1 = '';
        $this->user->save();

        $this->setCompanyClientNumberPattern('{$user_custom1}');

        /** @var ClientRepository $repository */
        $repository = app()->make(ClientRepository::class);

        $first = $this->saveClient($repository, 'Alpha');
        $second = $this->saveClient($repository, 'Beta');

        $this->assertNotEmpty($first->fresh()->number);
        $this->assertNotEmpty($second->fresh()->number);
        $this->assertNotEquals($first->fresh()->number, $second->fresh()->number);
    }

    public function testClientIsNumberedWhenAnEmptyNumberAlreadyExists()
    {
        $this->user->custom_value1 = '';
        $this->user->save();

        $this->setCompanyClientNumberPattern('{$user_custom1}');

        $poisoned = ClientFactory::create($this->company->id, $this->user->id);
        $poisoned->settings = ClientSettings::defaults();
        $poisoned->number = '';
        $poisoned->save();

        /** @var ClientRepository $repository */
        $repository = app()->make(ClientRepository::class);

        $client = $this->saveClient($repository, 'Alpha');

        $this->assertNotEmpty($client->fresh()->number);
        $this->assertNotEquals('', $client->fresh()->number);
    }

    public function testSuppliedClientNumberIsRespected()
    {
        /** @var ClientRepository $repository */
        $repository = app()->make(ClientRepository::class);

        $client = $repository->save(
            ['name' => 'Alpha', 'number' => 'SUPPLIED-99', 'settings' => ClientSettings::defaults()],
            ClientFactory::create($this->company->id, $this->user->id)
        );

        $this->assertEquals('SUPPLIED-99', $client->fresh()->number);
    }

    public function testExistingNumberIsNotRegeneratedOnUpdate()
    {
        /** @var ClientRepository $repository */
        $repository = app()->make(ClientRepository::class);

        $client = $this->saveClient($repository, 'Alpha');
        $number = $client->fresh()->number;

        $counter = $this->company->fresh()->settings->client_number_counter;

        $repository->save(['name' => 'Renamed'], $client);

        $this->assertEquals($number, $client->fresh()->number);
        $this->assertEquals($counter, $this->company->fresh()->settings->client_number_counter);
    }

    public function testExhaustedNumberGenerationUniquifiesTheNumber()
    {
        $taken = ClientFactory::create($this->company->id, $this->user->id);
        $taken->settings = ClientSettings::defaults();
        $taken->number = 'FIXED-0001';
        $taken->save();

        /** @var ClientRepository $repository */
        $repository = new class (app()->make(ClientContactRepository::class)) extends ClientRepository {
            public function getNextClientNumber(Client $client): string
            {
                return 'FIXED-0001';
            }
        };

        $client = $this->saveClient($repository, 'Alpha');

        $number = $client->fresh()->number;

        $this->assertStringStartsWith('FIXED-0001_', $number);
        $this->assertEquals(strlen('FIXED-0001_') + 5, strlen($number));
        $this->assertFalse($client->isDirty('number'));
    }

}
