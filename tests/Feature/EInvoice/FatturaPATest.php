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

namespace Tests\Feature\EInvoice;

use Tests\TestCase;
use App\Models\Client;
use App\Models\Company;
use Tests\MockAccountData;
use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\DataMapper\InvoiceItem;
use App\Models\Invoice;
use InvoiceNinja\EInvoice\Symfony\Encode;
use App\Services\EDocument\Standards\FatturaPANew;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use InvoiceNinja\EInvoice\EInvoice;
use InvoiceNinja\EInvoice\Models\FatturaPA\FatturaElettronica;
use InvoiceNinja\EInvoice\Models\FatturaPA\FatturaElettronicaBodyType\FatturaElettronicaBody;
use InvoiceNinja\EInvoice\Models\FatturaPA\FatturaElettronicaHeaderType\FatturaElettronicaHeader;

/**
 * 
 */
class FatturaPATest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();


        // $this->markTestSkipped('prevent running in CI');

        $this->withoutMiddleware(
            ThrottleRequests::class
        );
    }

    public function testInvoiceBoot()
    {

        $settings = CompanySettings::defaults();
        $settings->address1 = 'Via Silvio Spaventa 108';
        $settings->city = 'Calcinelli';

        $settings->state = 'PA';

        // $settings->state = 'Perugia';
        $settings->postal_code = '61030';
        $settings->country_id = '380';
        $settings->currency_id = '3';
        $settings->vat_number = '01234567890';
        $settings->id_number = '';

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
        ]);

        $client_settings = ClientSettings::defaults();
        $client_settings->currency_id = '3';

        $client = Client::factory()->create([
            'company_id' => $company->id,
            'user_id' => $this->user->id,
            'name' => 'Italian Client Name',
            'address1' => 'Via Antonio da Legnago 68',
            'city' => 'Monasterace',
            'state' => 'CR',
            // 'state' => 'Reggio Calabria',
            'postal_code' => '89040',
            'country_id' => 380,
            'routing_id' => 'ABC1234',
            'settings' => $client_settings,
        ]);

        $item = new InvoiceItem();
        $item->product_key = "Product Key";
        $item->notes = "Product Description";
        $item->cost = 10;
        $item->quantity = 10;
        $item->tax_rate1 = 22;
        $item->tax_name1 = 'IVA';

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'discount' => 0,
            'uses_inclusive_taxes' => false,
            'status_id' => 1,
            'tax_rate1' => 0,
            'tax_name1' => '',
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'tax_name2' => '',
            'tax_name3' => '',
            'line_items' => [$item],
            'number' => 'ITA-'.rand(1000, 100000)
        ]);

        $invoice->service()->markSent()->save();

        $fat = new FatturaPANew($invoice);
        $fat->run();

        $fe = $fat->getFatturaElettronica();

        $this->assertNotNull($fe);

        $this->assertInstanceOf(FatturaElettronica::class, $fe);
        $this->assertInstanceOf(FatturaElettronicaBody::class, $fe->FatturaElettronicaBody[0]);
        $this->assertInstanceOf(FatturaElettronicaHeader::class, $fe->FatturaElettronicaHeader);

        $e = new EInvoice();
        $errors = $e->validate($fe);

        

        if(count($errors) > 0) {
            nlog($errors);
        }

        $this->assertCount(0, $errors);

        $xml = $e->encode($fe, 'xml');
        $this->assertNotNull($xml);

        $json = $e->encode($fe, 'json');
        $this->assertNotNull($json);

        $decode = $e->decode('FatturaPA', $json, 'json');

        $this->assertInstanceOf(FatturaElettronica::class, $decode);
    }
}
