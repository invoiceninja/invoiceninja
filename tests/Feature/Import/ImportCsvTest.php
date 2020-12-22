<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */
namespace Tests\Feature\Import;

use App\Jobs\Import\CSVImport;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
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

    public function setUp() :void
    {
        parent::setUp();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        // $this->faker = \Faker\Factory::create();

        $this->makeTestData();

        $this->withoutExceptionHandling();
    }

    public function testCsvRead()
    {
        $csv = file_get_contents(base_path().'/tests/Feature/Import/invoice.csv');

        $this->assertTrue(is_array($this->getCsvData($csv)));
    }

    public function testInvoiceCsvImport()
    {
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
            'hash' => $hash,
            'column_map' => $column_map,
            'skip_header' => true,
            'entity_type'=> 'invoice',
        ];

        $pre_import = Invoice::count();

        Cache::put($hash, base64_encode($csv), 360);

        CSVImport::dispatchNow($data, $this->company);

        $this->assertGreaterThan($pre_import, Client::count());
    }


    public function testClientCsvImport()
    {
        $this->markTestSkipped();

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
            'hash' => $hash,
            'column_map' => $column_map,
            'skip_header' => true,
            'entity_type'=> 'client',
        ];

        $pre_import = Client::count();

        Cache::put($hash, base64_encode($csv), 360);

        CSVImport::dispatchNow($data, $this->company);

        $this->assertGreaterThan($pre_import, Client::count());
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
            'hash' => $hash,
            'column_map' => $column_map,
            'skip_header' => true,
            'entity_type'=> 'product',
        ];

        $pre_import = Product::count();
        
        Cache::put($hash, base64_encode($csv), 360);

        CSVImport::dispatchNow($data, $this->company);

        $this->assertGreaterThan($pre_import, Product::count());
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
