<?php

namespace Tests\Feature\Client;

use App\Jobs\Client\CheckVat;
use App\Models\Client;
use App\Models\Company;
use App\Observers\ClientObserver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClientObserverVatTest extends TestCase
{
    private $originalDispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.vat_observer_test', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        DB::setDefaultConnection('vat_observer_test');

        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('company_id');
            $table->string('country_id');
            $table->string('vat_number')->nullable();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('event_id');
            $table->boolean('is_deleted')->default(false);
            $table->softDeletes();
        });

        // Keep the real observer and transaction callbacks, excluding unrelated
        // Scout listeners from this isolated persistence test.
        $this->originalDispatcher = Client::getEventDispatcher();
        Client::setEventDispatcher(clone $this->originalDispatcher);
        Client::flushEventListeners();
        Client::observe(ClientObserver::class);
        Bus::fake([CheckVat::class]);
    }

    protected function tearDown(): void
    {
        if ($this->originalDispatcher) {
            Client::setEventDispatcher($this->originalDispatcher);
        }
        Mockery::close();
        parent::tearDown();
    }

    private function client(?string $vat = 'DE123456789'): Client
    {
        $company = Mockery::mock(Company::class)->makePartial();
        $company->calculate_taxes = true;
        // This also proves updated() actually ran, including after commit.
        $company->shouldReceive('shouldPushToQuickbooks')->once()->with('client')->andReturn(false);

        $client = new Client();
        $client->forceFill([
            'company_id' => 1,
            'country_id' => '276',
            'vat_number' => $vat,
            'name' => 'VAT comparison test',
        ]);
        $client->setRelation('company', $company);
        $client->saveQuietly();

        return $client;
    }

    public function test_unchanged_vat_does_not_dispatch_a_check(): void
    {
        $client = $this->client();
        $client->name = 'Updated name';

        $this->assertFalse($client->getOriginal('vat_number') != $client->vat_number);
        $client->save();

        Bus::assertNotDispatched(CheckVat::class);
        $this->assertFalse($client->wasChanged('vat_number'));
    }

    public function test_changed_vat_dispatches_without_a_transaction(): void
    {
        $client = $this->client();
        $client->vat_number = 'DE987654321';

        $this->assertTrue($client->getOriginal('vat_number') != $client->vat_number);
        $client->save();

        Bus::assertDispatchedTimes(CheckVat::class, 1);
        $this->assertTrue($client->wasChanged('vat_number'));
    }

    public function test_changed_vat_dispatches_after_commit(): void
    {
        $client = $this->client();

        DB::transaction(function () use ($client) {
            $client->vat_number = 'DE987654321';
            $this->assertTrue($client->getOriginal('vat_number') != $client->vat_number);
            $client->save();

            Bus::assertNotDispatched(CheckVat::class);
            $this->assertSame($client->vat_number, $client->getOriginal('vat_number'));
            $this->assertTrue($client->wasChanged('vat_number'));
        });

        $this->assertSame('DE987654321', DB::table('clients')->value('vat_number'));
        Bus::assertDispatchedTimes(CheckVat::class, 1);
        $this->assertTrue($client->wasChanged('vat_number'));
    }

    #[DataProvider('vatTransitions')]
    public function test_empty_vat_transitions(?string $before, ?string $after, bool $shouldDispatch, bool $transaction): void
    {
        $client = $this->client($before);
        $save = function () use ($client, $after, $transaction) {
            $client->vat_number = $after;
            $client->save();

            if ($transaction) {
                Bus::assertNotDispatched(CheckVat::class);
            }
        };

        if ($transaction) {
            DB::transaction($save);
        } else {
            $save();
        }

        if ($shouldDispatch) {
            Bus::assertDispatchedTimes(CheckVat::class, 1);
        } else {
            Bus::assertNotDispatched(CheckVat::class);
        }
    }

    public static function vatTransitions(): iterable
    {
        foreach ([false, true] as $transaction) {
            $suffix = $transaction ? ' after commit' : ' without transaction';
            yield 'null to empty'.$suffix => [null, '', false, $transaction];
            yield 'empty to null'.$suffix => ['', null, false, $transaction];
            yield 'null to VAT'.$suffix => [null, 'DE123456789', true, $transaction];
            yield 'empty to VAT'.$suffix => ['', 'DE123456789', true, $transaction];
            yield 'VAT to null'.$suffix => ['DE123456789', null, true, $transaction];
            yield 'VAT to empty'.$suffix => ['DE123456789', '', true, $transaction];
        }
    }
}
