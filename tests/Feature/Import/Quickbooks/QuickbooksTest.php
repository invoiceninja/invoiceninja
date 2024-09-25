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

        $this->makeTestData();
    }

    public function testCreateCustomerInQb()
    {
        $c = Company::whereNotNull('quickbooks')->first();

        $qb = new QuickbooksService($c);
    
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

        // $customer = $qb->sdk->createCustomer($customerData);

        $this->assertNotNull($customer);
    
        nlog($customer);


        // Send the create request to QuickBooks
        $resultingCustomerObj = $qb->sdk->Add($customer);

        // Check for errors
        $error = $qb->sdk->getLastError();
        if ($error) {
            $this->fail("The Customer could not be created: " . $error->getResponseBody());
        }

        nlog($resultingCustomerObj);

    }


    // public function testCreateInvoiceInQb()
    // {

    //     $c = Company::whereNotNull('quickbooks')->first();

    //     $qb = new QuickbooksService($c);

    //     //create QB customer

        

    //     //create ninja invoice

    //     //create QB invoice

    // }


    public function stubData()
    {
        
            // Inside the create method
            $lineItems = [
                [
                    "Amount" => 100.00,
                    "DetailType" => "SalesItemLineDetail",
                    "SalesItemLineDetail" => [
                        "ItemRef" => [
                            "value" => "1", // Replace with actual Item ID from QuickBooks
                            "name" => "Services"
                        ]
                    ]
                ]
            ];

            $invoiceData = [
                "Line" => $lineItems,
                "CustomerRef" => [
                    "value" => "1", // Replace with actual Customer ID from QuickBooks
                ],
                "BillEmail" => [
                    "Address" => "customer@example.com"
                ],
                "DueDate" => "2023-12-31",
                "TotalAmt" => 100.00,
                "DocNumber" => "INV-001"
            ];

            $invoice = QbInvoice::create($invoiceData);

    }
}
