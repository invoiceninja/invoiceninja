<?php

namespace Tests\Feature\Import\Quickbooks;

use Mockery;
use Tests\TestCase;
use ReflectionClass;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use Tests\MockAccountData;
use Illuminate\Support\Str;
use App\Models\ClientContact;
use App\DataMapper\ClientSync;
use App\DataMapper\InvoiceItem;
use App\DataMapper\InvoiceSync;
use App\DataMapper\ProductSync;
use App\Utils\Traits\MakesHash;
use App\Import\Providers\Quickbooks;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use QuickBooksOnline\API\Facades\Item;
use App\Import\Transformer\BaseTransformer;
use App\Services\Quickbooks\QuickbooksService;
use Illuminate\Routing\Middleware\ThrottleRequests;
use QuickBooksOnline\API\Facades\Invoice as QbInvoice;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class QuickbooksExportTest extends TestCase
{
    use MakesHash;
    use MockAccountData;

    protected QuickbooksService $qb;

    protected function setUp(): void
    {
        parent::setUp();      
        
        if(config('ninja.is_travis') || !config('services.quickbooks.client_id')){
            $this->markTestSkipped('No Quickbooks Client ID found');
        }

        $company = Company::find(1);

        if(!$company){
            $this->markTestSkipped('No company found');
        }

        $this->qb = new QuickbooksService($company);
    }

    public function testImportProducts()
    {
        $entity = 'Product';

        $entities = [
            'client' => 'Customer',
            'product' => 'Item',
            'invoice' => 'Invoice',
            // 'sales' => 'SalesReceipt',
        ];

        foreach($entities as $key => $entity)
        {
            $records = $this->qb->sdk()->fetchRecords($entity);

            $this->assertNotNull($records);

            switch ($key) {
                case 'product':
                    $this->qb->product->syncToNinja($records);
                    break;
                case 'client':
                    $this->qb->client->syncToNinja($records);
                    break;
                case 'invoice':
                    $this->qb->invoice->syncToNinja($records);
                    break;
                case 'sales':
                    $this->qb->invoice->syncToNinja($records);
                    break;
            }

        }



    }

}