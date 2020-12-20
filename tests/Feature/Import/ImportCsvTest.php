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
            0 => 'invoice.number',
            4 => 'invoice.client_id',
            7 => 'invoice.date',
            8 => 'invoice.due_date',
            9 => 'invoice.terms',
            10 => 'invoice.public_notes',
            11 => 'invoice.is_sent',
            12 => 'invoice.private_notes',
            13 => 'invoice.uses_inclusive_taxes',
            14 => 'invoice.tax_name1',
            15 => 'invoice.tax_rate1',
            16 => 'invoice.tax_name2',
            17 => 'invoice.tax_rate2',
            18 => 'invoice.tax_name3',
            19 => 'invoice.tax_rate3',
            21 => 'invoice.footer',
            22 => 'invoice.partial',
            23 => 'invoice.partial_due_date',
            33 => 'payment.date',
            34 => 'payment.amount',
            35 => 'payment.transaction_reference',
            36 => 'item.quantity',
            37 => 'item.cost',
            38 => 'item.product_key',
            39 => 'item.notes',
            40 => 'item.discount',
            41 => 'item.is_amount_discount',
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
