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
use App\DataMapper\ClientSync;
use App\Utils\Traits\MakesHash;
use App\Import\Providers\Quickbooks;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use App\Import\Transformer\BaseTransformer;
use App\Services\Quickbooks\QuickbooksService;
use Illuminate\Routing\Middleware\ThrottleRequests;
use QuickBooksOnline\API\Facades\Invoice as QbInvoice;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class QuickbooksTest extends TestCase
{
    use MakesHash;
    use MockAccountData;
    use DatabaseTransactions;

    protected $quickbooks;
    protected $data;
    protected QuickbooksService $qb;

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

        $this->makeTestData();
    }

    public function createQbCustomer()
    {

        $customerData = [
            "DisplayName" => $this->client->present()->name(), // Required and must be unique
            "PrimaryEmailAddr" => [
                "Address" => $this->client->present()->email(),
            ],
            "PrimaryPhone" => [
                "FreeFormNumber" => $this->client->present()->phone()
            ],
            "CompanyName" => $this->client->present()->name(),
            "BillAddr" => [
                "Line1" => $this->client->address1 ?? '',
                "City" => $this->client->city ?? '',
                "CountrySubDivisionCode" => $this->client->state ?? '',
                "PostalCode" => $this->client->postal_code ?? '',
                "Country" => $this->client->country->iso_3166_3
            ],
            "ShipAddr" => [
                "Line1" => $this->client->shipping_address1 ?? '',
                "City" => $this->client->shipping_city ?? '',
                "CountrySubDivisionCode" => $this->client->shipping_state ?? '',
                "PostalCode" => $this->client->shipping_postal_code ?? '',
                "Country" => $this->client->shipping_country->iso_3166_3
            ],
            "GivenName" => $this->client->present()->first_name(),
            "FamilyName" => $this->client->present()->last_name(),
            "PrintOnCheckName" => $this->client->present()->primary_contact_name(),
            "Notes" => $this->client->public_notes,
            // "TaxIdentifier" => $this->client->vat_number ?? '', // Federal Employer Identification Number (EIN)
            "BusinessNumber" => $this->client->id_number ?? '',
            "Active" => $this->client->deleted_at ? false : true,
            "V4IDPseudonym" => $this->client->client_hash,
            "WebAddr" => $this->client->website ?? '',
        ];


        $customer = \QuickBooksOnline\API\Facades\Customer::create($customerData);

        // Send the create request to QuickBooks
        $resultingCustomerObj = $this->qb->sdk->Add($customer);

        return $resultingCustomerObj;

    }

    public function testCreateInvoiceInQb()
    {

        $this->company  = Company::whereNotNull('quickbooks')->first();

        $this->qb = new QuickbooksService($this->company);

        $customer = $this->createQbCustomer();
        
        //create ninja invoice
        $qb_invoice = $this->createQbInvoice($customer);

        $this->assertNotNull($qb_invoice);

        nlog($qb_invoice);
    }

    public function testCreateCustomerInQb()
    {
        $this->company = Company::whereNotNull('quickbooks')->first();
        $this->qb = new QuickbooksService($this->company);
    
        $resultingCustomerObj = $this->createQbCustomer();

        // Check for errors
        $error = $this->qb->sdk->getLastError();
        if ($error) {
            $this->fail("The Customer could not be created: " . $error->getResponseBody());
        }

        $qb_id = data_get($resultingCustomerObj, 'Id.value');

        $this->assertNotNull($qb_id);

        $sync = new ClientSync();
        $sync->qb_id = $qb_id;
        $this->client->sync = $sync;
        $this->client->save();

        $c = $this->client->fresh();

        $this->assertEquals($qb_id, $c->sync->qb_id);

    }

    public function createQbInvoice($customer)
    {
        
        $line_items = [];
        $line_num = 1;

        foreach($this->invoice->line_items as $line_item)
        {
            
            $line_items[] = [
                'LineNum' => $line_num,  // Add the line number
                'DetailType' => 'SalesItemLineDetail',
                'SalesItemLineDetail' => [
                    'ItemRef' => [
                        'value' => $line_item->product_key,
                    ],
                    'Qty' => $line_item->quantity,
                    'UnitPrice' => $line_item->cost,
                ],
                'Description' => $line_item->notes,
                // 'Type' => $this->getQuickBooksItemType($line_item),
                // 'PurchaseCost' => $line_item->product_cost,
                // 'Taxable' => $line_item->isTaxable(),
                'Amount' => $line_item->line_total,
            ];

            $line_num++;

        }

        $invoiceData = [
            "Line" => $line_items,
            "CustomerRef" => [
                "value" => data_get($customer, 'Id.value')
            ],
            "BillEmail" => [
                "Address" => $this->client->present()->email()
            ],
            "DueDate" => $this->invoice->due_date,
            "TotalAmt" => $this->invoice->amount,
            "DocNumber" => $this->invoice->number,
            // "Note" => $this->invoice->public_notes,
        ];
        
        $invoice = \QuickBooksOnline\API\Facades\Invoice::create($invoiceData);

        nlog($invoice);
        
        return $this->qb->sdk->Add($invoice);

    }


    private function getQuickBooksItemType($line_item): string
    {
        $typeMap = [
            '1' => 'Inventory', // product
            '2' => 'Service',   // service
            '3' => 'NonInventory', // unpaid gateway fee
            '4' => 'NonInventory', // paid gateway fee
            '5' => 'Service',   // late fee
            '6' => 'NonInventory', // expense
        ];

        return $typeMap[$line_item->type_id] ?? 'Service';
    }


}
