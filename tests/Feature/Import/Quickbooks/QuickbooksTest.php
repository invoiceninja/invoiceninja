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

class QuickbooksTest extends TestCase
{
    use MakesHash;
    use MockAccountData;

    protected $quickbooks;
    protected $data;
    protected QuickbooksService $qb;
    protected $faker;

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

        $this->faker = \Faker\Factory::create();
    }



    public function createQbProduct()
    {
        $service_product = Product::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->company->owner()->id,
            'notes' => $this->faker->sentence(),
            'product_key' => \Illuminate\Support\Str::random(64),
            'tax_id' => 2,
        ]);


        $non_inventory_product = Product::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->company->owner()->id,
            'notes' => $this->faker->sentence(),
            'product_key' => \Illuminate\Support\Str::random(64),
            'tax_id' => 1,
        ]);


        $qb_product = Item::create([
            "Name" => $non_inventory_product->product_key,
            "Description" => $non_inventory_product->notes,
            "Active" => true,
            "FullyQualifiedName" => $non_inventory_product->product_key,
            "Taxable" => true,
            "UnitPrice" => $non_inventory_product->price,
            "Type" => "NonInventory",
            "IncomeAccountRef" => [
                "value" => $this->qb->settings->default_income_account, // Replace with your actual income account ID
            ],
            // "AssetAccountRef" => [
            //     "value" => "81", // Replace with your actual asset account ID
            //     "name" => "Inventory Asset"
            // ],
            // "InvStartDate" => date('Y-m-d'),
            // "QtyOnHand" => 100,
            "TrackQtyOnHand" => false,
            // "ExpenseAccountRef" => [
            //     "value" => "80", // Replace with your actual COGS account ID
            //     "name" => "Cost of Goods Sold"
            // ]
        ]);


        $qb_product = $this->qb->sdk->Add($qb_product);

        $sync = new ProductSync();
        $sync->qb_id = data_get($qb_product, 'Id.value');
        $non_inventory_product->sync = $sync;
        $non_inventory_product->save();

        $qb_service = Item::create([
            "Name" => $service_product->product_key,
            "Description" => $service_product->notes,
            "Active" => true,
            "FullyQualifiedName" => $service_product->product_key,
            "Taxable" => true,
            "UnitPrice" => $service_product->price,
            "Type" => "Service",
            "IncomeAccountRef" => [
                "value" => $this->qb->settings->default_income_account, // Replace with your actual income account ID
            ],
            "TrackQtyOnHand" => false,

        ]);


        $qb_service = $this->qb->sdk->Add($qb_service);

        $sync = new ProductSync();
        $sync->qb_id = data_get($qb_service, 'Id.value');
        $service_product->sync = $sync;
        $service_product->save();

        return [$non_inventory_product, $service_product];
    }

    public function createQbCustomer()
    {
        $client = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->company->owner()->id,
            'address1' => "10369 Ashton Avenue",
            'address2' => '',
            'city' => "Beverley Hills",
            'state' => "California",
            'postal_code' => "90210",
            'country_id' => 840,
            'shipping_address1' => "10369 Ashton Avenue",
            'address2' => '',
            'shipping_city' => "Beverley Hills",
            'shipping_state' => "California",
            'shipping_postal_code' => "90210",
            'shipping_country_id' => 840,
        ]);

        $contact = ClientContact::factory()->create([
            'user_id' => $this->company->owner()->id,
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'is_primary' => 1,
            'send_email' => true,
        ]);

        $customerData = [
            "DisplayName" => $client->present()->name(), // Required and must be unique
            "PrimaryEmailAddr" => [
                "Address" => $client->present()->email(),
            ],
            "PrimaryPhone" => [
                "FreeFormNumber" => $client->present()->phone()
            ],
            "CompanyName" => $client->present()->name(),
            "BillAddr" => [
                "Line1" => $client->address1 ?? '',
                "City" => $client->city ?? '',
                "CountrySubDivisionCode" => $client->state ?? '',
                "PostalCode" => $client->postal_code ?? '',
                "Country" => $client->country->iso_3166_3
            ],
            "ShipAddr" => [
                "Line1" => $client->shipping_address1 ?? '',
                "City" => $client->shipping_city ?? '',
                "CountrySubDivisionCode" => $client->shipping_state ?? '',
                "PostalCode" => $client->shipping_postal_code ?? '',
                "Country" => $client->shipping_country->iso_3166_3
            ],
            "GivenName" => $client->present()->first_name(),
            "FamilyName" => $client->present()->last_name(),
            "PrintOnCheckName" => $client->present()->primary_contact_name(),
            "Notes" => $client->public_notes,
            // "TaxIdentifier" => $client->vat_number ?? '', // Federal Employer Identification Number (EIN)
            "BusinessNumber" => $client->id_number ?? '',
            "Active" => $client->deleted_at ? false : true,
            "V4IDPseudonym" => $client->client_hash,
            "WebAddr" => $client->website ?? '',
        ];


        $customer = \QuickBooksOnline\API\Facades\Customer::create($customerData);

        // Send the create request to QuickBooks
        $resultingCustomerObj = $this->qb->sdk->Add($customer);

        $sync = new ClientSync();
        $sync->qb_id = data_get($resultingCustomerObj, 'Id.value');

        $client->sync = $sync;
        $client->save();

        return [$resultingCustomerObj, $client->id];

    }

    public function testCreateInvoiceInQb()
    {

        $this->company  = Company::whereNotNull('quickbooks')->first();

        $this->qb = new QuickbooksService($this->company);

        $customer = $this->createQbCustomer();
        
        //create ninja invoice
        $qb_invoice = $this->createQbInvoice($customer);


$this->assertNotNull($qb_invoice);

// sleep(5);

// $updatedInvoice = $this->qb->sdk->FindById('invoice', $qb_invoice->Id->value);



        nlog($updatedInvoice);
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

        $products = $this->createQbProduct();

        $non_inventory_product = $products[0];
        $service_product = $products[1];


        nlog(data_get($customer, 'Id.value'));

        $client = Client::find($client_id);

        $item_product = new InvoiceItem();
        $item_product->product_key = $non_inventory_product->product_key; // Changed from randomWord() to word()
        $item_product->notes = $non_inventory_product->notes;
        $item_product->quantity = 1;
        $item_product->cost = $non_inventory_product->price;
        $item_product->line_total = $non_inventory_product->price;
        $item_product->tax_name1 = 'CA Sales Tax';
        $item_product->tax_rate1 = 8;
        $item_product->type_id = '1';

        $item_service = new InvoiceItem();
        $item_service->product_key = $service_product->product_key; // Changed from randomWord() to word()
        $item_service->notes = $service_product->notes;
        $item_service->quantity = 1;
        $item_service->cost = $service_product->price;
        $item_service->line_total = $service_product->price;
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
            'number' => null,
        ]);

        $i->calc()->getInvoice()->service()->applyNumber()->markSent()->createInvitations()->save();

        $this->assertEquals(2, $i->status_id);
        // $this->assertEquals(30, $i->amount);
        $this->assertEquals(0, $i->paid_to_date);
        $this->assertEquals(0, $i->is_amount_discount);
        $this->assertEquals(0, $i->discount);
        // $this->assertEquals(30, $i->balance);

        $line_items = [];
        
        // $taxDetail = [
        //     "TotalTax" => 0,
        //     "TaxLine" => []
        // ];

        $line_num = 1;

        foreach($i->line_items as $line_item)
        {
            $product = Product::where('company_id', $this->company->id)
                                ->where('product_key', $line_item->product_key)
                                ->first();

            $this->assertNotNull($product);

            $line_items[] = [
                'LineNum' => $line_num,  // Add the line number
                'DetailType' => 'SalesItemLineDetail',
                'SalesItemLineDetail' => [
                    'ItemRef' => [
                        'value' => $product->sync->qb_id, 
                    ],
                    'Qty' => $line_item->quantity,
                    'UnitPrice' => $line_item->cost,
                    'TaxCodeRef' => [
                        'value' => (in_array($product->tax_id, [5, 8])) ? 'NON' : 'TAX',
                    ],
                ],
                'Description' => $line_item->notes,
                'Amount' => $line_item->line_total,
            ];

            // if($line_item->tax_rate1 > 0)
            // {
            //     $tax_line_detail = [
            //         "Amount" => $line_item->tax_amount,
            //         "DetailType" => "TaxLineDetail",
            //         "TaxLineDetail" => [
            //             "TaxRateRef" => [
            //                 "value" => $line_item->tax_rate1,
            //                 "name" => $line_item->tax_name1,
            //             ],
            //             "PercentBased" => true,
            //             "TaxPercent" => $line_item->tax_rate1,
            //             "NetAmountTaxable" => $line_item->line_total,
            //         ]
            //     ];

            //     $taxDetail['TaxLine'][] = $tax_line_detail;
            //     $taxDetail['TotalTax'] += $line_item->tax_amount;
            // }
                        
            // if ($line_item->tax_rate2 > 0) {
            //     $tax_line_detail = [
            //         "Amount" => $line_item->tax_amount,
            //         "DetailType" => "TaxLineDetail",
            //         "TaxLineDetail" => [
            //             "TaxRateRef" => [
            //                 "value" => $line_item->tax_rate2,
            //                 "name" => $line_items->tax_name2,
            //             ],
            //             "PercentBased" => true,
            //             "TaxPercent" => $line_item->tax_rate2,
            //             "NetAmountTaxable" => $line_item->line_total,
            //         ]
            //     ];

            //     $taxDetail['TaxLine'][] = $tax_line_detail;
            //     $taxDetail['TotalTax'] += $line_item->tax_amount;
            // }

            // if ($line_item->tax_rate3 > 0) {
            //     $tax_line_detail = [
            //         "Amount" => $line_item->tax_amount,
            //         "DetailType" => "TaxLineDetail",
            //         "TaxLineDetail" => [
            //             "TaxRateRef" => [
            //                 "value" => $line_item->tax_name3
            //             ],
            //             "PercentBased" => true,
            //             "TaxPercent" => $line_item->tax_rate3,
            //             "NetAmountTaxable" => $line_item->line_total,
            //         ]
            //     ];

            //     $taxDetail['TaxLine'][] = $tax_line_detail;
            //     $taxDetail['TotalTax'] += $line_item->tax_amount;
            // }



            $line_num++;

        }

        $invoiceData = [
            "Line" => $line_items,
            "CustomerRef" => [
                "value" => data_get($customer, 'Id.value')
            ],
            "BillEmail" => [
                "Address" => $client->present()->email()
            ],
            "TxnDate" => $i->date,
            "DueDate" => $i->due_date,
            "TotalAmt" => $i->amount,
            "DocNumber" => $i->number,
            "ApplyTaxAfterDiscount" => true,
            "PrintStatus" => "NeedToPrint",
            "EmailStatus" => "NotSet",
            "GlobalTaxCalculation" => "TaxExcluded",
            // "ApplyTaxAfterDiscount" => false,
            // "TxnTaxDetail" => $taxDetail,
            // "TxnTaxDetail" => [
                // "UseAutomatedSalesTax" => true,
                // "TxnTaxCodeRef" => [
                    // "value" => "SALES_TAX_STUB" // Use the appropriate tax code for your QuickBooks account
                //     "DefaultTaxRateRef" => [
                // ],
            // ]
            // "Note" => $this->invoice->public_notes,
        ];
        
        nlog($invoiceData);

        $invoice = \QuickBooksOnline\API\Facades\Invoice::create($invoiceData);        


        nlog($invoice);
        
        $qb_invoice =  $this->qb->sdk->Add($invoice);

        $sync = new InvoiceSync();
        $sync->qb_id = data_get($qb_invoice, 'Id.value');
        $i->sync = $sync;
        $i->save();

        return $qb_invoice;
    }


    private function getQuickBooksItemType($line_item): string
    {
        $typeMap = [
            '1' => 'NonInventory', // product
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

        // $this->company->clients()->forceDelete();
        // $this->company->products()->forceDelete();
        // $this->company->projects()->forceDelete();
        // $this->company->tasks()->forceDelete();
        // $this->company->vendors()->forceDelete();
        // $this->company->expenses()->forceDelete();
        // $this->company->purchase_orders()->forceDelete();
        // $this->company->bank_transaction_rules()->forceDelete();
        // $this->company->bank_transactions()->forceDelete();

        parent::tearDown();
    }

}
