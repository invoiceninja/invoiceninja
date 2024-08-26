<?php

namespace Tests\Feature\Jobs\Import;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Cache;
use App\Jobs\Import\QuickbooksIngest;
use Illuminate\Support\Facades\Auth;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Str;
use Tests\MockAccountData;
use App\Models\Client;
use ReflectionClass;
use Tests\TestCase;

class QuickbooksIngestTest extends TestCase
{
    use MakesHash;
    use MockAccountData;
    use DatabaseTransactions;

    protected $quickbooks;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => config('ninja.db.default')]);
        $this->markTestSkipped('no bueno');
        $this->makeTestData();
        $this->withoutExceptionHandling();
        Auth::setUser($this->user);

    }

    /**
     * A basic feature test example.
     */
    public function testCanQuickbooksIngest(): void
    {
        $data = (json_decode(file_get_contents(base_path('tests/Feature/Import/customers.json')), true))['Customer'];
        $hash = Str::random(32);
        Cache::put($hash.'-client', base64_encode(json_encode($data)), 360);
        QuickbooksIngest::dispatch([
            'hash' => $hash,
            'column_map' => ['client' => ['mapping' => []]],
            'skip_header' => true,
            'import_types' => ['client'],
        ], $this->company)->handle();
        $this->assertTrue(Client::withTrashed()->where(['company_id' => $this->company->id, 'name' => "Freeman Sporting Goods"])->exists());
    }
}
