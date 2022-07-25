<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Unit\Migration;

use App\Exceptions\ResourceDependencyMissing;
use App\Exceptions\ResourceNotAvailableForMigration;
use App\Jobs\Util\Import;
use App\Jobs\Util\StartMigration;
use App\Mail\MigrationFailed;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientGatewayToken;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\Credit;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Quote;
use App\Models\TaxRate;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\MockAccountData;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    public $migration_array;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        $migration_file = base_path().'/tests/Unit/Migration/migration.json';

        $this->migration_array = json_decode(file_get_contents($migration_file), 1);
    }

    public function testImportClassExists()
    {
        $status = class_exists(\App\Jobs\Util\Import::class);

        $this->assertTrue($status);
    }

    // public function testAllImport()
    // {

    //     $this->invoice->forceDelete();
    //     $this->quote->forceDelete();

    //     $this->user->setCompany($this->company);
    //     auth()->login($this->user, true);

    //     Import::dispatchNow($this->migration_array, $this->company, $this->user);

    //     $this->assertTrue(true);
    // }

//     public function testExceptionOnUnavailableResource()
//     {
//         $data['panda_bears'] = [
//             'name' => 'Awesome Panda Bear',
//         ];

//         try {
//             Import::dispatchNow($data, $this->company, $this->user);
//         }
//         catch (ResourceNotAvailableForMigration $e) {
//             $this->assertTrue(true);
//         }
//     }

//     public function testCompanyUpdating()
//     {
//         $original_company_key = $this->company->company_key;

//         $data['company'] = [
//             'company_key' => 0,
//         ];

//         Import::dispatchNow($data, $this->company, $this->user);

//         $this->assertNotEquals($original_company_key, $this->company->company_key);
//     }

//     public function testInvoicesFailsWithoutClient()
//     {
//         $data['invoices'] = [
//             0 => [
//                 'client_id' => 1,
//                 'is_amount_discount' => false,
//             ]
//         ];

//         try {
//             Import::dispatchNow($data, $this->company, $this->user);
//         } catch(ResourceDependencyMissing $e) {
//             $this->assertTrue(true);
//         }
//     }

//     public function testInvoicesImporting()
//     {
//         $this->makeTestData();

//         $this->invoice->forceDelete();
//         $this->quote->forceDelete();

//         $original_count = Invoice::count();

//         Import::dispatchNow($this->migration_array, $this->company, $this->user);

//         $this->assertGreaterThan($original_count, Invoice::count());
//     }

//     public function testQuotesFailsWithoutClient()
//     {
//         $data['quotes'] = [
//             0 => [
//                 'client_id' => 1,
//                 'is_amount_discount' => false,
//             ]
//         ];

//         try {
//             Import::dispatchNow($data, $this->company, $this->user);
//         } catch(ResourceDependencyMissing $e) {
//             $this->assertTrue(true);
//         }
//     }

//     public function testImportFileExists()
//     {
//         $this->assertGreaterThan(1, count($this->migration_array));

//     }

//     public function testClientAttributes()
//     {
//         $original_number = Client::count();

//         $random_balance = rand(0, 10);

//         $data['clients'] = [
//             0 => [
//                 'id' => 1,
//                 'name' => 'My awesome unique client',
//                 'balance' => $random_balance,
//                 'user_id' => 1,
//             ]
//         ];

//         Import::dispatchNow($data, $this->company, $this->user);

//         $client = Client::where('name', 'My awesome unique client')
//             ->where('balance', $random_balance)
//             ->first();

//         $this->assertNotNull($client);
//         $this->assertGreaterThan($original_number, Client::count());
//         $this->assertGreaterThanOrEqual(0, $client->balance);
//     }

//     // public function testInvoiceAttributes()
//     // {
//     //     $original_number = Invoice::count();

//     //     $this->invoice->forceDelete();

//     //     $migration_file = base_path() . '/tests/Unit/Migration/migration.json';

//     //     $this->migration_array = json_decode(file_get_contents($migration_file), 1);

//     //     Import::dispatchNow($this->migration_array, $this->company, $this->user);

//     //     $this->assertGreaterThan($original_number, Invoice::count());

//     //     $invoice_1 = Invoice::whereNumber('0001')
//     //         // ->where('discount', '0.00')
//     //         // ->where('date', '2020-03-18')
//     //         ->first();

