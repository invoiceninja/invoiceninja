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

use App\Import\Transformer\BaseTransformer;
use App\Jobs\Import\CSVImport;
use App\Models\Client;
use App\Models\ClientContact;
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
 * @covers App\Import\Transformer\BaseTransformer
 */
class BaseTransformerTest extends TestCase
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

        // $this->faker = \Faker\Factory::create();

        $this->makeTestData();
  
        $this->withoutExceptionHandling();
    }

    public function testGetString()
    {
        $base_transformer = new BaseTransformer($this->company);

        $data = [
        	'key' => 'value'
        ];

        $field = 'key';

		$this->assertEquals('value', $base_transformer->getString($data, $field));

    }

    public function testGetCurrencyCode()
    {
        $base_transformer = new BaseTransformer($this->company);

    	$code = ['client.currency_id' => "USD"];

    	$currency_id = $base_transformer->getCurrencyByCode($code);

    	$this->assertEquals(1, $currency_id);
    }

    public function testGetClient()
	{
        $base_transformer = new BaseTransformer($this->company);

		$client = 		Client::factory()->create([
				                'user_id' => $this->user->id,
				                'company_id' => $this->company->id,
				                'id_number' => 'hit',
				                'name' => 'magic ',
				        ]);

        $contact = ClientContact::factory()->create([
                'user_id' => $this->user->id,
                'client_id' => $client->id,
                'company_id' => $this->company->id,
                'is_primary' => 1,
                'send_email' => true,
                'email' => 'test@gmail.com'
        ]);


        $this->assertEquals($client->id, $base_transformer->getClient('hit', 'null'));
        $this->assertEquals($client->id, $base_transformer->getClient('magic', 'null'));
        $this->assertEquals($client->id, $base_transformer->getClient('nomagic', 'test@gmail.com'));

    	$this->assertEquals($client->id, $base_transformer->getClient(null, 'test@gmail.com'));
        $this->assertNull($base_transformer->getClient('null', 'notest@gmail.com'));

	}




	// public function testClientCsvImport()
	// {
	// 	$csv = file_get_contents(base_path().'/tests/Feature/Import/clients.csv');
	// 	$hash = Str::random(32);
	// 	$column_map = [
	// 		1 => 'client.balance',
	// 		2 => 'client.paid_to_date',
	// 		0 => 'client.name',
	// 		19 => 'client.currency_id',
	// 		20 => 'client.public_notes',
	// 		21 => 'client.private_notes',
	// 		22 => 'contact.first_name',
	// 		23 => 'contact.last_name',
	// 	];

	// 	$data = [
	// 		'hash'        => $hash,
	// 		'column_map'  => [ 'client' => [ 'mapping' => $column_map ] ],
	// 		'skip_header' => true,
	// 		'import_type' => 'csv',
	// 	];

	// 	$pre_import = Client::count();

	// 	Cache::put( $hash . '-client', base64_encode( $csv ), 360 );

	// 	CSVImport::dispatchNow( $data, $this->company );

	// 	$this->assertGreaterThan( $pre_import, Client::count() );
	// }

}
