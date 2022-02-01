<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */
namespace Tests\Feature\Import\CSV;

use App\Import\Providers\Csv;
use App\Import\Transformer\BaseTransformer;
use App\Jobs\Import\CSVImport;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\TaxRate;
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
 * @covers App\Import\Providers\Csv
 */
class CsvImportTest extends TestCase
{
    use MakesHash;
    use MockAccountData;

    public function setUp() :void
    {
        parent::setUp();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        config(['database.default' => config('ninja.db.default')]);

        $this->makeTestData();
  
        $this->withoutExceptionHandling();
    }

    public function testCsvFeature()
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
             24 => 'contact.email',
        ];

        $data = [
            'hash'        => $hash,
            'column_map'  => [ 'client' => [ 'mapping' => $column_map ] ],
            'skip_header' => true,
            'import_type' => 'csv',
        ];

        Cache::put( $hash . '-client', base64_encode( $csv ), 360 );

        $csv_importer = new Csv($data, $this->company);
     
        $this->assertInstanceOf(Csv::class, $csv_importer);

        $csv_importer->import('client');

        $base_transformer = new BaseTransformer($this->company);

        $this->assertTrue($base_transformer->hasClient("Ludwig Krajcik DVM"));

        $client_id = $base_transformer->getClient("Ludwig Krajcik DVM", null);

        $c = Client::find($client_id);

        $this->assertEquals($client_id, $c->id);

        $client_id = $base_transformer->getClient("a non existent clent", "brook59@example.org");

        $this->assertEquals($client_id, $c->id);

    }

}


    // public function testClientCsvImport()
    // {
    //  $csv = file_get_contents(base_path().'/tests/Feature/Import/clients.csv');
    //  $hash = Str::random(32);
    //  $column_map = [
    //      1 => 'client.balance',
    //      2 => 'client.paid_to_date',
    //      0 => 'client.name',
    //      19 => 'client.currency_id',
    //      20 => 'client.public_notes',
    //      21 => 'client.private_notes',
    //      22 => 'contact.first_name',
    //      23 => 'contact.last_name',
    //  ];

    //  $data = [
    //      'hash'        => $hash,
    //      'column_map'  => [ 'client' => [ 'mapping' => $column_map ] ],
    //      'skip_header' => true,
    //      'import_type' => 'csv',
    //  ];

    //  $pre_import = Client::count();

    //  Cache::put( $hash . '-client', base64_encode( $csv ), 360 );

    //  CSVImport::dispatchNow( $data, $this->company );

    //  $this->assertGreaterThan( $pre_import, Client::count() );
    // }
