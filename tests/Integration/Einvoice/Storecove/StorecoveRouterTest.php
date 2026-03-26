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

use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testResolveRequiredFieldsCaBusinessNeedsCbn()
    {
        $storecove = new Storecove();
        $required = $storecove->router->resolveRequiredClientFields('CA', 'business');

        $this->assertArrayHasKey('vat_number', $required);
        $this->assertEquals('CA:CBN', $required['vat_number']);
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
        $this->assertTrue($storecove->router->validateIdentifierFormat('DK:ERST', 'DK12345678'));
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

}
