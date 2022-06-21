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

namespace Tests\Feature\Import\Zoho;

use App\Import\Providers\BaseImport;
use App\Import\Providers\Zoho;
use App\Import\Transformer\BaseTransformer;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Vendor;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers App\Import\Providers\Zoho
 */
class ZohoTest extends TestCase
{
    use MakesHash;
    use MockAccountData;
    // use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Session::start();

        $this->withoutMiddleware(ThrottleRequests::class);

        config(['database.default' => config('ninja.db.default')]);

        $this->makeTestData();

        $this->withoutExceptionHandling();
    }

    public function testClientZohoImport()
    {
        $csv = file_get_contents(
            base_path().'/tests/Feature/Import/zoho_contacts.csv'
        );
        $hash = Str::random(32);

        $column_map = [
            0 => 'Created Time',
            1 => 'Last Modified Time',
            2 => 'Display Name',
            3 => 'Company Name',
            4 => 'Salutation',
            5 => 'First Name',
            6 => 'Last Name',
            7 => 'Phone',
            8 => 'Currency Code',
            9 => 'Notes',
            10 => 'Website',
            11 => 'Status',
            12 => 'Credit Limit',
            13 => 'Customer Sub Type',
            14 => 'Billing Attention',
            15 => 'Billing Address',
            16 => 'Billing Street2',
            17 => 'Billing City',
            18 => 'Billing State',
            19 => 'Billing Country',
            20 => 'Billing Code',
            21 => 'Billing Phone',
            22 => 'Billing Fax',
            23 => 'Shipping Attention',
            24 => 'Shipping Address',
            25 => 'Shipping Street2',
            26 => 'Shipping City',
            27 => 'Shipping State',
            28 => 'Shipping Country',
            29 => 'Shipping Code',
            30 => 'Shipping Phone',
            31 => 'Shipping Fax',
            32 => 'Skype Identity',
            33 => 'Facebook',
            34 => 'Twitter',
            35 => 'Department',
            36 => 'Designation',
            37 => 'Price List',
            38 => 'Payment Terms',
            39 => 'Payment Terms Label',
            40 => 'Last Sync Time',
            41 => 'Owner Name',
            42 => 'EmailID',
            43 => 'MobilePhone',
            44 => 'Customer ID',
            45 => 'Customer Name',
            46 => 'Contact Type',
            47 => 'Customer Address ID',
            48 => 'Source',
            49 => 'Reference ID',
            50 => 'Payment Reminder',
        ];

        $data = [
            'hash' => $hash,
            'column_map' => ['client' => ['mapping' => $column_map]],
            'skip_header' => true,
            'import_type' => 'zoho',
        ];

        Cache::put($hash.'-client', base64_encode($csv), 360);

        $csv_importer = new Zoho($data, $this->company);

        $count = $csv_importer->import('client');

        $base_transformer = new BaseTransformer($this->company);

        $this->assertTrue($base_transformer->hasClient('Defenders'));
        $this->assertTrue($base_transformer->hasClient('Avengers'));
        $this->assertTrue($base_transformer->hasClient('Highlander'));
        $this->assertTrue($base_transformer->hasClient('Simpsons Baking Co'));

        $client_id = $base_transformer->getClient('', 'jessica@jones.net');

        $client = Client::find($client_id);

        $this->assertInstanceOf(Client::class, $client);
        $this->assertEquals('1', $client->settings->currency_id);
        $this->assertEquals('888-867-5309', $client->phone);
    }

    public function testInvoiceZohoImport()
    {
        //first import all the clients
        $csv = file_get_contents(
            base_path().'/tests/Feature/Import/zoho_contacts.csv'
        );
        $hash = Str::random(32);

        $column_map = [
            0 => 'Created Time',
            1 => 'Last Modified Time',
            2 => 'Display Name',
            3 => 'Company Name',
            4 => 'Salutation',
            5 => 'First Name',
            6 => 'Last Name',
            7 => 'Phone',
            8 => 'Currency Code',
            9 => 'Notes',
            10 => 'Website',
            11 => 'Status',
            12 => 'Credit Limit',
            13 => 'Customer Sub Type',
            14 => 'Billing Attention',
            15 => 'Billing Address',
            16 => 'Billing Street2',
            17 => 'Billing City',
            18 => 'Billing State',
            19 => 'Billing Country',
            20 => 'Billing Code',
            21 => 'Billing Phone',
            22 => 'Billing Fax',
            23 => 'Shipping Attention',
            24 => 'Shipping Address',
            25 => 'Shipping Street2',
            26 => 'Shipping City',
            27 => 'Shipping State',
            28 => 'Shipping Country',
            29 => 'Shipping Code',
            30 => 'Shipping Phone',
            31 => 'Shipping Fax',
            32 => 'Skype Identity',
            33 => 'Facebook',
            34 => 'Twitter',
            35 => 'Department',
            36 => 'Designation',
            37 => 'Price List',
            38 => 'Payment Terms',
            39 => 'Payment Terms Label',
            40 => 'Last Sync Time',
            41 => 'Owner Name',
            42 => 'EmailID',
            43 => 'MobilePhone',
            44 => 'Customer ID',
            45 => 'Customer Name',
            46 => 'Contact Type',
            47 => 'Customer Address ID',
            48 => 'Source',
            49 => 'Reference ID',
            50 => 'Payment Reminder',
        ];

        $data = [
            'hash' => $hash,
            'column_map' => ['client' => ['mapping' => $column_map]],
            'skip_header' => true,
            'import_type' => 'zoho',
        ];

        Cache::put($hash.'-client', base64_encode($csv), 360);

        $csv_importer = new Zoho($data, $this->company);

        $count = $csv_importer->import('client');

        //now import the invoices

        $csv = file_get_contents(
            base_path().'/tests/Feature/Import/zoho_invoices.csv'
        );
        $hash = Str::random(32);

        $column_map = [
            0 => 'Invoice Date',
            1 => 'Invoice ID',
            2 => 'Invoice Number',
            3 => 'Estimate Number',
            4 => 'Invoice Status',
            5 => 'Customer Name',
            6 => 'Company Name',
            7 => 'Customer ID',
            8 => 'Branch ID',
            9 => 'Branch Name',
            10 => 'Due Date',
            11 => 'Expected Payment Date',
            12 => 'PurchaseOrder',
            13 => 'Template Name',
            14 => 'Currency Code',
            15 => 'Exchange Rate',
            16 => 'Discount Type',
            17 => 'Is Discount Before Tax',
            18 => 'Entity Discount Percent',
            19 => 'Entity Discount Amount',
            20 => 'Item Name',
            21 => 'Item Desc',
            22 => 'Quantity',
            23 => 'Usage unit',
            24 => 'Item Price',
            25 => 'Discount',
            26 => 'Discount Amount',
            27 => 'Expense Reference ID',
            28 => 'Project ID',
            29 => 'Project Name',
            30 => 'Item Total',
            31 => 'SubTotal',
            32 => 'Total',
            33 => 'Balance',
            34 => 'Shipping Charge',
            35 => 'Adjustment',
            36 => 'Adjustment Description',
            37 => 'Round Off',
            38 => 'Sales person',
            39 => 'Payment Terms',
            40 => 'Payment Terms Label',
            41 => 'Last Payment Date',
            42 => 'Notes',
            43 => 'Terms & Conditions',
            44 => 'Subject',
            45 => 'LateFee Name',
            46 => 'LateFee Type',
            47 => 'LateFee Rate',
            48 => 'LateFee Amount',
            49 => 'LateFee Frequency',
            50 => 'WriteOff Date',
            51 => 'WriteOff Exchange Rate',
            52 => 'WriteOff Amount',
            53 => 'Recurrence Name',
            54 => 'PayPal',
            55 => 'Authorize.Net',
            56 => 'Google Checkout',
            57 => 'Payflow Pro',
            58 => 'Stripe',
            59 => '2Checkout',
            60 => 'Braintree',
            61 => 'Forte',
            62 => 'WorldPay',
            63 => 'Payments Pro',
            64 => 'Square',
            65 => 'WePay',
            66 => 'Razorpay',
            67 => 'GoCardless',
            68 => 'Partial Payments',
            69 => 'Billing Address',
            70 => 'Billing City',
            71 => 'Billing State',
            72 => 'Billing Country',
            73 => 'Billing Code',
            74 => 'Billing Fax',
            75 => 'Billing Phone',
            76 => 'Shipping Address',
            77 => 'Shipping City',
            78 => 'Shipping State',
            79 => 'Shipping Country',
            80 => 'Shipping Code',
            81 => 'Shipping Fax',
            82 => 'Shipping Phone Number',
        ];

        $data = [
            'hash' => $hash,
            'column_map' => ['invoice' => ['mapping' => $column_map]],
            'skip_header' => true,
            'import_type' => 'zoho',
        ];

        Cache::put($hash.'-invoice', base64_encode($csv), 360);

        $csv_importer = new Zoho($data, $this->company);

        $count = $csv_importer->import('invoice');

        $base_transformer = new BaseTransformer($this->company);

        $this->assertTrue($base_transformer->hasInvoice('INV-000001'));
        $this->assertTrue($base_transformer->hasInvoice('INV-000002'));
        $this->assertTrue($base_transformer->hasInvoice('INV-000003'));

        $invoice_id = $base_transformer->getInvoiceId('INV-000003');
        $invoice = Invoice::find($invoice_id);

        $this->assertEquals(390, $invoice->amount);
        $this->assertEquals(1, $invoice->status_id);
        $this->assertEquals(0, $invoice->balance);
        $this->assertEquals(4, count($invoice->line_items));

        $this->assertFalse($invoice->payments()->exists());
    }
}