//     //     $invoice_2 = Invoice::whereNumber('0018')
//     //         // ->where('discount', '0.00')
//     //         // ->where('date', '2019-10-15')
//     //         ->first();

//     //     $this->assertNotNull($invoice_1);
//     //     $this->assertNotNull($invoice_2);

//     //     $this->assertEquals('13.5000', $invoice_1->amount);
//     //     $this->assertEquals('67.4100', $invoice_2->amount);

//     //     $this->assertEquals('8.4900', $invoice_1->balance);
//     //     $this->assertEquals('50.4200', $invoice_2->balance);
//     // }

//     // public function testQuoteAttributes()
//     // {
//     //     $original_number = Quote::count();

//     //     $this->invoice->forceDelete();

//     //     $migration_file = base_path() . '/tests/Unit/Migration/migration.json';

//     //     $this->migration_array = json_decode(file_get_contents($migration_file), 1);

//     //     Import::dispatchNow($this->migration_array, $this->company, $this->user);

//     //     $this->assertGreaterThan($original_number, Invoice::count());

//     //     $quote = Quote::whereNumber('0021')
//     //         ->whereDiscount('0.00')
//     //         ->first();

//     //     $this->assertNotNull($quote);
//     //     $this->assertEquals('0.0000', $quote->amount);
//     //     $this->assertEquals('0.0000', $quote->balance);
//     // }

//     public function testPaymentsImport()
//     {
//         $original_count = Payment::count();

//         $this->invoice->forceDelete();
//         $this->quote->forceDelete();
//         Import::dispatchNow($this->migration_array, $this->company, $this->user);

//         $this->assertGreaterThan($original_count, Payment::count());
//     }

//     public function testPaymentDependsOnClient()
//     {
//         $data['payments'] = [
//             0 => [
//                 'client_id' => 1,
//                 'amount' => 1,
//             ]
//         ];

//         try {
//             Import::dispatchNow($data, $this->company, $this->user);
//         } catch(ResourceDependencyMissing $e) {
//             $this->assertTrue(true);
//         }
//     }

//     public function testQuotesImport()
//     {
//         $original_count = Credit::count();

//         $this->invoice->forceDelete();
//         $this->quote->forceDelete();

//         Import::dispatchNow($this->migration_array, $this->company, $this->user);

//         $this->assertGreaterThan($original_count, Credit::count());
//     }

//     public function testMigrationFileExists()
//     {
//         $migration_archive = base_path() . '/tests/Unit/Migration/migration.zip';

//         $this->assertTrue(file_exists($migration_archive));
//     }

//     // public function testMigrationFileBeingExtracted()
//     // {
//     //     $migration_archive = base_path() . '/tests/Unit/Migration/migration.zip';

//     //     StartMigration::dispatchNow($migration_archive, $this->user, $this->company);

//     //     $extracted_archive = storage_path("migrations/migration");
//     //     $migration_file = storage_path("migrations/migration/migration.json");

//     //     $this->assertTrue(file_exists($extracted_archive));
//     //     $this->assertTrue(is_dir($extracted_archive));
//     //     $this->assertTrue(file_exists($migration_file));
//     // }

//     public function testValidityOfImportedData()
//     {
//         $this->invoice->forceDelete();
//         $this->quote->forceDelete();

//         Import::dispatchNow($this->migration_array, $this->company, $this->user);

//         $differences = [];

//         foreach ($this->migration_array['invoices'] as $key => $invoices) {
//             $record = Invoice::whereNumber($invoices['number'])
//                 ->whereAmount($invoices['amount'])
//                 ->whereBalance($invoices['balance'])
//                 ->first();

//             if (!$record) {
//                 $differences['invoices']['missing'][] = $invoices['id'];
//             }
//         }

//         foreach ($this->migration_array['users'] as $key => $user) {
//             $record = User::whereEmail($user['email'])->first();

//             if (!$record) {
//                 $differences['users']['missing'][] = $user['email'];
//             }
//         }

//         foreach ($this->migration_array['tax_rates'] as $key => $tax_rate) {
//             $record = TaxRate::whereName($tax_rate['name'])
//                 ->where('rate', $tax_rate['rate'])
//                 ->first();

//             if (!$record) {
//                 $differences['tax_rates']['missing'][] = $tax_rate['name'];
//             }
//         }

//         foreach ($this->migration_array['clients'] as $key => $client) {
//             $record = Client::whereName($client['name'])
//                 ->whereCity($client['city'])
//                 // ->where('paid_to_date', $client['paid_to_date']) // TODO: Doesn't work. Need debugging.
//                 ->first();

