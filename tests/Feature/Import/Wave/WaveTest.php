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

namespace Tests\Feature\Import\Wave;

use App\Import\Providers\Wave;
use App\Import\Transformer\BaseTransformer;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Vendor;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * 
 *  App\Import\Providers\Wave
 */
class WaveTest extends TestCase
{
    use MakesHash;
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);

        config(['database.default' => config('ninja.db.default')]);

        $this->makeTestData();

        $this->withoutExceptionHandling();
    }

    // public function testExpenseImport()
    // {

    //     $csv = file_get_contents(
    //         base_path() . '/tests/Feature/Import/wave_expenses.csv'
    //     );
    //     $hash = Str::random(32);

    //     $column_map = [
    //         0 => 'Transaction ID',
    //         1 => 'Transaction Date',
    //         2 => 'Account Name',
    //         3 => 'Transaction Description',
    //         4 => 'Transaction Line Description',
    //         5 => 'Amount (One column)',
    //         6 => ' ',
    //         7 => 'Debit Amount (Two Column Approach)',
    //         8 => 'Credit Amount (Two Column Approach)',
    //         9 => 'Other Accounts for this Transaction',
    //         10 => 'Customer',
    //         11 => 'Vendor',
    //         12 => 'Invoice Number',
    //         13 => 'Bill Number',
    //         14 => 'Notes / Memo',
    //         15 => 'Amount Before Sales Tax',
    //         16 => 'Sales Tax Amount',
    //         17 => 'Sales Tax Name',
    //         18 => 'Transaction Date Added',
    //         19 => 'Transaction Date Last Modified',
    //         20 => 'Account Group',
    //         21 => 'Account Type',
    //         22 => 'Account ID',
    //     ];

    //     $data = [
    //         'hash' => $hash,
    //         'column_map' => ['expense' => ['mapping' => $column_map]],
    //         'skip_header' => true,
    //         'import_type' => 'waveaccounting',
    //     ];

    //     Cache::put($hash . '-expense', base64_encode($csv), 360);

    //     $csv_importer = new Wave($data, $this->company);

    //     $count = $csv_importer->import('expense');

    // }

    public function testVendorAndExpenseWaveImport()
    {
        $csv = file_get_contents(
            base_path().'/tests/Feature/Import/wave_vendors.csv'
        );
        $hash = Str::random(32);

        $column_map = [
            0 => 'vendor_name',
            1 => 'email',
            2 => 'contact_first_name',
            3 => 'contact_last_name',
            4 => 'vendor_currency',
            5 => 'account_number',
            6 => 'phone',
            7 => 'fax',
            8 => 'mobile',
            9 => 'toll_free',
            10 => 'website',
            11 => 'country',
            12 => 'province/state',
            13 => 'address_line_1',
            14 => 'address_line_2',
            15 => 'city',
            16 => 'postal_code/zip_code',
        ];

        $data = [
            'hash' => $hash,
            'column_map' => ['vendor' => ['mapping' => $column_map]],
            'skip_header' => true,
            'import_type' => 'waveaccounting',
        ];

        Cache::put($hash.'-vendor', base64_encode($csv), 360);

        $csv_importer = new Wave($data, $this->company);

        $count = $csv_importer->import('vendor');

        $base_transformer = new BaseTransformer($this->company);

        $this->assertTrue($base_transformer->hasVendor('Vendor Name'));

        $vendor_id = $base_transformer->getVendorId('Vendor Name');

        $vendor = Vendor::find($vendor_id);

        $this->assertInstanceOf(Vendor::class, $vendor);

        $this->assertEquals(12, $vendor->currency_id);
        $this->assertEquals('Australian Capital Territory', $vendor->state);
        $this->assertEquals('city', $vendor->city);
        $this->assertEquals('postal_cod', $vendor->postal_code);

        $this->assertEquals('firstname', $vendor->contacts->first()->first_name);
        $this->assertEquals('lastname', $vendor->contacts->first()->last_name);
        $this->assertEquals('vendor@gmail.com', $vendor->contacts->first()->email);
        $this->assertEquals('phone', $vendor->contacts->first()->phone);

        // now lets try importing expenses / bills
        $csv = file_get_contents(
            base_path().'/tests/Feature/Import/wave_expenses.csv'
        );
        $hash = Str::random(32);

        $column_map = [
            0 => 'Transaction ID',
            1 => 'Transaction Date',
            2 => 'Account Name',
            3 => 'Transaction Description',
            4 => 'Transaction Line Description',
            5 => 'Amount (One column)',
            6 => ' ',
            7 => 'Debit Amount (Two Column Approach)',
            8 => 'Credit Amount (Two Column Approach)',
            9 => 'Other Accounts for this Transaction',
            10 => 'Customer',
            11 => 'Vendor',
            12 => 'Invoice Number',
            13 => 'Bill Number',
            14 => 'Notes / Memo',
            15 => 'Amount Before Sales Tax',
            16 => 'Sales Tax Amount',
            17 => 'Sales Tax Name',
            18 => 'Transaction Date Added',
            19 => 'Transaction Date Last Modified',
            20 => 'Account Group',
            21 => 'Account Type',
            22 => 'Account ID',
        ];

        $data = [
            'hash' => $hash,
            'column_map' => ['expense' => ['mapping' => $column_map]],
            'skip_header' => true,
            'import_type' => 'waveaccounting',
        ];

        Cache::put($hash.'-invoice', base64_encode($csv), 360);

        $csv_importer = new Wave($data, $this->company);

        $count = $csv_importer->import('expense');

        $base_transformer = new BaseTransformer($this->company);

        $this->assertTrue($base_transformer->hasExpense('66'));
    }

    public function testClientWaveImport()
    {
        $csv = file_get_contents(
            base_path().'/tests/Feature/Import/wave_clients.csv'
        );
        $hash = Str::random(32);

        $column_map = [
            0 => 'customer_name',
            1 => 'email',
            2 => 'contact_first_name',
            3 => 'contact_last_name',
            4 => 'customer_currency',
            5 => 'account_number',
            6 => 'phone',
            7 => 'fax',
            8 => 'mobile',
            9 => 'toll_free',
            10 => 'website',
            11 => 'country',
            12 => 'province/state',
            13 => 'address_line_1',
            14 => 'address_line_2',
            15 => 'city',
            16 => 'postal_code/zip_code',
            17 => 'shipping_address',
            18 => 'ship-to_contact',
            19 => 'ship-to_country',
            20 => 'ship-to_province/state',
            21 => 'ship-to_address_line_1',
            22 => 'ship-to_address_line_2',
            23 => 'ship-to_city',
            24 => 'ship-to_postal_code/zip_code',
            25 => 'ship-to_phone',
            26 => 'delivery_instructions',
        ];

        $data = [
            'hash' => $hash,
            'column_map' => ['client' => ['mapping' => $column_map]],
            'skip_header' => true,
            'import_type' => 'waveaccounting',
        ];

        Cache::put($hash.'-client', base64_encode($csv), 360);

        $csv_importer = new Wave($data, $this->company);

        $count = $csv_importer->import('client');

        $base_transformer = new BaseTransformer($this->company);

        $this->assertTrue($base_transformer->hasClient('Homer Simpson'));
        $this->assertTrue($base_transformer->hasClient('Jessica Jones'));
        $this->assertTrue($base_transformer->hasClient('Lucas Cage'));
        $this->assertTrue($base_transformer->hasClient('Mark Walberg'));

        $client_id = $base_transformer->getClient('Jessica Jones', 'jessica@jones.com');

        $client = Client::find($client_id);

        $this->assertInstanceOf(Client::class, $client);

        $this->assertEquals('12', $client->settings->currency_id);
        $this->assertEquals('Queensland', $client->state);
        $this->assertEquals('NYC', $client->city);
        $this->assertEquals('11213', $client->postal_code);

        $this->assertEquals('Jessica Jones', $client->contacts->first()->first_name);
        $this->assertEquals('', $client->contacts->first()->last_name);
        $this->assertEquals('jessica@jones.com', $client->contacts->first()->email);
        $this->assertEquals('555-867-5309', $client->contacts->first()->phone);
    }

    public function testInvoiceWaveImport()
    {
        //first import all the clients

        $csv = file_get_contents(
            base_path().'/tests/Feature/Import/wave_clients.csv'
        );
        $hash = Str::random(32);

        $column_map = [
            0 => 'customer_name',
            1 => 'email',
            2 => 'contact_first_name',
            3 => 'contact_last_name',
            4 => 'customer_currency',
            5 => 'account_number',
            6 => 'phone',
            7 => 'fax',
            8 => 'mobile',
            9 => 'toll_free',
            10 => 'website',
            11 => 'country',
            12 => 'province/state',
            13 => 'address_line_1',
            14 => 'address_line_2',
            15 => 'city',
            16 => 'postal_code/zip_code',
            17 => 'shipping_address',
            18 => 'ship-to_contact',
            19 => 'ship-to_country',
            20 => 'ship-to_province/state',
            21 => 'ship-to_address_line_1',
            22 => 'ship-to_address_line_2',
            23 => 'ship-to_city',
            24 => 'ship-to_postal_code/zip_code',
            25 => 'ship-to_phone',
            26 => 'delivery_instructions',
        ];

        $data = [
            'hash' => $hash,
            'column_map' => ['client' => ['mapping' => $column_map]],
            'skip_header' => true,
            'import_type' => 'waveaccounting',
        ];

        Cache::put($hash.'-client', base64_encode($csv), 360);

        $csv_importer = new Wave($data, $this->company);

        $count = $csv_importer->import('client');

        //now import the invoices

        $csv = file_get_contents(
            base_path().'/tests/Feature/Import/wave_invoices.csv'
        );
        $hash = Str::random(32);

        $column_map = [
            0 => 'Transaction ID',
            1 => 'Transaction Date',
            2 => 'Account Name',
            3 => 'Transaction Description',
            4 => 'Transaction Line Description',
            5 => 'Amount (One column)',
            6 => ' ',
            7 => 'Debit Amount (Two Column Approach)',
            8 => 'Credit Amount (Two Column Approach)',
            9 => 'Other Accounts for this Transaction',
            10 => 'Customer',
            11 => 'Vendor',
            12 => 'Invoice Number',
            13 => 'Bill Number',
            14 => 'Notes / Memo',
            15 => 'Amount Before Sales Tax',
            16 => 'Sales Tax Amount',
            17 => 'Sales Tax Name',
            18 => 'Transaction Date Added',
            19 => 'Transaction Date Last Modified',
            20 => 'Account Group',
            21 => 'Account Type',
            22 => 'Account ID',
        ];

        $data = [
            'hash' => $hash,
            'column_map' => ['invoice' => ['mapping' => $column_map]],
            'skip_header' => true,
            'import_type' => 'waveaccounting',
        ];

        Cache::put($hash.'-invoice', base64_encode($csv), 360);

        $csv_importer = new Wave($data, $this->company);

        $count = $csv_importer->import('invoice');

        $base_transformer = new BaseTransformer($this->company);

        $this->assertTrue($base_transformer->hasInvoice('2'));
        $this->assertTrue($base_transformer->hasInvoice('3'));
        $this->assertTrue($base_transformer->hasInvoice('4'));

        $invoice_id = $base_transformer->getInvoiceId('4');
        $invoice = Invoice::find($invoice_id);

        $this->assertEquals(3500.41, $invoice->amount);
        $this->assertEquals(0, $invoice->balance);
        $this->assertEquals(2, count($invoice->line_items));

        $this->assertTrue($invoice->payments()->exists());
        $this->assertEquals(3500.41, $invoice->payments->first()->amount);
    }

    // public function testVendorCsvImport()
    // {
    //     $csv = file_get_contents(
    //         base_path() . '/tests/Feature/Import/vendors.csv'
    //     );
    //     $hash = Str::random(32);
    //     $column_map = [
    //         0 => 'vendor.name',
    //         19 => 'vendor.currency_id',
    //         20 => 'vendor.public_notes',
    //         21 => 'vendor.private_notes',
    //         22 => 'vendor.first_name',
    //         23 => 'vendor.last_name',
    //     ];

    //     $data = [
    //         'hash' => $hash,
    //         'column_map' => ['vendor' => ['mapping' => $column_map]],
    //         'skip_header' => true,
    //         'import_type' => 'csv',
    //     ];

    //     $pre_import = Vendor::count();

    //     Cache::put($hash . '-vendor', base64_encode($csv), 360);

    //     $csv_importer = new Csv($data, $this->company);

    //     $csv_importer->import('vendor');

    //     $base_transformer = new BaseTransformer($this->company);

    //     $this->assertTrue($base_transformer->hasVendor('Ludwig Krajcik DVM'));
    // }

    // public function testProductImport()
    // {
    //     $csv = file_get_contents(
    //         base_path() . '/tests/Feature/Import/products.csv'
    //     );
    //     $hash = Str::random(32);
    //     Cache::put($hash . '-product', base64_encode($csv), 360);

    //     $column_map = [
    //         1 => 'product.product_key',
    //         2 => 'product.notes',
    //         3 => 'product.cost',
    //     ];

    //     $data = [
    //         'hash' => $hash,
    //         'column_map' => ['product' => ['mapping' => $column_map]],
    //         'skip_header' => true,
    //         'import_type' => 'csv',
    //     ];

    //     $csv_importer = new Csv($data, $this->company);

    //     $this->assertInstanceOf(Csv::class, $csv_importer);

    //     $csv_importer->import('product');

    //     $base_transformer = new BaseTransformer($this->company);

    //     $this->assertTrue($base_transformer->hasProduct('officiis'));
    //     // $this->assertTrue($base_transformer->hasProduct('maxime'));
    // }

    // public function testClientImport()
    // {
    //     $csv = file_get_contents(
    //         base_path() . '/tests/Feature/Import/clients.csv'
    //     );
    //     $hash = Str::random(32);
    //     $column_map = [
    //         1 => 'client.balance',
    //         2 => 'client.paid_to_date',
    //         0 => 'client.name',
    //         19 => 'client.currency_id',
    //         20 => 'client.public_notes',
    //         21 => 'client.private_notes',
    //         22 => 'contact.first_name',
    //         23 => 'contact.last_name',
    //         24 => 'contact.email',
    //     ];

    //     $data = [
    //         'hash' => $hash,
    //         'column_map' => ['client' => ['mapping' => $column_map]],
    //         'skip_header' => true,
    //         'import_type' => 'csv',
    //     ];

    //     Cache::put($hash . '-client', base64_encode($csv), 360);

    //     $csv_importer = new Csv($data, $this->company);

    //     $this->assertInstanceOf(Csv::class, $csv_importer);

    //     $csv_importer->import('client');

    //     $base_transformer = new BaseTransformer($this->company);

    //     $this->assertTrue($base_transformer->hasClient('Ludwig Krajcik DVM'));

    //     $client_id = $base_transformer->getClient('Ludwig Krajcik DVM', null);

    //     $c = Client::find($client_id);

    //     $this->assertEquals($client_id, $c->id);

    //     $client_id = $base_transformer->getClient(
    //         'a non existent clent',
    //         'brook59@example.org'
    //     );

    //     $this->assertEquals($client_id, $c->id);
    // }

    // public function testInvoiceImport()
    // {
    //     /*Need to import clients first*/
    //     $csv = file_get_contents(
    //         base_path() . '/tests/Feature/Import/clients.csv'
    //     );
    //     $hash = Str::random(32);
    //     $column_map = [
    //         1 => 'client.balance',
    //         2 => 'client.paid_to_date',
    //         0 => 'client.name',
    //         19 => 'client.currency_id',
    //         20 => 'client.public_notes',
    //         21 => 'client.private_notes',
    //         22 => 'contact.first_name',
    //         23 => 'contact.last_name',
    //     ];

    //     $data = [
    //         'hash' => $hash,
    //         'column_map' => ['client' => ['mapping' => $column_map]],
    //         'skip_header' => true,
    //         'import_type' => 'csv',
    //     ];

    //     Cache::put($hash . '-client', base64_encode($csv), 360);

    //     $csv_importer = new Csv($data, $this->company);

    //     $this->assertInstanceOf(Csv::class, $csv_importer);

    //     $csv_importer->import('client');

    //     $base_transformer = new BaseTransformer($this->company);

    //     $this->assertTrue($base_transformer->hasClient('Ludwig Krajcik DVM'));

    //     /* client import verified*/

    //     /*Now import invoices*/
    //     $csv = file_get_contents(
    //         base_path() . '/tests/Feature/Import/invoice.csv'
    //     );
    //     $hash = Str::random(32);

    //     $column_map = [
    //         1 => 'client.email',
    //         3 => 'payment.amount',
    //         5 => 'invoice.po_number',
    //         8 => 'invoice.due_date',
    //         9 => 'item.discount',
    //         11 => 'invoice.partial_due_date',
    //         12 => 'invoice.public_notes',
    //         13 => 'invoice.private_notes',
    //         0 => 'client.name',
    //         2 => 'invoice.number',
    //         7 => 'invoice.date',
    //         14 => 'item.product_key',
    //         15 => 'item.notes',
    //         16 => 'item.cost',
    //         17 => 'item.quantity',
    //     ];

    //     $data = [
    //         'hash' => $hash,
    //         'column_map' => ['invoice' => ['mapping' => $column_map]],
    //         'skip_header' => true,
    //         'import_type' => 'csv',
    //     ];

    //     Cache::put($hash . '-invoice', base64_encode($csv), 360);

    //     $csv_importer = new Csv($data, $this->company);

    //     $csv_importer->import('invoice');

    //     $this->assertTrue($base_transformer->hasInvoice('801'));

    //     /* Lets piggy back payments tests here to save rebuilding the test multiple times*/

    //     $csv = file_get_contents(
    //         base_path() . '/tests/Feature/Import/payments.csv'
    //     );
    //     $hash = Str::random(32);

    //     $column_map = [
    //         0 => 'payment.client_id',
    //         1 => 'payment.invoice_number',
    //         2 => 'payment.amount',
    //         3 => 'payment.date',
    //     ];

    //     $data = [
    //         'hash' => $hash,
    //         'column_map' => ['payment' => ['mapping' => $column_map]],
    //         'skip_header' => true,
    //         'import_type' => 'csv',
    //     ];

    //     Cache::put($hash . '-payment', base64_encode($csv), 360);

    //     $csv_importer = new Csv($data, $this->company);

    //     $csv_importer->import('payment');

    //     $this->assertTrue($base_transformer->hasInvoice('801'));

    //     $invoice_id = $base_transformer->getInvoiceId('801');

    //     $invoice = Invoice::find($invoice_id);

    //     $this->assertTrue($invoice->payments()->exists());
    //     $this->assertEquals(1, $invoice->payments()->count());
    //     $this->assertEquals(400, $invoice->payments()->sum('payments.amount'));
    // }
}
