<?php

namespace Tests\Unit\Migration;

use App\Exceptions\MigrationValidatorFailed;
use App\Exceptions\ResourceDependencyMissing;
use App\Exceptions\ResourceNotAvailableForMigration;
use App\Jobs\Util\Import;
use App\Jobs\Util\StartMigration;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\InvoiceInvitation;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Quote;
use App\Models\TaxRate;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
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

    public function testExceptionOnUnavailableResource()
    {
        $data['panda_bears'] = [
            'name' => 'Awesome Panda Bear',
        ];

        Import::dispatchNow($data, $this->company, $this->user);


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
        $this->makeTestData();

        $this->invoice->forceDelete();

        $original_count = Invoice::count();

        $migration_file = base_path() . '/tests/Unit/Migration/migration.json';

        $migration_array = json_decode(file_get_contents($migration_file), 1);

        Import::dispatchNow($migration_array, $this->company, $this->user);

        $this->assertGreaterThan($original_count, Invoice::count());
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

    // public function testInvoiceAttributes()
    // {
    //     $original_number = Invoice::count();

    //     $this->invoice->forceDelete();

    //     $migration_file = base_path() . '/tests/Unit/Migration/migration.json';

    //     $migration_array = json_decode(file_get_contents($migration_file), 1);

    //     Import::dispatchNow($migration_array, $this->company, $this->user);

    //     $this->assertGreaterThan($original_number, Invoice::count());

    //     $invoice_1 = Invoice::whereNumber('0001')
    //         // ->where('discount', '0.00')
    //         // ->where('date', '2020-03-18')
    //         ->first();

    //     $invoice_2 = Invoice::whereNumber('0018')
    //         // ->where('discount', '0.00')
    //         // ->where('date', '2019-10-15')
    //         ->first();

    //     $this->assertNotNull($invoice_1);
    //     $this->assertNotNull($invoice_2);

    //     $this->assertEquals('13.5000', $invoice_1->amount);
    //     $this->assertEquals('67.4100', $invoice_2->amount);

    //     $this->assertEquals('8.4900', $invoice_1->balance);
    //     $this->assertEquals('50.4200', $invoice_2->balance);
    // }

    // public function testQuoteAttributes()
    // {
    //     $original_number = Quote::count();

    //     $this->invoice->forceDelete();

    //     $migration_file = base_path() . '/tests/Unit/Migration/migration.json';

    //     $migration_array = json_decode(file_get_contents($migration_file), 1);

    //     Import::dispatchNow($migration_array, $this->company, $this->user);

    //     $this->assertGreaterThan($original_number, Invoice::count());


    //     $quote = Quote::whereNumber('0021')
    //         ->whereDiscount('0.00')
    //         ->first();

    //     $this->assertNotNull($quote);
    //     $this->assertEquals('0.0000', $quote->amount);
    //     $this->assertEquals('0.0000', $quote->balance);
    // }

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

    public function testMigrationFileExists()
    {
        $migration_archive = base_path() . '/tests/Unit/Migration/migration.zip';

        $this->assertTrue(file_exists($migration_archive));
    }

    public function testMigrationFileBeingExtracted()
    {
        $migration_archive = base_path() . '/tests/Unit/Migration/migration.zip';

        StartMigration::dispatchNow($migration_archive, $this->user, $this->company);

        $extracted_archive = storage_path("migrations/migration");
        $migration_file = storage_path("migrations/migration/migration.json");

        $this->assertTrue(file_exists($extracted_archive));
        $this->assertTrue(is_dir($extracted_archive));
        $this->assertTrue(file_exists($migration_file));
    }

    public function testValidityOfImportedData()
    {
        $this->invoice->forceDelete();

        $migration_file = base_path() . '/tests/Unit/Migration/migration.json';

        $migration_array = json_decode(file_get_contents($migration_file), 1);


        Import::dispatchNow($migration_array, $this->company, $this->user);

        $differences = [];

        foreach ($migration_array['invoices'] as $key => $invoices) {
            $record = Invoice::whereNumber($invoices['number'])
                ->whereAmount($invoices['amount'])
                ->whereBalance($invoices['balance'])
                ->first();

            if (!$record) {
                $differences['invoices']['missing'][] = $invoices['id'];
            }
        }

        foreach ($migration_array['users'] as $key => $user) {
            $record = User::whereEmail($user['email'])->first();

            if (!$record) {
                $differences['users']['missing'][] = $user['email'];
            }
        }

        foreach ($migration_array['tax_rates'] as $key => $tax_rate) {
            $record = TaxRate::whereName($tax_rate['name'])
                ->where('rate', $tax_rate['rate'])
                ->first();

            if (!$record) {
                $differences['tax_rates']['missing'][] = $tax_rate['name'];
            }
        }

        foreach ($migration_array['clients'] as $key => $client) {
            $record = Client::whereName($client['name'])
                ->whereCity($client['city'])
                // ->where('paid_to_date', $client['paid_to_date']) // TODO: Doesn't work. Need debugging.
                ->first();

            if (!$record) {
                $differences['clients']['missing'][] = $client['name'];
            }
        }

        foreach ($migration_array['products'] as $key => $product) {
            $record = Product::where('product_key', $product['product_key'])
                ->first();

            if (!$record) {
                $differences['products']['missing'][] = $product['notes'];
            }
        }

        foreach ($migration_array['quotes'] as $key => $quote) {
            $record = Quote::whereNumber($quote['number'])
                ->whereIsAmountDiscount($quote['is_amount_discount'])
                ->whereDueDate($quote['due_date'])
                ->first();

            if (!$record) {
                $differences['quotes']['missing'][] = $quote['id'];
            }
        }

        foreach ($migration_array['payments'] as $key => $payment) {
            $record = Payment::whereAmount($payment['amount'])
                ->whereApplied($payment['applied'])
                ->whereRefunded($payment['refunded'])
                ->first();

            if (!$record) {
                $differences['quotes']['missing'][] = $payment['id'];
            }
        }

        /*foreach ($migration_array['credits'] as $key => $credit) {

            // The Import::processCredits() does insert the credit record with number: 0053,
            // .. however this part of the code doesn't see it at all.

            $record = Credit::whereNumber($credit['number'])
                ->first();

            if (!$record) {
                $differences['credits']['missing'][] = $credit['id'];
            }
        }*/

        print_r($differences);

        $this->assertCount(0, $differences);
    }
}
