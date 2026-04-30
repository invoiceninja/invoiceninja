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

    // Checkdigit validation tests

    public function testValidateBeEnCheckdigitValid()
    {
        $storecove = new Storecove();

        // Known valid Belgian enterprise numbers (mod-97 checkdigit)
        $this->assertTrue($storecove->router->validateIdentifierFormat('BE:EN', '0202239951')); // KBO/BCE
        $this->assertTrue($storecove->router->validateIdentifierFormat('BE:EN', '0404616494')); // BNP Paribas Fortis
        $this->assertTrue($storecove->router->validateIdentifierFormat('BE:EN', '0403199702')); // bpost
        $this->assertTrue($storecove->router->validateIdentifierFormat('BE:EN', '0471811661')); // ING Belgium

        // With optional BE prefix
        $this->assertTrue($storecove->router->validateIdentifierFormat('BE:EN', 'BE0202239951'));
    }

    public function testValidateBeEnCheckdigitInvalid()
    {
        $storecove = new Storecove();

        // Invalid checkdigit — the exact case from the Storecove error
        $this->assertFalse($storecove->router->validateIdentifierFormat('BE:EN', '0123456789'));

        // Valid format but wrong check digits
        $this->assertFalse($storecove->router->validateIdentifierFormat('BE:EN', '0202239952'));

        // With prefix, still invalid
        $this->assertFalse($storecove->router->validateIdentifierFormat('BE:EN', 'BE0123456789'));
    }

    public function testValidateBeVatCheckdigitValid()
    {
        $storecove = new Storecove();

        // Belgian VAT uses same mod-97 on the 10-digit portion
        $this->assertTrue($storecove->router->validateIdentifierFormat('BE:VAT', 'BE0202239951'));
        $this->assertTrue($storecove->router->validateIdentifierFormat('BE:VAT', 'BE0471811661'));
        $this->assertTrue($storecove->router->validateIdentifierFormat('BE:VAT', '0404616494'));
    }

    public function testValidateBeVatCheckdigitInvalid()
    {
        $storecove = new Storecove();

        $this->assertFalse($storecove->router->validateIdentifierFormat('BE:VAT', 'BE0123456789'));
        $this->assertFalse($storecove->router->validateIdentifierFormat('BE:VAT', '0123456789'));
    }

    // Ensure other schemes are not affected by checkdigit validation

    public function testValidateOtherSchemesUnaffected()
    {
        $storecove = new Storecove();

        // These should still pass — no checkdigit algorithm defined
        $this->assertTrue($storecove->router->validateIdentifierFormat('DE:VAT', 'DE123456789'));
        $this->assertTrue($storecove->router->validateIdentifierFormat('SE:VAT', 'SE123456789012'));
        $this->assertTrue($storecove->router->validateIdentifierFormat('SE:ORGNR', '5567891234'));
        $this->assertTrue($storecove->router->validateIdentifierFormat('IT:CUUO', 'ABC1234'));
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

    // BE-specific comprehensive tests

    public function testResolveRequiredFieldsBeGovNeedsBoth()
    {
        $storecove = new Storecove();
        $required = $storecove->router->resolveRequiredClientFields('BE', 'government');

        $this->assertArrayHasKey('vat_number', $required);
        $this->assertArrayHasKey('id_number', $required);
        $this->assertEquals('BE:VAT', $required['vat_number']);
        $this->assertEquals('BE:EN', $required['id_number']);
    }

    public function testResolveRequiredFieldsBeIndividualReturnsEmpty()
    {
        $storecove = new Storecove();
        $this->assertEmpty($storecove->router->resolveRequiredClientFields('BE', 'individual'));
    }

    public function testBeClassificationRoutability()
    {
        $storecove = new Storecove();

        $this->assertTrue($storecove->router->isClassificationRoutable('BE', 'business'));
        $this->assertTrue($storecove->router->isClassificationRoutable('BE', 'government'));
        $this->assertFalse($storecove->router->isClassificationRoutable('BE', 'individual'));
    }

    public function testBeIso6523SchemeMapping()
    {
        $storecove = new Storecove();

        $this->assertEquals('0208', $storecove->router->resolveIso6523Scheme('BE:EN'));
        $this->assertEquals('9925', $storecove->router->resolveIso6523Scheme('BE:VAT'));
    }

    public function testBeEnFormatValidationVariants()
    {
        $storecove = new Storecove();

        // Valid: 10 digits starting with 0 or 1, valid checkdigit
        $this->assertTrue($storecove->router->validateIdentifierFormat('BE:EN', '0202239951'));
        $this->assertTrue($storecove->router->validateIdentifierFormat('BE:EN', 'BE0202239951'));
        $this->assertTrue($storecove->router->validateIdentifierFormat('BE:EN', '0403199702'));
        $this->assertTrue($storecove->router->validateIdentifierFormat('BE:EN', '0471811661'));

        // Invalid: too short
        $this->assertFalse($storecove->router->validateIdentifierFormat('BE:EN', '02022'));

        // Invalid: too long
        $this->assertFalse($storecove->router->validateIdentifierFormat('BE:EN', '02022399510'));

        // Invalid: non-numeric
        $this->assertFalse($storecove->router->validateIdentifierFormat('BE:EN', 'ABCDEFGHIJ'));
    }

    public function testBeVatFormatValidationVariants()
    {
        $storecove = new Storecove();

        // Valid: BE prefix + 0/1 + 9 digits, valid checkdigit
        $this->assertTrue($storecove->router->validateIdentifierFormat('BE:VAT', 'BE0202239951'));
        $this->assertTrue($storecove->router->validateIdentifierFormat('BE:VAT', '0471811661'));
        $this->assertTrue($storecove->router->validateIdentifierFormat('BE:VAT', 'BE0404616494'));

        // Invalid: starts with 2 (not 0 or 1)
        $this->assertFalse($storecove->router->validateIdentifierFormat('BE:VAT', 'BE2123456789'));

        // Invalid: too short
        $this->assertFalse($storecove->router->validateIdentifierFormat('BE:VAT', 'BE012345'));
    }

    public function testBeCheckdigitDistinguishesFormatVsCheckdigitErrors()
    {
        $storecove = new Storecove();

        // Valid format, valid checkdigit → true
        $this->assertTrue($storecove->router->validateIdentifierFormat('BE:EN', '0202239951'));

        // Valid format, invalid checkdigit → false (from checkdigit, not format)
        $this->assertFalse($storecove->router->validateIdentifierFormat('BE:EN', '0202239952'));

        // Public checkdigit method: returns false for bad checkdigit
        $this->assertFalse($storecove->router->validateIdentifierCheckdigit('BE:EN', '0202239952'));

        // Public checkdigit method: returns true for valid
        $this->assertTrue($storecove->router->validateIdentifierCheckdigit('BE:EN', '0202239951'));

        // Public checkdigit method: returns null for schemes without checkdigit algo
        $this->assertNull($storecove->router->validateIdentifierCheckdigit('DE:VAT', 'DE123456789'));
    }

    public function testBeBusinessClientRoutingUsesIdNumber()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 56;
        $client->vat_number = 'BE0202239951';
        $client->id_number = '0202239951';
        $client->classification = 'business';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        // BE routing should be BE:EN
        $this->assertEquals('BE:EN', $storecove->router->resolveRouting('BE', 'business'));

        // Tax scheme should be BE:VAT
        $this->assertEquals('BE:VAT', $storecove->router->resolveTaxScheme('BE', 'business'));

        // ISO 6523 for routing (BE:EN) should be 0208
        $this->assertEquals('0208', $storecove->router->resolveIso6523Scheme('BE:EN'));
    }

    public function testBeGovClientRoutingUsesIdNumber()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 56;
        $client->vat_number = 'BE0404616494';
        $client->id_number = '0404616494';
        $client->classification = 'government';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        // BE government routing should also be BE:EN (B+G rule)
        $this->assertEquals('BE:EN', $storecove->router->resolveRouting('BE', 'government'));
        $this->assertEquals('BE:VAT', $storecove->router->resolveTaxScheme('BE', 'government'));
    }

    public function testBeGetFormatExamples()
    {
        $storecove = new Storecove();

        $this->assertEquals('0202239951', $storecove->router->getFormatExample('BE:EN'));
        $this->assertEquals('BE0202239951', $storecove->router->getFormatExample('BE:VAT'));
    }

    /**
     * Locks down every identifier_regex pattern so accidental changes
     * are caught immediately. If a regex genuinely needs updating,
     * update the expected value here — that forces a conscious decision.
     */
    public function testIdentifierRegexPatternsAreStable(): void
    {
        $router = (new Storecove())->router;

        $expected = [
            // VAT number patterns
            'AT:VAT'    => '/^(AT)?U\d{8}$/i',
            'BE:VAT'    => '/^(BE)?[01]\d{9}$/i',
            'BG:VAT'    => '/^(BG)?\d{9,10}$/i',
            'CY:VAT'    => '/^(CY)?\d{8}[A-Z]$/i',
            'CZ:VAT'    => '/^(CZ)?\d{8,10}$/i',
            'DE:VAT'    => '/^(DE)?\d{9}$/i',
            'DK:ERST'   => '/^(DK)?\d{8}$/i',
            'EE:VAT'    => '/^(EE)?\d{9}$/i',
            'ES:VAT'    => '/^(ES)?[A-Z0-9]\d{7}[A-Z0-9]$/i',
            'FI:VAT'    => '/^(FI)?\d{8}$/i',
            'FR:VAT'    => '/^(FR)?[A-HJ-NP-Z0-9]{2}\d{9}$/i',
            'GR:VAT'    => '/^(GR|EL)?\d{9}$/i',
            'HR:VAT'    => '/^(HR)?\d{11}$/i',
            'HU:VAT'    => '/^(HU)?\d{8}$/i',
            'IE:VAT'    => '/^(IE)?\d[A-Z0-9\+\*]\d{5}[A-Z]{1,2}$/i',
            'IT:IVA'    => '/^(IT)?\d{11}$/i',
            'IT:CF'     => '/^[A-Z]{6}\d{2}[A-Z]\d{2}[A-Z]\d{3}[A-Z]$/i',
            'LT:VAT'    => '/^(LT)?(\d{9}|\d{12})$/i',
            'LU:VAT'    => '/^(LU)?\d{8}$/i',
            'LV:VAT'    => '/^(LV)?\d{11}$/i',
            'MT:VAT'    => '/^(MT)?\d{8}$/i',
            'NL:VAT'    => '/^(NL)?\d{9}B\d{2}$/i',
            'PL:VAT'    => '/^(PL)?\d{10}$/i',
            'PT:VAT'    => '/^(PT)?\d{9}$/i',
            'RO:VAT'    => '/^(RO)?\d{2,10}$/i',
            'SE:VAT'    => '/^(SE)?\d{12}$/i',
            'SI:VAT'    => '/^(SI)?\d{8}$/i',
            'SK:VAT'    => '/^(SK)?\d{10}$/i',
            'AD:VAT'    => '/^(AD)?[A-Z]\d{6}[A-Z]$/i',
            'AL:VAT'    => '/^(AL)?[A-Z]\d{8}[A-Z]$/i',
            'BA:VAT'    => '/^(BA)?\d{12}$/i',
            'LI:VAT'    => '/^(LI)?\d{5}$/i',
            'MC:VAT'    => '/^(MC|FR)?[A-HJ-NP-Z0-9]{2}\d{9}$/i',
            'ME:VAT'    => '/^(ME)?\d{8}$/i',
            'MK:VAT'    => '/^(MK)?\d{13}$/i',
            'SM:VAT'    => '/^(SM)?\d{5}$/i',
            'TR:VAT'    => '/^(TR)?\d{10}$/i',
            'VA:VAT'    => '/^(VA)?\d{11}$/i',
            'RS:VAT'    => '/^(RS)?\d{9}$/i',
            'IS:VAT'    => '/^(IS)?\d{5,6}$/i',
            'NO:VAT'    => '/^(NO)?\d{9}(MVA)?$/i',
            'CH:VAT'    => '/^(CHE)?\d{9}(MWST|TVA|IVA)?$/i',
            'GB:VAT'    => '/^(GB)?\d{9}(\d{3})?$/i',
            'AU:ABN'    => '/^\d{11}$/',
            'NZ:GST'    => '/^\d{8,9}$/',
            'US:EIN'    => '/^\d{2}\-?\d{7}$/',
            'IN:GSTIN'  => '/^\d{2}[A-Z]{5}\d{4}[A-Z]\d[A-Z0-9][A-Z0-9]$/i',
            'JP:IIN'    => '/^T?\d{13}$/',
            'SG:GST'    => '/^[A-Z0-9]{2}-\d{7}-[A-Z0-9]$/i',
            'SA:TIN'    => '/^\d{10,15}$/',
            'MY:TIN'    => '/^[A-Z0-9]{10,14}$/i',

            // ID number patterns
            'SE:ORGNR'  => '/^\d{10}$/',
            'NO:ORG'    => '/^\d{9}$/',
            'BE:EN'     => '/^(BE)?[01]\d{9}$/i',
            'DK:DIGST'  => '/^(DK)?\d{8}$/i',
            'EE:CC'     => '/^\d{8}$/',
            'FI:OVT'    => '/^\d{12,13}$/',
            'FR:SIRENE' => '/^\d{9}$/',
            'FR:SIRET'  => '/^\d{14}$/',
            'NL:KVK'    => '/^\d{8}$/',
            'NL:OINO'   => '/^\d{20}$/',
            'LT:LEC'    => '/^\d{7,9}$/',
            'LU:MAT'    => '/^\d{11}$/',
            'CH:UIDB'   => '/^(CHE)?\d{9}$/i',
            'IS:KTNR'   => '/^\d{6,10}$/',
            'CA:CBN'    => '/^\d{9}$/',
            'MX:RFC'    => '/^[A-Z&Ñ]{3,4}\d{6}[A-Z0-9]{3}$/i',
            'JP:SST'    => '/^T?\d{13}$/',
            'MY:EIF'    => '/^[A-Z0-9]{10,14}$/i',
            'SG:UEN'    => '/^[A-Z0-9]{9,16}$/i',
            'AT:GOV'    => '/^.{2,}$/',
            'DE:LWID'   => '/^.{2,}$/',
            'IT:CUUO'   => '/^[A-Z0-9]{6,7}$/i',
        ];

        $reflection = new \ReflectionClass($router);
        $property = $reflection->getProperty('identifier_regex');
        $property->setAccessible(true);
        $regexMap = $property->getValue($router);

        foreach ($expected as $scheme => $regex) {
            $this->assertArrayHasKey($scheme, $regexMap, "Scheme {$scheme} missing from identifier_regex");
            $this->assertEquals($regex, $regexMap[$scheme], "Regex for {$scheme} has been changed");
        }

        // Ensure no new schemes were added without updating this test
        $this->assertCount(count($expected), $regexMap, 'identifier_regex has schemes not covered by this test — add them to $expected');
    }

}
