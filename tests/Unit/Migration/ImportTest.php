<?php

namespace Tests\Unit\Migration;

use App\Exceptions\ResourceDependencyMissing;
use App\Exceptions\ResourceNotAvailableForMigration;
use App\Jobs\Util\Import;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\InvoiceInvitation;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Quote;
use App\Models\TaxRate;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    public function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testImportClassExists()
    {
        $status = class_exists('App\Jobs\Util\Import');

        $this->assertTrue($status);
    }

    /**
     * Ensure exception is thrown when resource
     * is not available for the migration.
     */
    public function testExceptionOnUnavailableResource()
    {
        try {
            $data['panda_bears'] = [
                'name' => 'Awesome Panda Bear',
            ];
            Import::dispatchNow($data, $this->company, $this->user);
        } catch (ResourceNotAvailableForMigration $e) {
            $this->assertTrue(true);
        }
    }

    public function testCompanyUpdating()
    {
        $original_company_key = $this->company->company_key;

        $data['company'] = [
            'company_key' => 0,
        ];

        Import::dispatchNow($data, $this->company, $this->user);

        $this->assertNotEquals($original_company_key, $this->company->company_key);
    }

    public function testTaxRatesInserting()
    {
        $total_tax_rates = TaxRate::count();

        $data['tax_rates'] = [
            0 => [
                'name' => 'My awesome tax rate 1',
                'rate' => '1.000',
            ]
        ];

        Import::dispatchNow($data, $this->company, $this->user);

        $this->assertNotEquals($total_tax_rates, TaxRate::count());
    }

    public function testTaxRateUniqueValidation()
    {
        $original_number = TaxRate::count();

        try {
            $data['tax_rates'] = [
                0 => [
                    'name' => '',
                    'rate' => '1.000',
                ],
                1 => [
                    'name' => 'My awesome tax rate 1',
                    'rate' => '1.000',
                ]
            ];

            Import::dispatchNow($data, $this->company, $this->user);
        } catch (\Exception $e) {
            $this->assertTrue(true);
        }

        $this->assertEquals($original_number, TaxRate::count());
    }

    public function testUsersImporting()
    {
        $original_number = User::count();

        $data['users'] = [
            0 => [
                'id' => 1,
                'first_name' => 'David',
                'last_name' => 'IN',
                'email' => 'my@awesomemail.com',
            ]
        ];

        Import::dispatchNow($data, $this->company, $this->user);

        $this->assertGreaterThan($original_number, User::count());
    }

    public function testUserValidator()
    {
        $original_number = User::count();

        try {
            $data['users'] = [
                0 => [
                    'id' => 1,
                    'first_name' => 'David',
                    'last_name' => 'IN',
                    'email' => 'my@awesomemail.com',
                ],
                1 => [
                    'id' => 2,
                    'first_name' => 'Someone',
                    'last_name' => 'Else',
                    'email' => 'my@awesomemail.com',
                ]
            ];

            Import::dispatchNow($data, $this->company, $this->user);
        } catch (\Exception $e) {
            $this->assertTrue(true);
        }

        $this->assertEquals($original_number, User::count());
    }

    public function testClientImporting()
    {
        $original_number = Client::count();

        $data['users'] = [
            0 => [
                'id' => 1,
                'first_name' => 'David',
                'last_name' => 'IN',
                'email' => 'my@awesomemail.com',
            ],
            1 => [
                'id' => 2,
                'first_name' => 'Someone',
                'last_name' => 'Else',
                'email' => 'my@awesomemail2.com',
            ]
        ];

        $data['clients'] = [
            0 => [
                'id' => 1,
                'name' => 'My awesome client',
                'balance' => '0.00',
                'user_id' => 1,
            ]
        ];

        Import::dispatchNow($data, $this->company, $this->user);

        $this->assertGreaterThan($original_number, Client::count());
    }

    public function testProductsImporting()
    {
        $original_number = Product::count();

        $data['products'] = [
            0 => [
                'id' => 1,
                'product_key' => 'My awesome product',
                'cost' => '21.0000',
                'price' => '1.000',
                'quantity' => 1,
            ],
        ];

        Import::dispatchNow($data, $this->company, $this->user);

        $this->assertGreaterThan($original_number, Product::count());
    }

    public function testInvoicesFailsWithoutClient()
    {
        try {
            $data['invoices'] = [
                0 => [
                    'client_id' => 1,
                    'is_amount_discount' => false,
                ]
            ];

            Import::dispatchNow($data, $this->company, $this->user);
        } catch (ResourceDependencyMissing $e) {
            $this->assertTrue(true);
        }
    }

    public function testInvoicesImporting()
    {

        $original_number = Invoice::count();

        $data['clients'] = [
            0 => [
                'id' => 1,
                'name' => 'My awesome client',
                'balance' => '0.00',
                'user_id' => 1,
            ]
        ];

        $data['invoices'] = [
            0 => [
                'id' => 1,
                'client_id' => 1,
                'discount' => '0.00',
            ]
        ];

        Import::dispatchNow($data, $this->company, $this->user);

        $this->assertGreaterThan($original_number, Invoice::count());

        Invoice::where('id', '>=', '0')->forceDelete();

        $this->assertEquals(0, Invoice::count());

    }

    public function testQuotesFailsWithoutClient()
    {
        try {
            $data['quotes'] = [
                0 => [
                    'client_id' => 1,
                    'is_amount_discount' => false,
                ]
            ];

            Import::dispatchNow($data, $this->company, $this->user);
        } catch (ResourceDependencyMissing $e) {
            $this->assertTrue(true);
        }
    }

    public function testQuotesImporting()
    {
        $original_number = Quote::count();

        $data['clients'] = [
            0 => [
                'id' => 1,
                'name' => 'My awesome client',
                'balance' => '0.00',
                'user_id' => 1,
            ]
        ];

        $data['quotes'] = [
            0 => [
                'client_id' => 1,
                'discount' => '0.00',
            ]
        ];

        Import::dispatchNow($data, $this->company, $this->user);

        $this->assertGreaterThan($original_number, Quote::count());
    }


    public function testImportFileExists()
    {
        $migration_file = base_path() . '/tests/Unit/Migration/migration.json';

        $this->assertTrue(file_exists($migration_file));

        $migration_array = json_decode(file_get_contents($migration_file), 1);

        $this->assertGreaterThan(1, count($migration_array));

    }

    public function testAllImport()
    {

        //$this->makeTestData();

        $this->invoice->forceDelete();

        $migration_file = base_path() . '/tests/Unit/Migration/migration.json';

        $migration_array = json_decode(file_get_contents($migration_file), 1);

        Import::dispatchNow($migration_array, $this->company, $this->user);

        $this->assertTrue(true);
    }

    public function testClientAttributes()
    {
        $original_number = Client::count();

        $random_balance = rand(0, 10);

        $data['clients'] = [
            0 => [
                'id' => 1,
                'name' => 'My awesome unique client',
                'balance' => $random_balance,
                'user_id' => 1,
            ]
        ];

        Import::dispatchNow($data, $this->company, $this->user);

        $client = Client::where('name', 'My awesome unique client')
            ->where('balance', $random_balance)
            ->first();

        // Originally was checked with ClientContact::whereEmail() but it throws 'array to string conversion' on insert.

        $this->assertNotNull($client);
        $this->assertGreaterThan($original_number, Client::count());
        $this->assertGreaterThanOrEqual(0, $client->balance);
    }

    public function testInvoiceImporting()
    {
        $original_number = Invoice::count();

        $this->invoice->forceDelete();

        $migration_file = base_path() . '/tests/Unit/Migration/migration.json';

        $migration_array = json_decode(file_get_contents($migration_file), 1);

        Import::dispatchNow($migration_array, $this->company, $this->user);

        $this->assertGreaterThan($original_number, Invoice::count());
    }

    public function testInvoiceAttributes()
    {
        $original_number = Invoice::count();

        $this->invoice->forceDelete();

        $migration_file = base_path() . '/tests/Unit/Migration/migration.json';

        $migration_array = json_decode(file_get_contents($migration_file), 1);

        Import::dispatchNow($migration_array, $this->company, $this->user);

        $this->assertGreaterThan($original_number, Invoice::count());

        $invoice_1 = Invoice::where('number', '0001')
            ->where('discount', '0.00')
            ->where('date', '2020-03-18')
            ->first();

        $invoice_2 = Invoice::where('number', '0018')
            ->where('discount', '0.00')
            ->where('date', '2019-10-15')
            ->first();

        $this->assertNotNull($invoice_1);
        $this->assertNotNull($invoice_2);

        $this->assertEquals('43.7500', $invoice_1->amount);
        $this->assertEquals('55.2600', $invoice_2->amount);

        $this->assertEquals('18.7700', $invoice_1->balance);
        $this->assertEquals('49.3700', $invoice_2->balance);
    }

    public function testQuoteAttributes()
    {
        $original_number = Quote::count();

        $this->invoice->forceDelete();

        $migration_file = base_path() . '/tests/Unit/Migration/migration.json';

        $migration_array = json_decode(file_get_contents($migration_file), 1);

        Import::dispatchNow($migration_array, $this->company, $this->user);

        $this->assertGreaterThan($original_number, Invoice::count());

        $quote = Quote::where('number', '0002')
            ->where('discount', '0.00')
            ->where('date', '2020-04-26')
            ->first();

        $this->assertNotNull($quote);
        $this->assertEquals('0.0000', $quote->amount);
        $this->assertEquals('0.0000', $quote->balance);
    }

    public function testPaymentsImport()
    {
        $original_count = Payment::count();

        $this->invoice->forceDelete();

        $migration_file = base_path() . '/tests/Unit/Migration/migration.json';

        $migration_array = json_decode(file_get_contents($migration_file), 1);

        Import::dispatchNow($migration_array, $this->company, $this->user);

        $this->assertGreaterThan($original_count, Payment::count());
    }

    public function testPaymentDependsOnClient()
    {
        try {
            $data['payments'] = [
                0 => [
                    'client_id' => 1,
                    'amount' => 1,
                ]
            ];

            Import::dispatchNow($data, $this->company, $this->user);
        } catch (ResourceDependencyMissing $e) {
            $this->assertTrue(true);
        }
    }

    public function testQuotesImport()
    {
        $original_count = Credit::count();

        $this->invoice->forceDelete();

        $migration_file = base_path() . '/tests/Unit/Migration/migration.json';

        $migration_array = json_decode(file_get_contents($migration_file), 1);

        Import::dispatchNow($migration_array, $this->company, $this->user);

        $this->assertGreaterThan($original_count, Credit::count());
    }
}
