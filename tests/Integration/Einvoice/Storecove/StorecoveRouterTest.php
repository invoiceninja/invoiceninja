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

namespace Tests\Integration\Einvoice\Storecove;

use Tests\TestCase;
use App\Models\User;
use App\Models\Client;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use Illuminate\Routing\Middleware\ThrottleRequests;
use App\Services\EDocument\Gateway\Storecove\Storecove;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class StorecoveRouterTest extends TestCase
{
    use DatabaseTransactions;

    protected $faker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->faker = \Faker\Factory::create();

    }

    private function buildData()
    {

        $account = Account::factory()->create();
        $company = Company::factory()->create([
            'account_id' => $account->id,
        ]);

        $user = User::factory()->create([
            'account_id' => $account->id,
            'confirmation_code' => 'xyz123',
            'email' => \Illuminate\Support\Str::random(32)."@example.com",
            'password' => \Illuminate\Support\Facades\Hash::make('ALongAndBriliantPassword'),
        ]);

        $client = Client::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
        ]);

        $invoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'client_id' => $client->id
        ]);

        $invoice->service()->markSent()->save();

        return $invoice;

    }

    public function testIsBusinessTaxIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 352;
        $client->vat_number = 'IS1234567890';
        $client->classification = 'business';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals("IS:VAT", $storecove->router->resolveTaxScheme('IS', 'business'));

    }

    // Luxembourg Tests
    public function testLuBusinessClientRoutingIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 442;
        $client->vat_number = 'LU12345678';
        $client->classification = 'business';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('LU:VAT', $storecove->router->resolveRouting('LU', 'business'));
    }

    public function testLuGovClientRoutingIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 442;
        $client->vat_number = 'LU12345678';
        $client->classification = 'government';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('LU:VAT', $storecove->router->resolveRouting('LU', 'government'));
    }

    public function testLuBusinessClientTaxIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 442;
        $client->vat_number = 'LU12345678';
        $client->classification = 'business';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('LU:VAT', $storecove->router->resolveTaxScheme('LU', 'business'));
    }

    public function testLuGovClientTaxIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 442;
        $client->vat_number = 'LU12345678';
        $client->classification = 'government';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals("LU:VAT", $storecove->router->resolveTaxScheme('LU', 'government'));
    }

    // Norway Tests
    public function testNoBusinessClientRoutingIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 578;
        $client->vat_number = 'NO123456789';
        $client->classification = 'business';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('NO:ORG', $storecove->router->resolveRouting('NO', 'business'));
    }

    public function testNoGovClientRoutingIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 578;
        $client->vat_number = 'NO123456789';
        $client->classification = 'government';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('NO:ORG', $storecove->router->resolveRouting('NO', 'government'));
    }

    public function testNoBusinessClientTaxIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 578;
        $client->vat_number = 'NO123456789';
        $client->classification = 'business';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('NO:VAT', $storecove->router->resolveTaxScheme('NO', 'business'));
    }

    public function testNoGovClientTaxIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 578;
        $client->vat_number = 'NO123456789';
        $client->classification = 'government';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals("NO:VAT", $storecove->router->resolveTaxScheme('NO', 'government'));
    }

    // Netherlands Tests
    public function testNlBusinessClientRoutingIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 528;
        $client->vat_number = 'NL123456789B01';
        $client->classification = 'business';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('NL:VAT', $storecove->router->resolveRouting('NL', 'business'));
    }

    public function testNlGovClientRoutingIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 528;
        $client->vat_number = 'NL123456789B01';
        $client->classification = 'government';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('NL:OINO', $storecove->router->resolveRouting('NL', 'government'));
    }

    public function testNlBusinessClientTaxIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 528;
        $client->vat_number = 'NL123456789B01';
        $client->classification = 'business';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('NL:VAT', $storecove->router->resolveTaxScheme('NL', 'business'));
    }

    public function testNlGovClientTaxIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 528;
        $client->vat_number = 'NL123456789B01';
        $client->classification = 'government';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals(false, $storecove->router->resolveTaxScheme('NL', 'government'));
    }

    // Sweden Tests
    public function testSeBusinessClientRoutingIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 752;
        $client->vat_number = 'SE123456789101';
        $client->classification = 'business';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('SE:ORGNR', $storecove->router->resolveRouting('SE', 'business'));
    }

    public function testSeGovClientRoutingIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 752;
        $client->vat_number = 'SE123456789101';
        $client->classification = 'government';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('SE:ORGNR', $storecove->router->resolveRouting('SE', 'government'));
    }

    public function testSeBusinessClientTaxIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 752;
        $client->vat_number = 'SE123456789101';
        $client->classification = 'business';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('SE:VAT', $storecove->router->resolveTaxScheme('SE', 'business'));
    }

    public function testSeGovClientTaxIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 752;
        $client->vat_number = 'SE123456789101';
        $client->classification = 'government';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('SE:VAT', $storecove->router->resolveTaxScheme('SE', 'government'));
    }

    public function testSeBusinessClientUsesIdNumberForOrgnrRouting()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 752;
        $client->vat_number = 'SE123456789101';
        $client->id_number = '5567891234';
        $client->classification = 'business';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        // Routing scheme should be SE:ORGNR
        $this->assertEquals('SE:ORGNR', $storecove->router->resolveRouting('SE', 'business'));

        // The Mutator should use id_number (org number) as the routing identifier value, not vat_number
        $storecove->mutator->setInvoice($invoice->fresh());
        $storecove->mutator->setClientRoutingCode();

        $meta = $storecove->mutator->getStorecoveMeta();

        $this->assertArrayHasKey('routing', $meta);
        $this->assertArrayHasKey('eIdentifiers', $meta['routing']);

        $eIdentifiers = $meta['routing']['eIdentifiers'];

        // Find the SE:ORGNR identifier
        $orgnrIdentifier = collect($eIdentifiers)->firstWhere('scheme', 'SE:ORGNR');

        $this->assertNotNull($orgnrIdentifier, 'SE:ORGNR routing identifier should be present');
        $this->assertEquals('5567891234', $orgnrIdentifier['id'], 'SE:ORGNR should use the client id_number (org number)');
    }

    public function testSeReceiverSetsSvefakturaNetwork()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 752;
        $client->vat_number = 'SE123456789101';
        $client->id_number = '5567891234';
        $client->classification = 'business';
        $client->save();

        $storecove = new Storecove();
        $storecove->mutator->setInvoice($invoice->fresh());
        $storecove->mutator->setClientRoutingCode();

        $meta = $storecove->mutator->getStorecoveMeta();

        $this->assertArrayHasKey('routing', $meta);
        $this->assertArrayHasKey('networks', $meta['routing']);

        $networks = $meta['routing']['networks'];
        $svefaktura = collect($networks)->firstWhere('application', 'svefaktura');

        $this->assertNotNull($svefaktura, 'Svefaktura network should be present when sending to SE receiver');
        $this->assertTrue($svefaktura['settings']['enabled']);
    }

    // Iceland Tests
    public function testIsBusinessClientRoutingIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 352;
        $client->vat_number = 'IS123456';
        $client->classification = 'business';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('IS:KTNR', $storecove->router->resolveRouting('IS', 'business'));
    }

    public function testIsGovClientRoutingIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 352;
        $client->vat_number = 'IS123456';
        $client->classification = 'government';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('IS:KTNR', $storecove->router->resolveRouting('IS', 'government'));
    }

    public function testIsBusinessClientTaxIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 352;
        $client->vat_number = 'IS123456';
        $client->classification = 'business';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('IS:VAT', $storecove->router->resolveTaxScheme('IS', 'business'));
    }

    public function testIsGovClientTaxIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 352;
        $client->vat_number = 'IS123456';
        $client->classification = 'government';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('IS:VAT', $storecove->router->resolveTaxScheme('IS', 'government'));
    }

    // Ireland Tests
    public function testIeBusinessClientRoutingIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 372;
        $client->vat_number = 'IE1234567T';
        $client->classification = 'business';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('IE:VAT', $storecove->router->resolveRouting('IE', 'business'));
    }

    public function testIeGovClientRoutingIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 372;
        $client->vat_number = 'IE1234567T';
        $client->classification = 'government';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('IE:VAT', $storecove->router->resolveRouting('IE', 'government'));
    }

    public function testIeBusinessClientTaxIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 372;
        $client->vat_number = 'IE1234567T';
        $client->classification = 'business';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('IE:VAT', $storecove->router->resolveTaxScheme('IE', 'business'));
    }

    public function testIeGovClientTaxIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 372;
        $client->vat_number = 'IE1234567T';
        $client->classification = 'government';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('IE:VAT', $storecove->router->resolveTaxScheme('IE', 'government'));
    }


    // Denmark Tests
    public function testDkBusinessClientRoutingIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 208;
        $client->vat_number = 'DK12345678';
        $client->classification = 'business';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('DK:DIGST', $storecove->router->resolveRouting('DK', 'business'));
    }

    public function testDkGovClientRoutingIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 208;
        $client->vat_number = 'DK12345678';
        $client->classification = 'government';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('DK:DIGST', $storecove->router->resolveRouting('DK', 'government'));
    }

    public function testDkBusinessClientTaxIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 208;
        $client->vat_number = 'DK12345678';
        $client->classification = 'business';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('DK:ERST', $storecove->router->resolveTaxScheme('DK', 'business'));
    }

    public function testDkGovClientTaxIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 208;
        $client->vat_number = 'DK12345678';
        $client->classification = 'government';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('DK:ERST', $storecove->router->resolveTaxScheme('DK', 'government'));
    }

    // UK/England Tests
    public function testGbBusinessClientRoutingIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 826;
        $client->vat_number = 'GB123456789';
        $client->classification = 'business';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('GB:VAT', $storecove->router->resolveRouting('GB', 'business'));
    }

    public function testGbGovClientRoutingIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 826;
        $client->vat_number = 'GB123456789';
        $client->classification = 'government';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('GB:VAT', $storecove->router->resolveRouting('GB', 'government'));
    }

    public function testGbBusinessClientTaxIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 826;
        $client->vat_number = 'GB123456789';
        $client->classification = 'business';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('GB:VAT', $storecove->router->resolveTaxScheme('GB', 'business'));
    }

    public function testGbGovClientTaxIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 826;
        $client->vat_number = 'GB123456789';
        $client->classification = 'government';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('GB:VAT', $storecove->router->resolveTaxScheme('GB', 'government'));
    }

    public function testBeBusinessClientRoutingIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 56; // Belgium
        $client->vat_number = 'BE0123456789';
        $client->classification = 'business';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('BE:EN', $storecove->router->resolveRouting('BE', 'business'));
    }

    public function testBeGovClientRoutingIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 56;
        $client->vat_number = 'BE0123456789';
        $client->classification = 'government';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('BE:EN', $storecove->router->resolveRouting('BE', 'government'));
    }

    public function testBeBusinessClientTaxIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 56;
        $client->vat_number = 'BE0123456789';
        $client->classification = 'business';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('BE:VAT', $storecove->router->resolveTaxScheme('BE', 'business'));
    }

    public function testBeGovClientTaxIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 56;
        $client->vat_number = 'BE0123456789';
        $client->classification = 'government';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('BE:VAT', $storecove->router->resolveTaxScheme('BE', 'government'));
    }


    public function testAtBusinessClientRoutingIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 40;
        $client->vat_number = 'ATU123456789';
        $client->classification = 'business';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('AT:VAT', $storecove->router->resolveRouting('AT', 'business'));

    }

    public function testAtGovClientRoutingIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 40;
        $client->vat_number = 'ATU123456789';
        $client->classification = 'government';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals("9915:b", $storecove->router->resolveRouting('AT', 'government'));

    }

    public function testAtBusinessClientTaxIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 40;
        $client->vat_number = 'ATU123456789';
        $client->classification = 'business';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('AT:VAT', $storecove->router->resolveTaxScheme('AT', 'business'));

    }

    public function testAtGovClientTaxIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 40;
        $client->vat_number = 'ATU123456789';
        $client->classification = 'government';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals(false, $storecove->router->resolveTaxScheme('AT', 'government'));

    }

    public function testDeSteurNummerRegistration()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 276;
        // $client->vat_number = 'DE123456789';
        $client->id_number = '12/345/67890';
        $client->classification = 'individual';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('DE:STNR', $storecove->router->resolveRouting('DE', 'individual'));

    }

    public function testDeBusinessClientRoutingIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 276;
        $client->vat_number = 'DE123456789';
        $client->classification = 'business';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('DE:VAT', $storecove->router->resolveRouting('DE', 'business'));

    }

    public function testDeGovClientRoutingIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 276;
        $client->vat_number = 'DE123456789';
        $client->classification = 'government';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals("DE:LWID", $storecove->router->resolveRouting('DE', 'government'));

    }

    public function testDeBusinessClientTaxIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 276;
        $client->vat_number = 'DE123456789';
        $client->classification = 'business';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('DE:VAT', $storecove->router->resolveTaxScheme('DE', 'business'));

    }

    public function testDeGovClientTaxIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 276;
        $client->vat_number = 'DE123456789';
        $client->classification = 'government';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals(false, $storecove->router->resolveTaxScheme('DE', 'government'));

    }

    // resolveRequiredClientFields() tests

    public function testResolveRequiredFieldsSeBusinessNeedsBoth()
    {
        $storecove = new Storecove();
        $required = $storecove->router->resolveRequiredClientFields('SE', 'business');

        $this->assertArrayHasKey('vat_number', $required);
        $this->assertArrayHasKey('id_number', $required);
        $this->assertEquals('SE:VAT', $required['vat_number']);
        $this->assertEquals('SE:ORGNR', $required['id_number']);
    }

    public function testResolveRequiredFieldsNoBusinessNeedsBoth()
    {
        $storecove = new Storecove();
        $required = $storecove->router->resolveRequiredClientFields('NO', 'business');

        $this->assertArrayHasKey('vat_number', $required);
        $this->assertArrayHasKey('id_number', $required);
        $this->assertEquals('NO:VAT', $required['vat_number']);
        $this->assertEquals('NO:ORG', $required['id_number']);
    }

    public function testResolveRequiredFieldsBeBusinessNeedsBoth()
    {
        $storecove = new Storecove();
        $required = $storecove->router->resolveRequiredClientFields('BE', 'business');

        $this->assertArrayHasKey('vat_number', $required);
        $this->assertArrayHasKey('id_number', $required);
        $this->assertEquals('BE:VAT', $required['vat_number']);
        $this->assertEquals('BE:EN', $required['id_number']);
    }

    public function testResolveRequiredFieldsDeBusinessNeedsVatOnly()
    {
        $storecove = new Storecove();
        $required = $storecove->router->resolveRequiredClientFields('DE', 'business');

        $this->assertArrayHasKey('vat_number', $required);
        $this->assertArrayNotHasKey('id_number', $required);
        $this->assertEquals('DE:VAT', $required['vat_number']);
    }

    public function testResolveRequiredFieldsDeGovNeedsIdOnly()
    {
        $storecove = new Storecove();
        $required = $storecove->router->resolveRequiredClientFields('DE', 'government');

        $this->assertArrayNotHasKey('vat_number', $required);
        $this->assertArrayHasKey('id_number', $required);
        $this->assertEquals('DE:LWID', $required['id_number']);
    }

    public function testResolveRequiredFieldsCaBusinessNeedsIdOnly()
    {
        $storecove = new Storecove();
        $required = $storecove->router->resolveRequiredClientFields('CA', 'business');

        $this->assertArrayNotHasKey('vat_number', $required);
        $this->assertArrayHasKey('id_number', $required);
        $this->assertEquals('CA:CBN', $required['id_number']);
    }

    public function testResolveRequiredFieldsAtBusinessNeedsVatOnly()
    {
        $storecove = new Storecove();
        $required = $storecove->router->resolveRequiredClientFields('AT', 'business');

        $this->assertArrayHasKey('vat_number', $required);
        $this->assertArrayNotHasKey('id_number', $required);
        $this->assertEquals('AT:VAT', $required['vat_number']);
    }

    public function testResolveRequiredFieldsAtGovNeedsIdOnly()
    {
        $storecove = new Storecove();
        $required = $storecove->router->resolveRequiredClientFields('AT', 'government');

        $this->assertArrayNotHasKey('vat_number', $required);
        $this->assertArrayHasKey('id_number', $required);
        $this->assertEquals('AT:GOV', $required['id_number']);
    }

    public function testResolveRequiredFieldsFrBusinessNeedsBoth()
    {
        $storecove = new Storecove();
        $required = $storecove->router->resolveRequiredClientFields('FR', 'business');

        $this->assertArrayHasKey('vat_number', $required);
        $this->assertArrayHasKey('id_number', $required);
        $this->assertEquals('FR:VAT', $required['vat_number']);
        $this->assertEquals('FR:SIRENE or FR:SIRET', $required['id_number']);
    }

    public function testResolveRequiredFieldsItBusinessNeedsVatAndRouting()
    {
        $storecove = new Storecove();
        $required = $storecove->router->resolveRequiredClientFields('IT', 'business');

        $this->assertArrayHasKey('vat_number', $required);
        $this->assertArrayHasKey('routing_id', $required);
        $this->assertEquals('IT:IVA', $required['vat_number']);
        $this->assertEquals('IT:CUUO', $required['routing_id']);
    }

    public function testResolveRequiredFieldsIndividualReturnsEmpty()
    {
        $storecove = new Storecove();

        $this->assertEmpty($storecove->router->resolveRequiredClientFields('DE', 'individual'));
        $this->assertEmpty($storecove->router->resolveRequiredClientFields('SE', 'individual'));
        $this->assertEmpty($storecove->router->resolveRequiredClientFields('FR', 'individual'));
    }

    public function testResolveRequiredFieldsUnknownCountryReturnsEmpty()
    {
        $storecove = new Storecove();
        $this->assertEmpty($storecove->router->resolveRequiredClientFields('ZZ', 'business'));
    }

    // Format validation tests

    public function testValidateIdentifierFormatSeVat()
    {
        $storecove = new Storecove();
        $this->assertTrue($storecove->router->validateIdentifierFormat('SE:VAT', 'SE123456789012'));
        $this->assertTrue($storecove->router->validateIdentifierFormat('SE:VAT', '123456789012'));
        $this->assertFalse($storecove->router->validateIdentifierFormat('SE:VAT', '12345'));
    }

    public function testValidateIdentifierFormatSeOrgnr()
    {
        $storecove = new Storecove();
        $this->assertTrue($storecove->router->validateIdentifierFormat('SE:ORGNR', '5567891234'));
        $this->assertFalse($storecove->router->validateIdentifierFormat('SE:ORGNR', '556789'));
    }

    public function testValidateIdentifierFormatFrSireneOrSiret()
    {
        $storecove = new Storecove();
        // SIRENE (9 digits) or SIRET (14 digits)
        $this->assertTrue($storecove->router->validateIdentifierFormat('FR:SIRENE or FR:SIRET', '123456789'));
        $this->assertTrue($storecove->router->validateIdentifierFormat('FR:SIRENE or FR:SIRET', '12345678901234'));
        $this->assertFalse($storecove->router->validateIdentifierFormat('FR:SIRENE or FR:SIRET', '12345'));
    }

    public function testValidateIdentifierFormatDeVat()
    {
        $storecove = new Storecove();
        $this->assertTrue($storecove->router->validateIdentifierFormat('DE:VAT', 'DE123456789'));
        $this->assertTrue($storecove->router->validateIdentifierFormat('DE:VAT', '123456789'));
        $this->assertFalse($storecove->router->validateIdentifierFormat('DE:VAT', 'DE12345'));
    }

    public function testValidateIdentifierFormatDkBothFields()
    {
        $storecove = new Storecove();
        // DK:ERST (VAT) - 8 digits
        $this->assertTrue($storecove->router->validateIdentifierFormat('DK:ERST', 'DK12345678'));
        // DK:DIGST (id_number) - 8-10 digits
        $this->assertTrue($storecove->router->validateIdentifierFormat('DK:DIGST', '12345678'));
    }

    public function testValidateIdentifierFormatItCuuo()
    {
        $storecove = new Storecove();
        $this->assertTrue($storecove->router->validateIdentifierFormat('IT:CUUO', 'ABC1234'));
        $this->assertTrue($storecove->router->validateIdentifierFormat('IT:CUUO', 'ABCDEF'));
        $this->assertFalse($storecove->router->validateIdentifierFormat('IT:CUUO', 'AB'));
    }

    public function testResolveRequiredFieldsNlBusinessNeedsBoth()
    {
        $storecove = new Storecove();
        $required = $storecove->router->resolveRequiredClientFields('NL', 'business');

        $this->assertArrayHasKey('vat_number', $required);
        $this->assertArrayHasKey('id_number', $required);
        $this->assertEquals('NL:VAT', $required['vat_number']);
        $this->assertEquals('NL:KVK', $required['id_number']);
    }

    public function testResolveRequiredFieldsNlGovNeedsIdOnly()
    {
        $storecove = new Storecove();
        $required = $storecove->router->resolveRequiredClientFields('NL', 'government');

        $this->assertArrayNotHasKey('vat_number', $required);
        $this->assertArrayHasKey('id_number', $required);
        $this->assertEquals('NL:OINO', $required['id_number']);
    }

    public function testResolveRequiredFieldsChBusinessNeedsBoth()
    {
        $storecove = new Storecove();
        $required = $storecove->router->resolveRequiredClientFields('CH', 'business');

        $this->assertArrayHasKey('vat_number', $required);
        $this->assertArrayHasKey('id_number', $required);
        $this->assertEquals('CH:VAT', $required['vat_number']);
        $this->assertEquals('CH:UIDB', $required['id_number']);
    }

    public function testResolveRequiredFieldsGbBusinessNeedsVatOnly()
    {
        $storecove = new Storecove();
        $required = $storecove->router->resolveRequiredClientFields('GB', 'business');

        $this->assertArrayHasKey('vat_number', $required);
        $this->assertArrayNotHasKey('id_number', $required);
        $this->assertEquals('GB:VAT', $required['vat_number']);
    }

    public function testResolveRequiredFieldsAuBusinessNeedsBoth()
    {
        $storecove = new Storecove();
        $required = $storecove->router->resolveRequiredClientFields('AU', 'business');

        $this->assertArrayHasKey('vat_number', $required);
        $this->assertArrayHasKey('id_number', $required);
        $this->assertEquals('AU:ABN', $required['vat_number']);
        $this->assertEquals('AU:ABN', $required['id_number']);
    }

    // =====================================================================
    // Comprehensive country coverage: resolveRequiredClientFields()
    // =====================================================================

    public function testResolveRequiredFieldsForCountry(
        string $country,
        string $classification,
        bool $expectVat,
        bool $expectId,
        bool $expectRouting,
        ?string $vatScheme,
        ?string $idScheme,
        ?string $routingScheme
    ) {
        $storecove = new Storecove();
        $required = $storecove->router->resolveRequiredClientFields($country, $classification);

        if ($expectVat) {
            $this->assertArrayHasKey('vat_number', $required, "{$country}/{$classification} should require vat_number");
            if ($vatScheme) {
                $this->assertEquals($vatScheme, $required['vat_number'], "{$country}/{$classification} vat_number scheme mismatch");
            }
        } else {
            $this->assertArrayNotHasKey('vat_number', $required, "{$country}/{$classification} should NOT require vat_number");
        }

        if ($expectId) {
            $this->assertArrayHasKey('id_number', $required, "{$country}/{$classification} should require id_number");
            if ($idScheme) {
                $this->assertEquals($idScheme, $required['id_number'], "{$country}/{$classification} id_number scheme mismatch");
            }
        } else {
            $this->assertArrayNotHasKey('id_number', $required, "{$country}/{$classification} should NOT require id_number");
        }

        if ($expectRouting) {
            $this->assertArrayHasKey('routing_id', $required, "{$country}/{$classification} should require routing_id");
            if ($routingScheme) {
                $this->assertEquals($routingScheme, $required['routing_id'], "{$country}/{$classification} routing_id scheme mismatch");
            }
        } else {
            $this->assertArrayNotHasKey('routing_id', $required, "{$country}/{$classification} should NOT require routing_id");
        }
    }

    public static function requiredFieldsProvider(): array
    {
        return [
            // Countries requiring BOTH vat_number + id_number
            //                    country, class,       vat,   id,    rte,   vatScheme,  idScheme,             rteScheme
            'SE business'     => ['SE',    'business',  true,  true,  false, 'SE:VAT',   'SE:ORGNR',           null],
            'SE government'   => ['SE',    'government',true,  true,  false, 'SE:VAT',   'SE:ORGNR',           null],
            'NO business'     => ['NO',    'business',  true,  true,  false, 'NO:VAT',   'NO:ORG',             null],
            'NO government'   => ['NO',    'government',true,  true,  false, 'NO:VAT',   'NO:ORG',             null],
            'BE business'     => ['BE',    'business',  true,  true,  false, 'BE:VAT',   'BE:EN',              null],
            'BE government'   => ['BE',    'government',true,  true,  false, 'BE:VAT',   'BE:EN',              null],
            'CH business'     => ['CH',    'business',  true,  true,  false, 'CH:VAT',   'CH:UIDB',            null],
            'CH government'   => ['CH',    'government',true,  true,  false, 'CH:VAT',   'CH:UIDB',            null],
            'IS business'     => ['IS',    'business',  true,  true,  false, 'IS:VAT',   'IS:KTNR',            null],
            'DK business'     => ['DK',    'business',  true,  true,  false, 'DK:ERST',  'DK:DIGST',           null],
            'DK government'   => ['DK',    'government',true,  true,  false, 'DK:ERST',  'DK:DIGST',           null],
            'EE business'     => ['EE',    'business',  true,  true,  false, 'EE:VAT',   'EE:CC',              null],
            'EE government'   => ['EE',    'government',true,  true,  false, 'EE:VAT',   'EE:CC',              null],
            'FI business'     => ['FI',    'business',  true,  true,  false, 'FI:VAT',   'FI:OVT',             null],
            'FI government'   => ['FI',    'government',true,  true,  false, 'FI:VAT',   'FI:OVT',             null],
            'LT business'     => ['LT',    'business',  true,  true,  false, 'LT:VAT',   'LT:LEC',             null],
            'LT government'   => ['LT',    'government',true,  true,  false, 'LT:VAT',   'LT:LEC',             null],
            'LU business'     => ['LU',    'business',  true,  true,  false, 'LU:VAT',   'LU:MAT',             null],
            'LU government'   => ['LU',    'government',true,  true,  false, 'LU:VAT',   'LU:MAT',             null],
            'NL business'     => ['NL',    'business',  true,  true,  false, 'NL:VAT',   'NL:KVK',             null],
            'AU business'     => ['AU',    'business',  true,  true,  false, 'AU:ABN',   'AU:ABN',             null],
            'AU government'   => ['AU',    'government',true,  true,  false, 'AU:ABN',   'AU:ABN',             null],
            'NZ business'     => ['NZ',    'business',  true,  true,  false, 'NZ:GST',   'GLN',                null],
            'JP business'     => ['JP',    'business',  true,  true,  false, 'JP:IIN',   'JP:SST',             null],
            'MY business'     => ['MY',    'business',  true,  true,  false, 'MY:TIN',   'MY:EIF',             null],
            'FR business'     => ['FR',    'business',  true,  true,  false, 'FR:VAT',   'FR:SIRENE or FR:SIRET', null],
            'SG business'     => ['SG',    'business',  true,  true,  false, 'SG:GST',   'SG:UEN',             null],

            // Countries requiring ONLY vat_number
            'DE business'     => ['DE',    'business',  true,  false, false, 'DE:VAT',   null,                 null],
            'AT business'     => ['AT',    'business',  true,  false, false, 'AT:VAT',   null,                 null],
            'ES business'     => ['ES',    'business',  true,  false, false, 'ES:VAT',   null,                 null],
            'LI business'     => ['LI',    'business',  true,  false, false, 'LI:VAT',   null,                 null],
            'AD business'     => ['AD',    'business',  true,  false, false, 'AD:VAT',   null,                 null],
            'AL business'     => ['AL',    'business',  true,  false, false, 'AL:VAT',   null,                 null],
            'BA business'     => ['BA',    'business',  true,  false, false, 'BA:VAT',   null,                 null],
            'BG business'     => ['BG',    'business',  true,  false, false, 'BG:VAT',   null,                 null],
            'CY business'     => ['CY',    'business',  true,  false, false, 'CY:VAT',   null,                 null],
            'CZ business'     => ['CZ',    'business',  true,  false, false, 'CZ:VAT',   null,                 null],
            'GR business'     => ['GR',    'business',  true,  false, false, 'GR:VAT',   null,                 null],
            'HR business'     => ['HR',    'business',  true,  false, false, 'HR:VAT',   null,                 null],
            'HU business'     => ['HU',    'business',  true,  false, false, 'HU:VAT',   null,                 null],
            'IE business'     => ['IE',    'business',  true,  false, false, 'IE:VAT',   null,                 null],
            'LV business'     => ['LV',    'business',  true,  false, false, 'LV:VAT',   null,                 null],
            'MC business'     => ['MC',    'business',  true,  false, false, 'MC:VAT',   null,                 null],
            'ME business'     => ['ME',    'business',  true,  false, false, 'ME:VAT',   null,                 null],
            'MK business'     => ['MK',    'business',  true,  false, false, 'MK:VAT',   null,                 null],
            'MT business'     => ['MT',    'business',  true,  false, false, 'MT:VAT',   null,                 null],
            'PL business'     => ['PL',    'business',  true,  false, false, 'PL:VAT',   null,                 null],
            'PT business'     => ['PT',    'business',  true,  false, false, 'PT:VAT',   null,                 null],
            'RO business'     => ['RO',    'business',  true,  false, false, 'RO:VAT',   null,                 null],
            'RS business'     => ['RS',    'business',  true,  false, false, 'RS:VAT',   null,                 null],
            'SI business'     => ['SI',    'business',  true,  false, false, 'SI:VAT',   null,                 null],
            'SK business'     => ['SK',    'business',  true,  false, false, 'SK:VAT',   null,                 null],
            'SM business'     => ['SM',    'business',  true,  false, false, 'SM:VAT',   null,                 null],
            'TR business'     => ['TR',    'business',  true,  false, false, 'TR:VAT',   null,                 null],
            'VA business'     => ['VA',    'business',  true,  false, false, 'VA:VAT',   null,                 null],
            'GB business'     => ['GB',    'business',  true,  false, false, 'GB:VAT',   null,                 null],
            'IN business'     => ['IN',    'business',  true,  false, false, 'IN:GSTIN', null,                 null],
            'SA business'     => ['SA',    'business',  true,  false, false, 'SA:TIN',   null,                 null],

            // Countries requiring ONLY id_number
            'CA business'     => ['CA',    'business',  false, true,  false, null,        'CA:CBN',             null],
            'MX business'     => ['MX',    'business',  false, true,  false, null,        'MX:RFC',             null],
            'DE government'   => ['DE',    'government',false, true,  false, null,        'DE:LWID',            null],
            'AT government'   => ['AT',    'government',false, true,  false, null,        'AT:GOV',             null],
            'NL government'   => ['NL',    'government',false, true,  false, null,        'NL:OINO',            null],
            'SG government'   => ['SG',    'government',false, true,  false, null,        'SG:UEN',             null],

            // FR government needs id_number only (SIRET + customerAssignedAccountIdValue)
            'FR government'   => ['FR',    'government',false, true,  false, null,        'FR:SIRET + customerAssignedAccountIdValue', null],

            // IT requires vat_number + routing_id
            'IT business'     => ['IT',    'business',  true,  false, true,  'IT:IVA',   null,                 'IT:CUUO'],
            'IT government'   => ['IT',    'government',true,  false, true,  'IT:IVA',   null,                 'IT:CUUO'],

            // US requires vat_number (EIN) + id_number (DUNS/GLN/LEI)
            'US business'     => ['US',    'business',  true,  true,  false, 'US:EIN',   'DUNS, GLN, LEI',     null],

            // Individuals always return empty
            'DE individual'   => ['DE',    'individual',false, false, false, null,        null,                 null],
            'SE individual'   => ['SE',    'individual',false, false, false, null,        null,                 null],
            'IT individual'   => ['IT',    'individual',false, false, false, null,        null,                 null],
            'FR individual'   => ['FR',    'individual',false, false, false, null,        null,                 null],
            'US individual'   => ['US',    'individual',false, false, false, null,        null,                 null],
            'NL individual'   => ['NL',    'individual',false, false, false, null,        null,                 null],

            // Unknown country
            'ZZ business'     => ['ZZ',    'business',  false, false, false, null,        null,                 null],
        ];
    }

    // =====================================================================
    // Comprehensive format validation tests
    // =====================================================================

    public function testValidIdentifierFormats(string $scheme, string $value)
    {
        $storecove = new Storecove();
        $this->assertTrue(
            $storecove->router->validateIdentifierFormat($scheme, $value),
            "'{$value}' should be valid for scheme '{$scheme}'"
        );
    }

    public static function validFormatProvider(): array
    {
        return [
            // EU VAT numbers
            'AT:VAT valid'          => ['AT:VAT', 'ATU12345678'],
            'AT:VAT no prefix'      => ['AT:VAT', 'U12345678'],
            'BE:VAT valid'          => ['BE:VAT', 'BE0123456789'],
            'BE:VAT no prefix'      => ['BE:VAT', '0123456789'],
            'BG:VAT 9 digits'       => ['BG:VAT', 'BG123456789'],
            'BG:VAT 10 digits'      => ['BG:VAT', '1234567890'],
            'CY:VAT valid'          => ['CY:VAT', 'CY12345678A'],
            'CZ:VAT 8 digits'       => ['CZ:VAT', 'CZ12345678'],
            'CZ:VAT 10 digits'      => ['CZ:VAT', '1234567890'],
            'DE:VAT valid'          => ['DE:VAT', 'DE123456789'],
            'DE:VAT no prefix'      => ['DE:VAT', '123456789'],
            'DK:ERST valid'         => ['DK:ERST', 'DK12345678'],
            'EE:VAT valid'          => ['EE:VAT', 'EE123456789'],
            'ES:VAT valid'          => ['ES:VAT', 'ESA1234567B'],
            'FI:VAT valid'          => ['FI:VAT', 'FI12345678'],
            'FR:VAT valid'          => ['FR:VAT', 'FRAA123456789'],
            'FR:VAT numeric'        => ['FR:VAT', 'FR12345678901'],
            'GR:VAT valid'          => ['GR:VAT', 'GR123456789'],
            'GR:VAT EL prefix'      => ['GR:VAT', 'EL123456789'],
            'HR:VAT valid'          => ['HR:VAT', 'HR12345678901'],
            'HU:VAT valid'          => ['HU:VAT', 'HU12345678'],
            'IE:VAT valid'          => ['IE:VAT', 'IE1A23456AB'],
            'IT:IVA valid'          => ['IT:IVA', 'IT12345678901'],
            'IT:IVA no prefix'      => ['IT:IVA', '12345678901'],
            'LT:VAT 9 digits'       => ['LT:VAT', 'LT123456789'],
            'LT:VAT 12 digits'      => ['LT:VAT', 'LT123456789012'],
            'LU:VAT valid'          => ['LU:VAT', 'LU12345678'],
            'LV:VAT valid'          => ['LV:VAT', 'LV12345678901'],
            'MT:VAT valid'          => ['MT:VAT', 'MT12345678'],
            'NL:VAT valid'          => ['NL:VAT', 'NL123456789B01'],
            'PL:VAT valid'          => ['PL:VAT', 'PL1234567890'],
            'PT:VAT valid'          => ['PT:VAT', 'PT123456789'],
            'RO:VAT valid'          => ['RO:VAT', 'RO1234567890'],
            'RO:VAT short'          => ['RO:VAT', 'RO12'],
            'SE:VAT valid'          => ['SE:VAT', 'SE123456789012'],
            'SI:VAT valid'          => ['SI:VAT', 'SI12345678'],
            'SK:VAT valid'          => ['SK:VAT', 'SK1234567890'],

            // Non-EU VAT numbers
            'NO:VAT valid'          => ['NO:VAT', 'NO123456789MVA'],
            'NO:VAT no suffix'      => ['NO:VAT', '123456789'],
            'CH:VAT valid'          => ['CH:VAT', 'CHE123456789MWST'],
            'CH:VAT short'          => ['CH:VAT', 'CHE123456789'],
            'GB:VAT 9 digits'       => ['GB:VAT', 'GB123456789'],
            'GB:VAT 12 digits'      => ['GB:VAT', 'GB123456789012'],
            'AU:ABN valid'          => ['AU:ABN', '12345678901'],
            'NZ:GST 8 digits'       => ['NZ:GST', '12345678'],
            'NZ:GST 9 digits'       => ['NZ:GST', '123456789'],
            'US:EIN valid'          => ['US:EIN', '12-3456789'],
            'US:EIN no dash'        => ['US:EIN', '123456789'],
            'IN:GSTIN valid'        => ['IN:GSTIN', '22AAAAA0000A1Z5'],
            'JP:IIN valid'          => ['JP:IIN', 'T1234567890123'],
            'JP:IIN no T'           => ['JP:IIN', '1234567890123'],
            'SA:TIN valid'          => ['SA:TIN', '1234567890'],

            // ID number patterns
            'SE:ORGNR valid'        => ['SE:ORGNR', '5567891234'],
            'NO:ORG valid'          => ['NO:ORG', '123456789'],
            'BE:EN valid'           => ['BE:EN', '0123456789'],
            'BE:EN with prefix'     => ['BE:EN', 'BE0123456789'],
            'DK:DIGST valid'        => ['DK:DIGST', '12345678'],
            'EE:CC valid'           => ['EE:CC', '12345678'],
            'FI:OVT 12 digits'      => ['FI:OVT', '123456789012'],
            'FI:OVT 13 digits'      => ['FI:OVT', '1234567890123'],
            'FR:SIRENE valid'       => ['FR:SIRENE', '123456789'],
            'FR:SIRET valid'        => ['FR:SIRET', '12345678901234'],
            'NL:KVK valid'          => ['NL:KVK', '12345678'],
            'NL:OINO valid'         => ['NL:OINO', '12345678901234567890'],
            'LT:LEC valid'          => ['LT:LEC', '123456789'],
            'LU:MAT valid'          => ['LU:MAT', '12345678901'],
            'CH:UIDB valid'         => ['CH:UIDB', 'CHE123456789'],
            'IS:KTNR valid'         => ['IS:KTNR', '1234567890'],
            'CA:CBN valid'          => ['CA:CBN', '123456789'],
            'JP:SST valid'          => ['JP:SST', 'T1234567890123'],
            'SG:UEN valid'          => ['SG:UEN', 'T08GA0028A'],
            'IT:CUUO valid'         => ['IT:CUUO', 'A1B2C3D'],
            'IT:CF valid'           => ['IT:CF', 'RSSMRA85M01H501Z'],

            // Composite schemes
            'FR:SIRENE or FR:SIRET (SIRENE)' => ['FR:SIRENE or FR:SIRET', '123456789'],
            'FR:SIRENE or FR:SIRET (SIRET)'  => ['FR:SIRENE or FR:SIRET', '12345678901234'],
            'DUNS, GLN, LEI'        => ['DUNS, GLN, LEI', '123456789'],

            // Values with separators stripped
            'DE:VAT with spaces'    => ['DE:VAT', 'DE 123 456 789'],
            'US:EIN with dash'      => ['US:EIN', '12-3456789'],
            'NO:ORG with dots'      => ['NO:ORG', '123.456.789'],
        ];
    }

    public function testInvalidIdentifierFormats(string $scheme, string $value)
    {
        $storecove = new Storecove();
        $this->assertFalse(
            $storecove->router->validateIdentifierFormat($scheme, $value),
            "'{$value}' should be INVALID for scheme '{$scheme}'"
        );
    }

    public static function invalidFormatProvider(): array
    {
        return [
            'AT:VAT too short'      => ['AT:VAT', 'ATU1234'],
            'AT:VAT too long'       => ['AT:VAT', 'ATU1234567890'],
            'DE:VAT too short'      => ['DE:VAT', 'DE12345'],
            'DE:VAT too long'       => ['DE:VAT', 'DE1234567890'],
            'SE:VAT too short'      => ['SE:VAT', 'SE12345'],
            'SE:ORGNR too short'    => ['SE:ORGNR', '556789'],
            'SE:ORGNR too long'     => ['SE:ORGNR', '55678912345'],
            'NO:ORG too short'      => ['NO:ORG', '12345'],
            'NL:KVK too short'      => ['NL:KVK', '1234567'],
            'NL:KVK too long'       => ['NL:KVK', '123456789'],
            'FR:SIRENE wrong len'   => ['FR:SIRENE', '12345'],
            'FR:SIRET wrong len'    => ['FR:SIRET', '123456789'],
            'CA:CBN too short'      => ['CA:CBN', '12345'],
            'IT:IVA too short'      => ['IT:IVA', '1234567'],
            'IT:CUUO too short'     => ['IT:CUUO', 'AB'],
            'FR:SIRENE or FR:SIRET wrong' => ['FR:SIRENE or FR:SIRET', '12345'],
            'BE:EN too short'       => ['BE:EN', '12345'],
            'EE:CC too short'       => ['EE:CC', '1234'],
            'AU:ABN too short'      => ['AU:ABN', '1234567'],
            'GB:VAT too short'      => ['GB:VAT', 'GB1234'],
        ];
    }

    // =====================================================================
    // Consistency: resolveRequiredClientFields matches resolveRouting/resolveTaxScheme
    // =====================================================================

    /**
     * For every country where resolveRouting returns a scheme,
     * resolveRequiredClientFields should return a non-empty array
     * (except for individuals).
     *
     */
    public function testRequiredFieldsNonEmptyForSupportedCountries(string $country)
    {
        $storecove = new Storecove();
        $required = $storecove->router->resolveRequiredClientFields($country, 'business');
        $this->assertNotEmpty($required, "{$country} business should have at least one required field");
    }

    public static function supportedCountryProvider(): array
    {
        $countries = [
            'US', 'CA', 'MX', 'AU', 'NZ', 'CH', 'IS', 'LI', 'NO',
            'AD', 'AL', 'AT', 'BA', 'BE', 'BG', 'CY', 'CZ', 'DE',
            'DK', 'EE', 'ES', 'FI', 'FR', 'GR', 'HR', 'HU', 'IE',
            'IT', 'LT', 'LU', 'LV', 'MC', 'ME', 'MK', 'MT', 'NL',
            'PL', 'PT', 'RO', 'RS', 'SE', 'SI', 'SK', 'SM', 'TR',
            'VA', 'IN', 'JP', 'MY', 'SG', 'GB', 'SA',
        ];

        $data = [];
        foreach ($countries as $c) {
            $data[$c] = [$c];
        }
        return $data;
    }

}
