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

namespace Tests\Feature\Import;

use App\Jobs\Import\CSVImport;
use App\Models\Client;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Vendor;
use App\Utils\Traits\MakesHash;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use League\Csv\Reader;
use League\Csv\Statement;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers App\Http\Controllers\ImportController
 */
class ImportCsvTest extends TestCase
{
    use MakesHash;
    use MockAccountData;

    protected function setUp() :void
    {
        parent::setUp();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );
        config(['database.default' => config('ninja.db.default')]);

        // $this->faker = \Faker\Factory::create();

        $this->makeTestData();

        $this->markTestSkipped('Skipping CSV Import to improve test speed');

        $this->withoutExceptionHandling();
    }

    public function testCsvRead()
    {
        $csv = file_get_contents(base_path().'/tests/Feature/Import/invoice.csv');

        $this->assertTrue(is_array($this->getCsvData($csv)));
    }

    public function testClientCsvImport()
    {
        $csv = file_get_contents(base_path().'/tests/Feature/Import/clients.csv');
        $hash = Str::random(32);
        $column_map = [
            1 => 'client.balance',
            2 => 'client.paid_to_date',
            0 => 'client.name',
            19 => 'client.currency_id',
            20 => 'client.public_notes',
            21 => 'client.private_notes',
            22 => 'contact.first_name',
            23 => 'contact.last_name',
        ];

        $data = [
            'hash'        => $hash,
            'column_map'  => ['client' => ['mapping' => $column_map]],
            'skip_header' => true,
            'import_type' => 'csv',
        ];

        $pre_import = Client::count();

        Cache::put($hash.'-client', base64_encode($csv), 360);

        CSVImport::dispatchSync($data, $this->company);

        $this->assertGreaterThan($pre_import, Client::count());
    }

    public function testInvoiceCsvImport()
    {
        /*Need to import clients first*/
        $csv = file_get_contents(base_path().'/tests/Feature/Import/clients.csv');
        $hash = Str::random(32);
        $column_map = [
            1 => 'client.balance',
            2 => 'client.paid_to_date',
            0 => 'client.name',
            19 => 'client.currency_id',
            20 => 'client.public_notes',
            21 => 'client.private_notes',
            22 => 'contact.first_name',
            23 => 'contact.last_name',
        ];

        $data = [
            'hash'        => $hash,
            'column_map'  => ['client' => ['mapping' => $column_map]],
            'skip_header' => true,
            'import_type' => 'csv',
        ];

        Cache::put($hash.'-client', base64_encode($csv), 360);

        CSVImport::dispatchSync($data, $this->company);

        /*Now import invoices*/
        $csv = file_get_contents(base_path().'/tests/Feature/Import/invoice.csv');
        $hash = Str::random(32);

        $column_map = [
            1 => 'client.email',
            3 => 'payment.amount',
            5 => 'invoice.po_number',
            8 => 'invoice.due_date',
            9 => 'item.discount',
            11 => 'invoice.partial_due_date',
            12 => 'invoice.public_notes',
            13 => 'invoice.private_notes',
            0 => 'client.name',
            2 => 'invoice.number',
            7 => 'invoice.date',
            14 => 'item.product_key',
            15 => 'item.notes',
            16 => 'item.cost',
            17 => 'item.quantity',
        ];

        $data = [
            'hash'        => $hash,
            'column_map'  => ['invoice' => ['mapping' => $column_map]],
            'skip_header' => true,
            'import_type' => 'csv',
        ];

        $pre_import = Invoice::count();

        Cache::put($hash.'-invoice', base64_encode($csv), 360);

        CSVImport::dispatchSync($data, $this->company);

        $this->assertGreaterThan($pre_import, Invoice::count());
    }

    public function testVendorCsvImport()
    {
        $csv = file_get_contents(base_path().'/tests/Feature/Import/vendors.csv');
        $hash = Str::random(32);
        $column_map = [
            0  => 'vendor.name',
            19 => 'vendor.currency_id',
            20 => 'vendor.public_notes',
            21 => 'vendor.private_notes',
            22 => 'vendor.first_name',
            23 => 'vendor.last_name',
        ];

        $data = [
            'hash'        => $hash,
            'column_map'  => ['vendor' => ['mapping' => $column_map]],
            'skip_header' => true,
            'import_type' => 'csv',
        ];

        $pre_import = Vendor::count();

        Cache::put($hash.'-vendor', base64_encode($csv), 360);

        CSVImport::dispatchSync($data, $this->company);

        $this->assertGreaterThan($pre_import, Vendor::count());
    }

    public function testProductCsvImport()
    {
        $csv = file_get_contents(base_path().'/tests/Feature/Import/products.csv');
        $hash = Str::random(32);

        $column_map = [
            2 => 'product.notes',
            3 => 'product.cost',
        ];

        $data = [
            'hash'        => $hash,
            'column_map'  => ['product' => ['mapping' => $column_map]],
            'skip_header' => true,
            'import_type' => 'csv',
        ];

        $pre_import = Product::count();

        Cache::put($hash.'-product', base64_encode($csv), 360);

        CSVImport::dispatchSync($data, $this->company);

        $this->assertGreaterThan($pre_import, Product::count());
    }

    public function testExpenseCsvImport()
    {
        $csv = file_get_contents(base_path().'/tests/Feature/Import/expenses.csv');
        $hash = Str::random(32);

        $column_map = [
            2 => 'expense.public_notes',
            3 => 'expense.amount',
        ];

        $data = [
            'hash'        => $hash,
            'column_map'  => ['expense' => ['mapping' => $column_map]],
            'skip_header' => true,
            'import_type' => 'csv',
        ];

        $pre_import = Expense::count();

        Cache::put($hash.'-expense', base64_encode($csv), 360);

        CSVImport::dispatchSync($data, $this->company);

        $this->assertGreaterThan($pre_import, Expense::count());
    }

    public function testPaymentCsvImport()
    {

/*Need to import clients first*/
        $csv = file_get_contents(base_path().'/tests/Feature/Import/clients.csv');
        $hash = Str::random(32);
        $column_map = [
            1 => 'client.balance',
            2 => 'client.paid_to_date',
            0 => 'client.name',
            19 => 'client.currency_id',
            20 => 'client.public_notes',
            21 => 'client.private_notes',
            22 => 'contact.first_name',
            23 => 'contact.last_name',
        ];

        $data = [
            'hash'        => $hash,
            'column_map'  => ['client' => ['mapping' => $column_map]],
            'skip_header' => true,
            'import_type' => 'csv',
        ];

        Cache::put($hash.'-client', base64_encode($csv), 360);

        CSVImport::dispatchSync($data, $this->company);

        /*Now import invoices*/
        $csv = file_get_contents(base_path().'/tests/Feature/Import/invoice.csv');
        $hash = Str::random(32);

        $column_map = [
            1 => 'client.email',
            3 => 'payment.amount',
            5 => 'invoice.po_number',
            8 => 'invoice.due_date',
            9 => 'item.discount',
            11 => 'invoice.partial_due_date',
            12 => 'invoice.public_notes',
            13 => 'invoice.private_notes',
            0 => 'client.name',
            2 => 'invoice.number',
            7 => 'invoice.date',
            14 => 'item.product_key',
            15 => 'item.notes',
            16 => 'item.cost',
            17 => 'item.quantity',
        ];

        $data = [
            'hash'        => $hash,
            'column_map'  => ['invoice' => ['mapping' => $column_map]],
            'skip_header' => true,
            'import_type' => 'csv',
        ];

        $pre_import = Invoice::count();

        Cache::put($hash.'-invoice', base64_encode($csv), 360);

        CSVImport::dispatchSync($data, $this->company);

        /* Test Now import payments*/

        $csv = file_get_contents(base_path().'/tests/Feature/Import/payments.csv');
        $hash = Str::random(32);

        $column_map = [
            0 => 'payment.client_id',
            1 => 'payment.invoice_number',
            2 => 'payment.amount',
            3 => 'payment.date',
        ];

        $data = [
            'hash'        => $hash,
            'column_map'  => ['payment' => ['mapping' => $column_map]],
            'skip_header' => true,
            'import_type' => 'csv',
        ];

        $pre_import = Payment::count();

        Cache::put($hash.'-payment', base64_encode($csv), 360);

        CSVImport::dispatchSync($data, $this->company);

        $this->assertGreaterThan($pre_import, Payment::count());
    }

    private function getCsvData($csvfile)
    {
        if (! ini_get('auto_detect_line_endings')) {
            ini_set('auto_detect_line_endings', '1');
        }

        $csv = Reader::createFromString($csvfile);
        $stmt = new Statement();
        $data = iterator_to_array($stmt->process($csv));

        if (count($data) > 0) {
            $headers = $data[0];

            // Remove Invoice Ninja headers
            if (count($headers) && count($data) > 4) {
                $firstCell = $headers[0];
                if (strstr($firstCell, config('ninja.app_name'))) {
                    array_shift($data); // Invoice Ninja...
                    array_shift($data); // <blank line>
                    array_shift($data); // Enitty Type Header
                }
            }
        }

        return $data;
    }
}
