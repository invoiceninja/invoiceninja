<?php

namespace Tests\Feature\Import\Quickbooks;

use Tests\TestCase;
use App\Import\Providers\Quickbooks;
use App\Import\Transformer\BaseTransformer;
use App\Import\Transformer\Quickbooks\ClientTransformer;
use App\Repositories\ClientRepository;
use App\Factory\ClientFactory;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\MockAccountData;
use Illuminate\Support\Facades\Cache;
use App\Http\Requests\Client\StoreClientRequest;
use Mockery;
use App\Models\Client;
use Illuminate\Support\Str;
use ReflectionClass;
use Illuminate\Support\Facades\Auth;


class QuickbooksTest extends TestCase
{
    use MakesHash;
    use MockAccountData;
    use DatabaseTransactions;

    protected $quickbooks;
    protected $data;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->withoutMiddleware(ThrottleRequests::class);
        config(['database.default' => config('ninja.db.default')]);
        $this->makeTestData();
       // $this->withoutExceptionHandling();
        Auth::setUser($this->user);
        $this->data = (json_decode( file_get_contents( base_path('tests/Feature/Import/customers.json') ), true))['Customer'];
        $hash = Str::random(32);
        Cache::put($hash.'-client', base64_encode(json_encode($this->data)), 360);

        $this->quickbooks = Mockery::mock(Quickbooks::class,[[
            'hash' => $hash,
            'column_map' => ['client' => ['mapping' => []]],
            'skip_header' => true,
            'import_type' => 'invoicely',
        ], $this->company ])->makePartial();
    }

    public function testImportCallsGetDataOnceForClient()
    {
        $this->quickbooks->shouldReceive('getData')
            ->once()
            ->with('client')
            ->andReturn($this->data);

        // Mocking the dependencies used within the client method
       
        $this->quickbooks->import('client');

        $this->assertArrayHasKey('clients', $this->quickbooks->entity_count);
        $this->assertGreaterThan(0, $this->quickbooks->entity_count['clients']);

        $base_transformer = new BaseTransformer($this->company);
        $this->assertTrue($base_transformer->hasClient('Sonnenschein Family Store'));
        $contact = $base_transformer->getClient('Amy\'s Bird Sanctuary','');
        $contact = Client::where('name','Amy\'s Bird Sanctuary')->first();
        $this->assertEquals('(650) 555-3311',$contact->phone);
        $this->assertEquals('Birds@Intuit.com',$contact->contacts()->first()->email);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
