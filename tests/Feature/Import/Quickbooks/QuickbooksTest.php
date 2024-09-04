<?php

namespace Tests\Feature\Import\Quickbooks;

use Tests\TestCase;
use App\Import\Providers\Quickbooks;
use App\Import\Transformer\BaseTransformer;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\MockAccountData;
use Illuminate\Support\Facades\Cache;
use Mockery;
use App\Models\Client;
use App\Models\Product;
use App\Models\Invoice;
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
        $this->markTestSkipped('no bueno');
        
    }

    public function testCustomerSync()
    {
        $data = (json_decode(file_get_contents(base_path('tests/Feature/Import/Quickbooks/customers.json')), false));
    }
}
