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

namespace Tests\Feature\Import\CSV;

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
 * @covers App\Import\Transformer\BaseTransformer
 */
class BaseTransformerTest extends TestCase
{
    use MakesHash;
    use MockAccountData;

    protected function setUp() :void
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
            'key' => 'value',
        ];

        $field = 'key';

        $this->assertEquals('value', $base_transformer->getString($data, $field));
    }

    public function testGetCurrencyCode()
    {
        $base_transformer = new BaseTransformer($this->company);

        $code = ['client.currency_id' => 'USD'];

        $currency_id = $base_transformer->getCurrencyByCode($code);

        $this->assertEquals(1, $currency_id);
    }

    public function testGetClient()
    {
        $base_transformer = new BaseTransformer($this->company);

        $client = Client::factory()->create([
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
            'email' => 'test@gmail.com',
        ]);

        $this->assertEquals($client->id, $base_transformer->getClient('hit', 'null'));
        $this->assertEquals($client->id, $base_transformer->getClient('magic', 'null'));
        $this->assertEquals($client->id, $base_transformer->getClient('nomagic', 'test@gmail.com'));
        $this->assertEquals($client->id, $base_transformer->getClient(null, 'test@gmail.com'));
        $this->assertNull($base_transformer->getClient('null', 'notest@gmail.com'));

        $this->assertEquals($client->id, $base_transformer->getClientId(' magic'));
        $this->assertEquals($client->id, $base_transformer->getClientId('Magic '));
    }

    public function testGetContact()
    {
        $base_transformer = new BaseTransformer($this->company);

        $client = Client::factory()->create([
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
            'email' => 'test@gmail.com',
        ]);

        $this->assertEquals($contact->id, $base_transformer->getContact('TeSt@gmail.com')->id);
        $this->assertEquals($contact->id, $base_transformer->getContact('TeSt@gmail.com ')->id);
        $this->assertEquals($contact->id, $base_transformer->getContact('TeSt@gmaiL.com')->id);
    }

    public function testHasClient()
    {
        $base_transformer = new BaseTransformer($this->company);

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'id_number' => 'hit',
            'name' => 'maGic ',
        ]);

        $this->assertTrue($base_transformer->hasClient('magic'));
        $this->assertTrue($base_transformer->hasClient('Magic'));
        $this->assertTrue($base_transformer->hasClient('Ma gi c '));
    }

    public function testHasVendor()
    {
        $base_transformer = new BaseTransformer($this->company);

        $client = Vendor::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'id_number' => 'hit',
            'name' => 'maGic ',
        ]);

        $this->assertTrue($base_transformer->hasVendor('magic'));
        $this->assertTrue($base_transformer->hasVendor('Magic'));
        $this->assertTrue($base_transformer->hasVendor('Ma gi c '));
    }

    public function testHasProduct()
    {
        $base_transformer = new BaseTransformer($this->company);

        $client = Product::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'product_key' => 'HiT ',
        ]);

        $this->assertTrue($base_transformer->hasProduct('hit'));
        $this->assertTrue($base_transformer->hasProduct(' hIt'));
        $this->assertTrue($base_transformer->hasProduct('  h i T '));
    }

    public function testGetCountryId()
    {
        $base_transformer = new BaseTransformer($this->company);

        $this->assertEquals(840, $base_transformer->getCountryId('us'));
        $this->assertEquals(840, $base_transformer->getCountryId('US'));
        $this->assertEquals(840, $base_transformer->getCountryId('United States'));
    }

    public function testGetTaxRate()
    {
        $base_transformer = new BaseTransformer($this->company);

        $client = TaxRate::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'rate' => '10',
            'name' => 'GST',
        ]);

        $this->assertEquals(10, $base_transformer->getTaxRate('gst'));
        $this->assertEquals(10, $base_transformer->getTaxRate(' GST'));
        $this->assertEquals(10, $base_transformer->getTaxRate('  gS t '));
    }

    public function testGetTaxName()
    {
        $base_transformer = new BaseTransformer($this->company);

        $client = TaxRate::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'rate' => '17.5',
            'name' => 'VAT',
        ]);

        $this->assertEquals('VAT', $base_transformer->getTaxName('vat'));
        $this->assertEquals('VAT', $base_transformer->getTaxName(' VaT'));
        $this->assertEquals('VAT', $base_transformer->getTaxName('  va T '));
    }

    public function testGetInvoiceId()
    {
        $base_transformer = new BaseTransformer($this->company);

        $invoice = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'number' => 'trick_number_123',
        ]);

        $this->assertEquals($invoice->id, $base_transformer->getInvoiceId('TRICK_number_123'));
        $this->assertEquals($invoice->id, $base_transformer->getInvoiceId(' TRICK_number_123'));
        $this->assertEquals($invoice->id, $base_transformer->getInvoiceId('  TRICK_number_123 '));
    }

    public function testHasInvoiceWithNumber()
    {
        $base_transformer = new BaseTransformer($this->company);

        $client = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'number' => 'tricky_number_123',
        ]);

        $this->assertTrue($base_transformer->hasInvoice('TRICKY_number_123'));
        $this->assertTrue($base_transformer->hasInvoice(' TRICKY_number_123'));
        $this->assertTrue($base_transformer->hasInvoice('  TRICKY_number_123 '));
    }

    public function testInvoiceClientId()
    {
        $base_transformer = new BaseTransformer($this->company);

        $client = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'number' => 'tricky_number_123',
        ]);

        $this->assertEquals($this->client->id, $base_transformer->getInvoiceClientId('TRICKY_number_123'));
        $this->assertEquals($this->client->id, $base_transformer->getInvoiceClientId(' TRICKY_number_123'));
        $this->assertEquals($this->client->id, $base_transformer->getInvoiceClientId('  TRICKY_number_123 '));
    }

    public function testGetVendorId()
    {
        $base_transformer = new BaseTransformer($this->company);

        $vendor = Vendor::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'id_number' => 'hit',
            'name' => 'maGic ',
        ]);

        $this->assertEquals($vendor->id, $base_transformer->getVendorId('magic'));
        $this->assertEquals($vendor->id, $base_transformer->getVendorId('Magic'));
        $this->assertEquals($vendor->id, $base_transformer->getVendorId('Ma gi c '));
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
