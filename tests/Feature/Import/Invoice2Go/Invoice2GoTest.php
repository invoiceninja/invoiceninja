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

namespace Tests\Feature\Import\Invoice2Go;

use App\Import\Providers\Invoice2Go;
use App\Import\Transformer\BaseTransformer;
use App\Models\Client;
use App\Models\Invoice;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * 
 *  App\Import\Providers\Invoice2Go
 */
class Invoice2GoTest extends TestCase
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

    public function testInvoice2GoImport()
    {
        $csv = file_get_contents(
            base_path().'/tests/Feature/Import/i2g_invoices.csv'
        );
        $hash = Str::random(32);

        $column_map = [
            0 => 'Id',
            1 => 'AccountId',
            2 => 'State',
            3 => 'LastUpdated',
            4 => 'CreatedDate',
            5 => 'DocumentDate',
            6 => 'DocumentNumber',
            7 => 'Comment',
            8 => 'Name',
            9 => 'EmailRecipient',
            10 => 'DocumentStatus',
            11 => 'OutputStatus',
            12 => 'PartPayment',
            13 => 'DocumentRecipientAddress',
            14 => 'ShipDate',
            15 => 'ShipAddress',
            16 => 'ShipAmount',
            17 => 'ShipTrackingNumber',
            18 => 'ShipVia',
            19 => 'ShipFob',
            20 => 'StyleName',
            21 => 'DatePaid',
            22 => 'CustomField',
            23 => 'DiscountType',
            24 => 'Discount',
            25 => 'DiscountValue',
            26 => 'Taxes',
            27 => 'WithholdingTaxName',
            28 => 'WithholdingTaxRate',
            29 => 'TermsOld',
            30 => 'Payments',
            31 => 'Items',
            32 => 'CurrencyCode',
            33 => 'TotalAmount',
            34 => 'BalanceDueAmount',
            35 => 'AmountPaidAmount',
            36 => 'DiscountAmount',
            37 => 'SubtotalAmount',
            38 => 'TotalTaxAmount',
            39 => 'WithholdingTaxAmount',
        ];

        $data = [
            'hash' => $hash,
            'column_map' => ['invoice' => ['mapping' => $column_map]],
            'skip_header' => true,
            'import_type' => 'invoice2go',
        ];

        Cache::put($hash.'-invoice', base64_encode($csv), 360);

        $csv_importer = new Invoice2Go($data, $this->company);

        $count = $csv_importer->import('invoice');

        $base_transformer = new BaseTransformer($this->company);

        $this->assertTrue($base_transformer->hasClient('Barry Gordon'));
        $this->assertTrue($base_transformer->hasClient('James Gordon'));
        $this->assertTrue($base_transformer->hasClient('Deadpool Inc'));
        $this->assertTrue($base_transformer->hasClient('Daily Planet'));

        $client_id = $base_transformer->getClient('', 'wade@projectx.net');

        $client = Client::find($client_id);

        $this->assertInstanceOf(Client::class, $client);
        // $this->assertEquals('840', $client->country_id);
        // $this->assertEquals('wade@projectx.net', $client->contacts->first()->email);
        // $this->assertEquals('2584 Sesame Street', $client->address1);

        $this->assertTrue($base_transformer->hasInvoice('1'));
        $this->assertTrue($base_transformer->hasInvoice('2'));
        $this->assertTrue($base_transformer->hasInvoice('3'));
        $this->assertTrue($base_transformer->hasInvoice('4'));
        $invoice_id = $base_transformer->getInvoiceId('1');
        $invoice = Invoice::find($invoice_id);

        $this->assertEquals(953.55, $invoice->amount);
        $this->assertEquals(1, $invoice->status_id);
        $this->assertEquals(0, $invoice->balance);
    }
}
