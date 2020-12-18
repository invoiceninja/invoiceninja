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

        Cache::put($hash, base64_encode($csv), 360);

        $this->markTestSkipped();
        // CSVImport::dispatchNow($data, $this->company);
    }


    public function testProductCsvImport()
    {
        $csv = file_get_contents(base_path().'/tests/Feature/Import/products.csv');
        $hash = Str::random(32);
        $column_map = [
            1 => 'product.product_key',
            2 => 'product.notes',
            3 => 'product.cost',
            4 => 'product.user_id',
        ];

        $data = [
            'hash' => $hash,
            'column_map' => $column_map,
            'skip_header' => true,
            'entity_type'=> 'product',
        ];

        Cache::put($hash, base64_encode($csv), 360);
        $this->markTestSkipped();

        CSVImport::dispatchNow($data, $this->company);
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
