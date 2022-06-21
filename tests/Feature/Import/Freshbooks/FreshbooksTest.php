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

namespace Tests\Feature\Import\Freshbooks;

use App\Import\Providers\BaseImport;
use App\Import\Providers\Freshbooks;
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
use Illuminate\Support\Str;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers App\Import\Providers\Freshbooks
 */
class FreshbooksTest extends TestCase
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

    public function testClientFreshbooksImport()
    {
        $csv = file_get_contents(
            base_path().'/tests/Feature/Import/freshbooks_clients.csv'
        );
        $hash = Str::random(32);

        $column_map = [
            0 => 'Organization',
            1 => 'First Name',
            2 => 'Last Name',
            3 => 'Email',
            4 => 'Phone',
            5 => 'Street',
            6 => 'City',
            7 => 'Province/State',
            8 => 'Country',
            9 => 'Postal Code',
            10 => 'Notes',
        ];

        $data = [
            'hash' => $hash,
            'column_map' => ['client' => ['mapping' => $column_map]],
            'skip_header' => true,
            'import_type' => 'freshbooks',
        ];

        Cache::put($hash.'-client', base64_encode($csv), 360);

        $csv_importer = new Freshbooks($data, $this->company);

        $count = $csv_importer->import('client');

        $base_transformer = new BaseTransformer($this->company);

        $this->assertTrue($base_transformer->hasClient('Marge Simpson'));
        $this->assertTrue($base_transformer->hasClient('X-Men'));

        $client_id = $base_transformer->getClient('', 'marge@simpsons.com');

        $client = Client::find($client_id);

        $this->assertInstanceOf(Client::class, $client);
        $this->assertEquals('840', $client->country_id);
        $this->assertEquals('867-5309', $client->phone);
    }

    public function testInvoiceZohoImport()
    {
        $csv = file_get_contents(
            base_path().'/tests/Feature/Import/freshbooks_clients.csv'
        );
        $hash = Str::random(32);

        $column_map = [
            0 => 'Organization',
            1 => 'First Name',
            2 => 'Last Name',
            3 => 'Email',
            4 => 'Phone',
            5 => 'Street',
            6 => 'City',
            7 => 'Province/State',
            8 => 'Country',
            9 => 'Postal Code',
            10 => 'Notes',
        ];

        $data = [
            'hash' => $hash,
            'column_map' => ['client' => ['mapping' => $column_map]],
            'skip_header' => true,
            'import_type' => 'freshbooks',
        ];

        Cache::put($hash.'-client', base64_encode($csv), 360);

        $csv_importer = new Freshbooks($data, $this->company);

        $count = $csv_importer->import('client');

        $base_transformer = new BaseTransformer($this->company);

        $this->assertTrue($base_transformer->hasClient('Marge Simpson'));
        $this->assertTrue($base_transformer->hasClient('X-Men'));

        $client_id = $base_transformer->getClient('', 'marge@simpsons.com');

        $client = Client::find($client_id);

        $this->assertInstanceOf(Client::class, $client);
        $this->assertEquals('840', $client->country_id);
        $this->assertEquals('867-5309', $client->phone);

        //now import the invoices

        $csv = file_get_contents(
            base_path().'/tests/Feature/Import/freshbooks_invoices.csv'
        );
        $hash = Str::random(32);

        $column_map = [
            0 => 'Client Name',
            1 => 'Invoice #',
            2 => 'Date Issued',
            3 => 'Invoice Status',
            4 => 'Date Paid',
            5 => 'Item Name',
            6 => 'Item Description',
            7 => 'Rate',
            8 => 'Quantity',
            9 => 'Discount Percentage',
            10 => 'Line Subtotal',
            11 => 'Tax 1 Type',
            12 => 'Tax 1 Amount',
            13 => 'Tax 2 Type',
            14 => 'Tax 2 Amount',
            15 => 'Line Total',
            16 => 'Currency',
        ];

        $data = [
            'hash' => $hash,
            'column_map' => ['invoice' => ['mapping' => $column_map]],
            'skip_header' => true,
            'import_type' => 'freshbooks',
        ];

        Cache::put($hash.'-invoice', base64_encode($csv), 360);

        $csv_importer = new Freshbooks($data, $this->company);

        $count = $csv_importer->import('invoice');

        $base_transformer = new BaseTransformer($this->company);

        $this->assertTrue($base_transformer->hasInvoice('0000001'));
        $invoice_id = $base_transformer->getInvoiceId('0000001');
        $invoice = Invoice::find($invoice_id);

        $this->assertEquals(7890, $invoice->amount);
        $this->assertEquals(2, $invoice->status_id);
        $this->assertEquals(7890, $invoice->balance);
        $this->assertEquals(3, count($invoice->line_items));

        $this->assertFalse($invoice->payments()->exists());
    }
}
