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
use App\DataMapper\InvoiceItem;
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

        $sync = new ClientSync();
        $sync->qb_id = data_get($resultingCustomerObj, 'Id.value');

        $this->client->sync = $sync;
        $this->client->save();

        return [$resultingCustomerObj, $this->client->id];

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

        $qb_id = data_get($resultingCustomerObj[0], 'Id.value');

        $this->assertNotNull($qb_id);

        $c = Client::find($resultingCustomerObj[1]);
        
        $this->assertEquals($qb_id, $c->sync->qb_id);

    }

    public function createQbInvoice($customer_payload)
    {
        $customer = $customer_payload[0];
        $client_id = $customer_payload[1];

        nlog(data_get($customer, 'Id.value'));

        $client = Client::find($client_id);

        nlog($client);
        
        $item_product = new InvoiceItem();
        $item_product->product_key = $this->faker->word(); // Changed from randomWord() to word()
        $item_product->notes = $this->faker->sentence();
        $item_product->quantity = 1;
        $item_product->cost = 10;
        $item_product->line_total = 10;
        $item_product->type_id = '1';

        $item_service = new InvoiceItem();
        $item_service->product_key = $this->faker->word(); // Changed from randomWord() to word()
        $item_service->notes = $this->faker->sentence();
        $item_service->quantity = 1;
        $item_service->cost = 20;
        $item_service->line_total = 20;
        $item_service->type_id = '2';


        $i = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $client->user_id,
            'client_id' => $client->id,
            'date' => '2024-09-01',
            'due_date' => '2024-09-30',
            'public_notes' => 'Test invoice',
            'tax_name1' =>  '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
            'amount' => 30,
            'paid_to_date' => 0,
            'is_amount_discount' => 0,
            'discount' => 0,
            'line_items' => [$item_product, $item_service],
        ]);

        $i->calc()->getInvoice()->service()->applyNumber()->markSent()->createInvitations()->save();

        $this->assertEquals(2, $i->status_id);
        $this->assertEquals(30, $i->amount);
        $this->assertEquals(0, $i->paid_to_date);
        $this->assertEquals(0, $i->is_amount_discount);
        $this->assertEquals(0, $i->discount);
        $this->assertEquals(30, $i->balance);

        $line_items = [];
        $line_num = 1;

        foreach($i->line_items as $line_item)
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
                "Address" => $c->present()->email()
            ],
            "TxnDate" => $i->date,
            "DueDate" => $i->due_date,
            "TotalAmt" => $i->amount,
            "DocNumber" => $i->number,
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

    protected function tearDown(): void
    {

        $this->company->clients()->forceDelete();
        $this->company->products()->forceDelete();
        $this->company->projects()->forceDelete();
        $this->company->tasks()->forceDelete();
        $this->company->vendors()->forceDelete();
        $this->company->expenses()->forceDelete();
        $this->company->purchase_orders()->forceDelete();
        $this->company->bank_transaction_rules()->forceDelete();
        $this->company->bank_transactions()->forceDelete();

        parent::tearDown();
    }

}
