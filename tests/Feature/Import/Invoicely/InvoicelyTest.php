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

namespace Tests\Feature\Import\Invoicely;

use App\Import\Providers\Invoicely;
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
 * @test
 * @covers App\Import\Providers\Invoicely
 */
class InvoicelyTest extends TestCase
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

    public function testClientInvoicelyImport()
    {
        $csv = file_get_contents(
            base_path().'/tests/Feature/Import/invoicely_clients.csv'
        );
        $hash = Str::random(32);

        $column_map = [
            0 => 'Client Name',
            1 => 'Email',
            2 => 'Phone',
            3 => 'Country',
        ];

        $data = [
            'hash' => $hash,
            'column_map' => ['client' => ['mapping' => $column_map]],
            'skip_header' => true,
            'import_type' => 'invoicely',
        ];

        Cache::put($hash.'-client', base64_encode($csv), 360);

        $csv_importer = new Invoicely($data, $this->company);

        $count = $csv_importer->import('client');

        $base_transformer = new BaseTransformer($this->company);

        $this->assertTrue($base_transformer->hasClient('Alexander Hamilton'));
        $this->assertTrue($base_transformer->hasClient('Bruce Wayne'));

        $client_id = $base_transformer->getClient('', 'alexander@iamhamllton.com');

        $client = Client::find($client_id);

        $this->assertInstanceOf(Client::class, $client);
        $this->assertEquals('5558675309', $client->phone);
    }

    public function testInvoiceInvoicelyImport()
    {
        $csv = file_get_contents(
            base_path().'/tests/Feature/Import/invoicely_clients.csv'
        );
        $hash = Str::random(32);

        $column_map = [
            0 => 'Client Name',
            1 => 'Email',
            2 => 'Phone',
            3 => 'Country',
        ];

        $data = [
            'hash' => $hash,
            'column_map' => ['client' => ['mapping' => $column_map]],
            'skip_header' => true,
            'import_type' => 'invoicely',
        ];

        Cache::put($hash.'-client', base64_encode($csv), 360);

        $csv_importer = new Invoicely($data, $this->company);

        $count = $csv_importer->import('client');

        $base_transformer = new BaseTransformer($this->company);

        $this->assertTrue($base_transformer->hasClient('Alexander Hamilton'));
        $this->assertTrue($base_transformer->hasClient('Bruce Wayne'));

        $client_id = $base_transformer->getClient('', 'alexander@iamhamllton.com');

        $client = Client::find($client_id);

        $this->assertInstanceOf(Client::class, $client);
        $this->assertEquals('5558675309', $client->phone);
        //now import the invoices

        $csv = file_get_contents(
            base_path().'/tests/Feature/Import/invoicely_invoices.csv'
        );
        $hash = Str::random(32);

        $column_map = [
            0 => 'Date',
            1 => 'Due',
            2 => 'Details',
            3 => 'Client',
            4 => 'Status',
            5 => 'Total',
        ];

        $data = [
            'hash' => $hash,
            'column_map' => ['invoice' => ['mapping' => $column_map]],
            'skip_header' => true,
            'import_type' => 'invoicely',
        ];

        Cache::put($hash.'-invoice', base64_encode($csv), 360);

        $csv_importer = new Invoicely($data, $this->company);

        $count = $csv_importer->import('invoice');

        $base_transformer = new BaseTransformer($this->company);


        $this->assertTrue($base_transformer->hasInvoice('INV-1'));

        $invoice_id = $base_transformer->getInvoiceId('INV-1');
        $invoice = Invoice::find($invoice_id);

        $this->assertEquals(1020, $invoice->amount);
        $this->assertEquals(2, $invoice->status_id);
        $this->assertEquals(1020, $invoice->balance);
        $this->assertEquals(1, count($invoice->line_items));

        $this->assertFalse($invoice->payments()->exists());
    }
}
