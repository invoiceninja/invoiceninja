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
use App\Models\Company;
use App\Models\Product;
use App\Models\Invoice;
use App\Services\Quickbooks\QuickbooksService;
use Illuminate\Support\Str;
use ReflectionClass;
use Illuminate\Support\Facades\Auth;
use QuickBooksOnline\API\Facades\Invoice as QbInvoice;

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
        
        if(config('ninja.is_travis'))
        {
            $this->markTestSkipped('No need to run this test on Travis');
        }
        elseif(Company::whereNotNull('quickbooks')->count() == 0){
            $this->markTestSkipped('No need to run this test on Travis');
        }
    }

    public function testCreateInvoiceInQb()
    {

        $c = Company::whereNotNull('quickbooks')->first();

        $qb = new QuickbooksService($c);

        

    }
}