//             if (!$record) {
//                 $differences['clients']['missing'][] = $client['name'];
//             }
//         }

//         foreach ($this->migration_array['products'] as $key => $product) {
//             $record = Product::where('product_key', $product['product_key'])
//                 ->first();

//             if (!$record) {
//                 $differences['products']['missing'][] = $product['notes'];
//             }
//         }

//         foreach ($this->migration_array['quotes'] as $key => $quote) {
//             $record = Quote::whereNumber($quote['number'])
//                 ->whereIsAmountDiscount($quote['is_amount_discount'])
//                 ->whereDueDate($quote['due_date'])
//                 ->first();

//             if (!$record) {
//                 $differences['quotes']['missing'][] = $quote['id'];
//             }
//         }

//         foreach ($this->migration_array['payments'] as $key => $payment) {
//             $record = Payment::whereApplied($payment['applied'])
//                 ->first();

//             if (!$record) {
//                 $differences['payments']['missing'][] = $payment['id'];
//             }
//         }

//         foreach ($this->migration_array['credits'] as $key => $credit) {

//             // The Import::processCredits() does insert the credit record with number: 0053,
//             // .. however this part of the code doesn't see it at all.

//             $record = Credit::whereNumber($credit['number'])
//                 ->first();

//             if (!$record) {
//                 $differences['credits']['missing'][] = $credit['id'];
//             }
//         }

// /*
//         foreach ($this->migration_array['company_gateways'] as $key => $company_gateway) {

//             // The Import::processCredits() does insert the credit record with number: 0053,
//             // .. however this part of the code doesn't see it at all.

//             $record = CompanyGateway::where('gateway_key' ,$company_gateway['gateway_key'])
//                 ->first();

//             if (!$record) {
//                 $differences['company_gateways']['missing'][] = $company_gateway['id'];
//             }
//         }

//         foreach ($this->migration_array['client_gateway_tokens'] as $key => $cgt) {

//             // The Import::processCredits() does insert the credit record with number: 0053,
//             // .. however this part of the code doesn't see it at all.

//             $record = ClientGatewayToken::where('token' ,$cgt['token'])
//                 ->first();

//             if (!$record) {
//                 $differences['client_gateway_tokens']['missing'][] = $cgt['id'];
//             }
//         }
// */
//         //@TODO we can uncomment tests for documents when we have imported expenses.

//         // foreach ($this->migration_array['documents'] as $key => $document) {

//         //     if(!is_null($document['invoice_id'])) {

//         //         $record = Document::where('hash', $document['hash'])
//         //             ->first();

//         //         if (!$record) {
//         //             $differences['documents']['missing'][] = $document['id'];
//         //         }
//         //     }
//         // }

//         //\Log::error($differences);

//         $this->assertCount(0, $differences);
//     }

//     public function testClientContactsImport()
//     {
//         $this->invoice->forceDelete();
//         $this->quote->forceDelete();

//         $original = ClientContact::count();

//         Import::dispatchNow($this->migration_array, $this->company, $this->user);

//         $this->assertGreaterThan($original, ClientContact::count());
//     }

//     public function testClientGatewayTokensImport()
//     {
//         $this->invoice->forceDelete();
//         $this->quote->forceDelete();

//         $original = ClientGatewayToken::count();

//         Import::dispatchNow($this->migration_array, $this->company, $this->user);

//         $this->assertTrue(true, 'ClientGatewayTokens importing not completed yet.');

//     }

//     public function testDocumentsImport()
//     {
//         $this->invoice->forceDelete();
//         $this->quote->forceDelete();

//         $original = Document::count();

//         Import::dispatchNow($this->migration_array, $this->company, $this->user);

//         $document = Document::first();

//         $this->assertTrue(true, 'Documents importing not completed yet. Missing expenses.');
//     }

//     public function testExceptionMailSending()
//     {
//         Mail::fake();

//         $data['panda_bears'] = [
//             'name' => 'Awesome Panda Bear',
//         ];

//         try {
//             Import::dispatchNow($data, $this->company, $this->user);
//         }
//         catch (ResourceNotAvailableForMigration $e) {
//             Mail::to($this->user)->send(new MigrationFailed($e, $e->getMessage()));
//             $this->assertTrue(true);
//         }
//     }
}
