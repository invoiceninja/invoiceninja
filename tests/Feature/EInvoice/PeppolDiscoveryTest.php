<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Feature\EInvoice;

use Tests\TestCase;
use App\DataMapper\Tax\TaxModel;
use App\Models\Client;
use App\Models\Company;
use App\Models\Country;
use Tests\MockAccountData;
use App\Models\ClientContact;
use App\Services\EDocument\Gateway\Storecove\Storecove;
use App\Services\EDocument\Gateway\Storecove\StorecoveProxy;
use App\Services\EDocument\Gateway\Storecove\StorecoveRouter;
use App\Services\EDocument\Gateway\Storecove\RoutingResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Tests that RoutingResolver correctly resolves PEPPOL routing
 * (scheme + identifier) for recipients based on country, classification,
 * and available identifiers (vat_number, id_number, routing_id).
 */
class PeppolDiscoveryTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTestData();
    }

    private function countryId(string $countryCode): int
    {
        return (int) Country::where('iso_3166_2', $countryCode)->firstOrFail()->id;
    }

    private function setCompanyCountry(string $countryCode): void
    {
        $settings = $this->company->settings;
        $settings->country_id = (string) $this->countryId($countryCode);
        $this->company->settings = $settings;
        $this->company->save();
        $this->company = $this->company->fresh();
    }

    private function setCompanyVatNumber(string $vatNumber): void
    {
        $settings = $this->company->settings;
        $settings->vat_number = $vatNumber;
        $this->company->settings = $settings;
        $this->company->save();
        $this->company = $this->company->fresh();
    }

    private function setCompanyRegionalVatNumber(string $countryCode, string $vatNumber): void
    {
        $taxData = $this->company->tax_data ?? new TaxModel();

        if (! isset($taxData->regions->EU->subregions->{$countryCode})) {
            $taxData->regions->EU->subregions->{$countryCode} = new \stdClass();
        }

        $taxData->regions->EU->subregions->{$countryCode}->vat_number = $vatNumber;
        $this->company->tax_data = $taxData;
        $this->company->save();
        $this->company = $this->company->fresh();
    }

    private function makeClient(int $countryId, string $classification, array $extra = []): Client
    {
        $client = Client::factory()->create(array_merge([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'country_id' => $countryId,
            'classification' => $classification,
        ], $extra));

        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'is_primary' => 1,
            'email' => 'test@example.com',
        ]);

        return $client->fresh();
    }

    /**
     * Build a RoutingResolver with a mocked StorecoveProxy, resolve routing,
     * and return the resulting storecove_meta routing array.
     */
    private function runMutatorWithMock(Client $client, callable $discoveryCallback): array
    {
        $client->load('country', 'company');

        // Use the existing test invoice but point it at our client
        $this->invoice->client_id = $client->id;
        $this->invoice->company_id = $this->company->id;
        $this->invoice->save();
        $this->invoice->setRelation('client', $client);
        $this->invoice->setRelation('company', $this->company);

        $proxyMock = $this->createMock(StorecoveProxy::class);
        $proxyMock->method('discovery')->willReturnCallback($discoveryCallback);
        $proxyMock->method('setCompany')->willReturnSelf();

        $router = new StorecoveRouter();
        $resolver = new RoutingResolver($this->invoice, $proxyMock, $router);
        $result = $resolver->resolve();

        // Build the same meta structure the old Mutator produced
        $meta = $result['meta'] ?? [];
        if (!empty($result['networks'])) {
            $meta['routing']['networks'] = $result['networks'];
        }

        return $meta;
    }

    // ──────────────────────────────────────────────────────
    // Routing resolves correctly for standard VAT countries
    // ──────────────────────────────────────────────────────

    public function testDeBusinessResolvesDeVatRouting(): void
    {
        $client = $this->makeClient(276, 'business', ['vat_number' => 'DE123456789']);

        $meta = $this->runMutatorWithMock($client, fn () => false);

        $this->assertNotEmpty($meta['routing']['eIdentifiers'] ?? []);
        $this->assertEquals('DE:VAT', $meta['routing']['eIdentifiers'][0]['scheme']);
        $this->assertEquals('DE123456789', $meta['routing']['eIdentifiers'][0]['id']);
    }

    public function testAtBusinessResolvesAtVatRouting(): void
    {
        $client = $this->makeClient(40, 'business', ['vat_number' => 'ATU12345678']);

        $meta = $this->runMutatorWithMock($client, fn () => false);

        $this->assertNotEmpty($meta['routing']['eIdentifiers'] ?? []);
        $this->assertEquals('AT:VAT', $meta['routing']['eIdentifiers'][0]['scheme']);
        $this->assertEquals('ATU12345678', $meta['routing']['eIdentifiers'][0]['id']);
    }

    // ──────────────────────────────────────────────────────
    // Non-VAT routing schemes use id_number
    // ──────────────────────────────────────────────────────

    public function testSeBusinessResolvesOrgnrWithIdNumber(): void
    {
        $client = $this->makeClient(752, 'business', [
            'id_number' => '1234567890',
            'vat_number' => 'SE123456789012',
        ]);

        $meta = $this->runMutatorWithMock($client, fn () => false);

        $this->assertEquals('SE:ORGNR', $meta['routing']['eIdentifiers'][0]['scheme']);
        $this->assertEquals('1234567890', $meta['routing']['eIdentifiers'][0]['id']);
    }

    public function testNoBusinessResolvesOrgWithIdNumber(): void
    {
        $client = $this->makeClient(578, 'business', [
            'id_number' => '123456789',
            'vat_number' => 'NO123456789MVA',
        ]);

        $meta = $this->runMutatorWithMock($client, fn () => false);

        $this->assertEquals('NO:ORG', $meta['routing']['eIdentifiers'][0]['scheme']);
        $this->assertEquals('123456789', $meta['routing']['eIdentifiers'][0]['id']);
    }

    public function testEeBusinessResolvesCcWithIdNumber(): void
    {
        $client = $this->makeClient(233, 'business', [
            'id_number' => '12345678',
            'vat_number' => 'EE123456789',
        ]);

        $meta = $this->runMutatorWithMock($client, fn () => false);

        $this->assertEquals('EE:CC', $meta['routing']['eIdentifiers'][0]['scheme']);
        $this->assertEquals('12345678', $meta['routing']['eIdentifiers'][0]['id']);
    }

    public function testLtBusinessResolvesLecWithIdNumber(): void
    {
        $client = $this->makeClient(440, 'business', [
            'id_number' => '1234567',
            'vat_number' => 'LT123456789',
        ]);

        $meta = $this->runMutatorWithMock($client, fn () => false);

        $this->assertEquals('LT:LEC', $meta['routing']['eIdentifiers'][0]['scheme']);
        $this->assertEquals('1234567', $meta['routing']['eIdentifiers'][0]['id']);
    }

    // ──────────────────────────────────────────────────────
    // DK adds DK prefix to identifier
    // ──────────────────────────────────────────────────────

    public function testDkBusinessAddsPrefix(): void
    {
        $client = $this->makeClient(208, 'business', [
            'id_number' => '12345678',
            'vat_number' => 'DK12345678',
        ]);

        $meta = $this->runMutatorWithMock($client, fn () => false);

        $this->assertEquals('DK:DIGST', $meta['routing']['eIdentifiers'][0]['scheme']);
        $this->assertStringStartsWith('DK', $meta['routing']['eIdentifiers'][0]['id']);
    }

    public function testDkBusinessDoesNotDoublePrefixIdentifier(): void
    {
        $client = $this->makeClient(208, 'business', [
            'id_number' => 'DK12345678',
            'vat_number' => 'DK12345678',
        ]);

        $meta = $this->runMutatorWithMock($client, fn () => false);

        $this->assertStringStartsNotWith('DKDK', $meta['routing']['eIdentifiers'][0]['id']);
    }

    // ──────────────────────────────────────────────────────
    // BE tries BE:EN first, then BE:VAT — no double prefix
    // ──────────────────────────────────────────────────────

    public function testBeDiscoveryTriesBeEnFirst(): void
    {
        $client = $this->makeClient(56, 'business', [
            'vat_number' => 'BE0123456789',
            'id_number' => '',
        ]);

        $triedSchemes = [];
        $this->runMutatorWithMock($client, function ($identifier, $scheme) use (&$triedSchemes) {
            $triedSchemes[] = $scheme;
            return false;
        });

        $this->assertEquals('BE:EN', $triedSchemes[0] ?? null, 'BE should try BE:EN first');
        $this->assertEquals('BE:VAT', $triedSchemes[1] ?? null, 'BE should try BE:VAT second');
    }

    public function testBeVatDoesNotDoublePrefixIdentifier(): void
    {
        $client = $this->makeClient(56, 'business', [
            'vat_number' => 'BE0123456789',
            'id_number' => '',
        ]);

        $beVatIdentifier = null;
        $this->runMutatorWithMock($client, function ($identifier, $scheme) use (&$beVatIdentifier) {
            if ($scheme === 'BE:VAT') {
                $beVatIdentifier = $identifier;
            }
            return false;
        });

        $this->assertEquals('BE0123456789', $beVatIdentifier, 'BE:VAT should not double-prefix');
    }

    public function testBeEnStripsPrefix(): void
    {
        $client = $this->makeClient(56, 'business', [
            'vat_number' => 'BE0123456789',
            'id_number' => '',
        ]);

        $beEnIdentifier = null;
        $this->runMutatorWithMock($client, function ($identifier, $scheme) use (&$beEnIdentifier) {
            if ($scheme === 'BE:EN') {
                $beEnIdentifier = $identifier;
            }
            return false;
        });

        $this->assertEquals('0123456789', $beEnIdentifier, 'BE:EN should strip BE prefix');
    }

    public function testBeEnSuccessStopsEarly(): void
    {
        $client = $this->makeClient(56, 'business', [
            'vat_number' => 'BE0123456789',
            'id_number' => '',
        ]);

        $meta = $this->runMutatorWithMock($client, function ($identifier, $scheme) {
            return $scheme === 'BE:EN';
        });

        $this->assertEquals('BE:EN', $meta['routing']['eIdentifiers'][0]['scheme']);
        $this->assertEquals('0123456789', $meta['routing']['eIdentifiers'][0]['id']);
    }

    // ──────────────────────────────────────────────────────
    // Explicit routing_id override
    // ──────────────────────────────────────────────────────

    public function testExplicitRoutingIdSplitsAsSchemeAndIdentifier(): void
    {
        $client = $this->makeClient(40, 'government', [
            'id_number' => 'ATGOV12345',
            'routing_id' => '9915:b',
        ]);

        $triedPairs = [];
        $this->runMutatorWithMock($client, function ($identifier, $scheme) use (&$triedPairs) {
            $triedPairs[] = ['scheme' => $scheme, 'identifier' => $identifier];
            return false;
        });

        // First attempt should be the explicit routing_id split
        $this->assertEquals('9915', $triedPairs[0]['scheme'] ?? null);
        $this->assertEquals('b', $triedPairs[0]['identifier'] ?? null);
    }

    public function testExplicitRoutingIdSucceedsFirst(): void
    {
        $client = $this->makeClient(40, 'government', [
            'id_number' => 'ATGOV12345',
            'routing_id' => '9915:b',
        ]);

        $meta = $this->runMutatorWithMock($client, function ($identifier, $scheme) {
            return $scheme === '9915';
        });

        $this->assertEquals('9915', $meta['routing']['eIdentifiers'][0]['scheme']);
        $this->assertEquals('b', $meta['routing']['eIdentifiers'][0]['id']);
    }

    // ──────────────────────────────────────────────────────
    // DE government uses routing_id for DE:LWID
    // ──────────────────────────────────────────────────────

    public function testDeGovernmentUsesRoutingId(): void
    {
        $client = $this->makeClient(276, 'government', [
            'routing_id' => '04011000-1234561234-56',
            'id_number' => 'some-id',
        ]);

        $meta = $this->runMutatorWithMock($client, fn () => false);

        $this->assertEquals('DE:LWID', $meta['routing']['eIdentifiers'][0]['scheme']);
        $this->assertEquals('04011000-1234561234-56', $meta['routing']['eIdentifiers'][0]['id']);
    }

    // ──────────────────────────────────────────────────────
    // No identifiers → no routing (SendEDocument would fail early)
    // ──────────────────────────────────────────────────────

    public function testNoIdentifiersProducesNoRouting(): void
    {
        $client = $this->makeClient(276, 'business', [
            'vat_number' => '',
            'id_number' => '',
            'routing_id' => '',
        ]);

        $meta = $this->runMutatorWithMock($client, fn () => false);

        $this->assertEmpty($meta, 'No identifiers should produce no routing metadata');
    }

    // ──────────────────────────────────────────────────────
    // Individual routes via email, not discovery
    // ──────────────────────────────────────────────────────

    public function testIndividualWithNoIdentifiersRoutesViaEmail(): void
    {
        $client = $this->makeClient(276, 'individual', [
            'vat_number' => '',
            'id_number' => '',
        ]);

        $meta = $this->runMutatorWithMock($client, fn () => false);

        // Individual with no identifiers gets email routing
        $this->assertArrayHasKey('routing', $meta);
        $this->assertArrayHasKey('emails', $meta['routing']);
    }

    // ──────────────────────────────────────────────────────
    // Identifier cleaning — special chars stripped
    // ──────────────────────────────────────────────────────

    public function testSpecialCharsAreStrippedFromIdentifier(): void
    {
        $client = $this->makeClient(276, 'business', [
            'vat_number' => 'DE 123.456-789',
        ]);

        $meta = $this->runMutatorWithMock($client, fn () => false);

        $this->assertEquals('DE123456789', $meta['routing']['eIdentifiers'][0]['id']);
    }

    // ──────────────────────────────────────────────────────
    // Deduplication — LU:VAT used for both routing and tax
    // ──────────────────────────────────────────────────────

    public function testLuBusinessSetsLuVatRouting(): void
    {
        $client = $this->makeClient(442, 'business', [
            'vat_number' => 'LU12345678',
        ]);

        $meta = $this->runMutatorWithMock($client, fn () => false);

        $this->assertEquals('LU:VAT', $meta['routing']['eIdentifiers'][0]['scheme']);
        $this->assertCount(1, $meta['routing']['eIdentifiers']);
    }

    // ──────────────────────────────────────────────────────
    // FR uses id_number (SIRENE/SIRET) for identifier
    // ──────────────────────────────────────────────────────

    public function testFrBusinessUsesIdNumberForIdentifier(): void
    {
        $client = $this->makeClient(250, 'business', [
            'id_number' => '12345678901234', // SIRET
            'vat_number' => 'FRAA123456789',
        ]);

        $meta = $this->runMutatorWithMock($client, fn () => false);

        $this->assertNotEmpty($meta['routing']['eIdentifiers'] ?? []);
        // FR should use id_number, not vat_number, for routing
        $id = $meta['routing']['eIdentifiers'][0]['id'];
        $this->assertEquals('12345678901234', $id);
    }


    public function testFrDomesticBusinessEnablesDgfipNetwork(): void
    {
        $this->setCompanyCountry('FR');

        $client = $this->makeClient($this->countryId('FR'), 'business', [
            'id_number' => '12345678901234',
            'vat_number' => 'FRAA123456789',
        ]);

        $meta = $this->runMutatorWithMock($client, fn () => false);

        $this->assertSame('FR:SIRET', $meta['routing']['eIdentifiers'][0]['scheme']);
        $this->assertSame('12345678901234', $meta['routing']['eIdentifiers'][0]['id']);

        $dgfip = collect($meta['routing']['networks'] ?? [])->firstWhere('application', 'fr-dgfip');
        $this->assertNotNull($dgfip, 'FR domestic B2B should enable the DGFIP network');
        $this->assertTrue($dgfip['settings']['enabled']);
    }

    public function testFrDomesticGovernmentDoesNotEnableDgfipNetwork(): void
    {
        $this->setCompanyCountry('FR');

        $client = $this->makeClient($this->countryId('FR'), 'government', [
            'id_number' => '12345678901234',
            'vat_number' => 'FRAA123456789',
        ]);

        $meta = $this->runMutatorWithMock($client, fn () => false);

        $this->assertSame('0009', $meta['routing']['eIdentifiers'][0]['scheme']);
        $this->assertSame('11000201100044', $meta['routing']['eIdentifiers'][0]['id']);

        $dgfip = collect($meta['routing']['networks'] ?? [])->firstWhere('application', 'fr-dgfip');
        $this->assertNull($dgfip, 'FR government should stay on Chorus Pro routing, not DGFIP B2B routing');
    }

    public function testFrSenderToForeignReceiverDoesNotEnableDgfipNetwork(): void
    {
        $this->setCompanyCountry('FR');

        $client = $this->makeClient($this->countryId('DE'), 'business', [
            'vat_number' => 'DE123456789',
        ]);

        $meta = $this->runMutatorWithMock($client, fn () => false);

        $this->assertSame('DE:VAT', $meta['routing']['eIdentifiers'][0]['scheme']);

        $dgfip = collect($meta['routing']['networks'] ?? [])->firstWhere('application', 'fr-dgfip');
        $this->assertNull($dgfip, 'FR to foreign Peppol receiver should not enable DGFIP domestic network');
    }

    public function testForeignSenderToFrReceiverDoesNotEnableDgfipNetwork(): void
    {
        $this->setCompanyCountry('DE');

        $client = $this->makeClient($this->countryId('FR'), 'business', [
            'id_number' => '12345678901234',
            'vat_number' => 'FRAA123456789',
        ]);

        $meta = $this->runMutatorWithMock($client, fn () => false);

        $this->assertSame('FR:SIRET', $meta['routing']['eIdentifiers'][0]['scheme']);

        $this->assertSame('', data_get($client->company->tax_data, 'regions.EU.subregions.FR.vat_number', ''));
        $dgfip = collect($meta['routing']['networks'] ?? [])->firstWhere('application', 'fr-dgfip');
        $this->assertNull($dgfip, 'Foreign sender without French seller tax presence should not enable DGFIP domestic network');
    }

    public function testForeignSenderWithOnlyPrimaryFrenchVatDoesNotEnableDgfipForFrBusinessReceiver(): void
    {
        $this->setCompanyCountry('DE');
        $this->setCompanyVatNumber('FR12345678901');

        $client = $this->makeClient($this->countryId('FR'), 'business', [
            'id_number' => '12345678901234',
            'vat_number' => 'FRAA123456789',
        ]);

        $meta = $this->runMutatorWithMock($client, fn () => false);

        $this->assertSame('FR:SIRET', $meta['routing']['eIdentifiers'][0]['scheme']);

        $dgfip = collect($meta['routing']['networks'] ?? [])->firstWhere('application', 'fr-dgfip');
        $this->assertNull($dgfip, 'Foreign sender primary VAT should not be treated as French regional tax presence');
    }

    public function testForeignSenderWithRegionalFrenchVatEnablesDgfipForFrBusinessReceiver(): void
    {
        $this->setCompanyCountry('DE');
        $this->setCompanyRegionalVatNumber('FR', 'FR22345678901');

        $client = $this->makeClient($this->countryId('FR'), 'business', [
            'id_number' => '12345678901234',
            'vat_number' => 'FRAA123456789',
        ]);

        $meta = $this->runMutatorWithMock($client, fn () => false);

        $this->assertSame('FR:SIRET', $meta['routing']['eIdentifiers'][0]['scheme']);

        $dgfip = collect($meta['routing']['networks'] ?? [])->firstWhere('application', 'fr-dgfip');
        $this->assertNotNull($dgfip, 'Foreign sender with a French regional VAT registration should enable DGFIP for FR B2B routing');
        $this->assertTrue($dgfip['settings']['enabled']);
    }

    // ──────────────────────────────────────────────────────
    // SG government uses composite endpoint (0195:...)
    // ──────────────────────────────────────────────────────

    public function testSgGovernmentUsesCompositeEndpoint(): void
    {
        $client = $this->makeClient(702, 'government', [
            'id_number' => '12345678A',
            'vat_number' => '',
        ]);

        $meta = $this->runMutatorWithMock($client, fn () => false);

        $this->assertNotEmpty($meta['routing']['eIdentifiers'] ?? []);
        // SG government routes via composite endpoint 0195:SGUENT08GA0028A
        $scheme = $meta['routing']['eIdentifiers'][0]['scheme'];
        $id = $meta['routing']['eIdentifiers'][0]['id'];
        $this->assertEquals('0195', $scheme);
        $this->assertEquals('SGUENT08GA0028A', $id);
    }

    // ──────────────────────────────────────────────────────
    // SE receiver enables Svefaktura network
    // ──────────────────────────────────────────────────────

    public function testSeReceiverEnablesSvefakturaNetwork(): void
    {
        $client = $this->makeClient(752, 'business', [
            'id_number' => '1234567890',
            'vat_number' => 'SE123456789012',
        ]);

        $meta = $this->runMutatorWithMock($client, fn () => false);

        // SE should have Svefaktura network enabled
        $this->assertArrayHasKey('networks', $meta['routing'] ?? []);
        $networks = $meta['routing']['networks'];
        $svefaktura = collect($networks)->firstWhere('application', 'svefaktura');
        $this->assertNotNull($svefaktura, 'Svefaktura network should be enabled for SE');
        $this->assertTrue($svefaktura['settings']['enabled']);
    }

    public function testNonSeReceiverDoesNotEnableSvefaktura(): void
    {
        $client = $this->makeClient(276, 'business', [
            'vat_number' => 'DE123456789',
        ]);

        $meta = $this->runMutatorWithMock($client, fn () => false);

        // Non-SE should NOT have Svefaktura network
        $networks = $meta['routing']['networks'] ?? [];
        $svefaktura = collect($networks)->firstWhere('application', 'svefaktura');
        $this->assertNull($svefaktura, 'Svefaktura should not be set for non-SE receivers');
    }


    public function testSeSenderToForeignReceiverDoesNotEnableSvefaktura(): void
    {
        $this->setCompanyCountry('SE');

        $client = $this->makeClient($this->countryId('DE'), 'business', [
            'vat_number' => 'DE123456789',
        ]);

        $meta = $this->runMutatorWithMock($client, fn () => false);

        $svefaktura = collect($meta['routing']['networks'] ?? [])->firstWhere('application', 'svefaktura');
        $this->assertNull($svefaktura, 'Svefaktura should only be enabled for SE receivers');
    }

    public function testPolishSenderEnablesKsefNetwork(): void
    {
        $this->setCompanyCountry('PL');

        $client = $this->makeClient($this->countryId('DE'), 'business', [
            'vat_number' => 'DE123456789',
        ]);

        $meta = $this->runMutatorWithMock($client, fn () => false);

        $ksef = collect($meta['routing']['networks'] ?? [])->firstWhere('application', 'pl-ksef');
        $this->assertNotNull($ksef, 'PL sender should enable KSeF network');
        $this->assertTrue($ksef['settings']['enabled']);
    }

    public function testRomanianSenderEnablesAnafNetwork(): void
    {
        $this->setCompanyCountry('RO');

        $client = $this->makeClient($this->countryId('DE'), 'business', [
            'vat_number' => 'DE123456789',
        ]);

        $meta = $this->runMutatorWithMock($client, fn () => false);

        $anaf = collect($meta['routing']['networks'] ?? [])->firstWhere('application', 'ro-anaf');
        $this->assertNotNull($anaf, 'RO sender should enable ANAF network');
        $this->assertTrue($anaf['settings']['enabled']);
    }

    // ──────────────────────────────────────────────────────
    // IT B2B/B2G: Codice Destinatario (CUUO) + Partita IVA for SDI
    // ──────────────────────────────────────────────────────

    public function testItBusinessUsesRoutingIdForCuuo(): void
    {
        $client = $this->makeClient(380, 'business', [
            'vat_number' => 'IT12345678901',
            'routing_id' => 'A1B2C3',
        ]);

        $meta = $this->runMutatorWithMock($client, fn () => false);

        $identifiers = $meta['routing']['eIdentifiers'] ?? [];
        $this->assertCount(2, $identifiers);
        $this->assertEquals('IT:CUUO', $identifiers[0]['scheme']);
        $this->assertEquals('A1B2C3', $identifiers[0]['id']);
        $this->assertEquals('IT:IVA', $identifiers[1]['scheme']);
        $this->assertEquals('IT12345678901', $identifiers[1]['id']);
    }

    public function testItDomesticIndividualIncludesCuuoAndCfIdentifiers(): void
    {
        $itCompany = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);
        $settings = $itCompany->settings;
        $settings->country_id = '380';
        $itCompany->settings = $settings;
        $itCompany->save();

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $itCompany->id,
            'country_id' => 380,
            'classification' => 'individual',
            'address1' => 'Via Roma 1',
            'city' => 'Roma',
            'postal_code' => '00100',
            'id_number' => 'RSSMRA85M01H501Z',
            'routing_id' => 'SUBM70N',
            'vat_number' => '',
        ]);

        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'company_id' => $itCompany->id,
            'is_primary' => 1,
            'email' => 'test@example.com',
        ]);

        $client = $client->fresh(['country']);

        $this->invoice->company_id = $itCompany->id;
        $this->invoice->client_id = $client->id;
        $this->invoice->save();
        $this->invoice->setRelation('company', $itCompany->fresh());
        $this->invoice->setRelation('client', $client);

        $proxyMock = $this->createMock(StorecoveProxy::class);
        $proxyMock->method('discovery')->willReturn(false);
        $proxyMock->method('setCompany')->willReturnSelf();

        $resolver = new RoutingResolver($this->invoice, $proxyMock, new StorecoveRouter());
        $result = $resolver->resolve();

        $identifiers = $result['meta']['routing']['eIdentifiers'] ?? [];
        $this->assertCount(2, $identifiers);
        $this->assertEquals('IT:CUUO', $identifiers[0]['scheme']);
        $this->assertEquals('SUBM70N', $identifiers[0]['id']);
        $this->assertEquals('IT:CF', $identifiers[1]['scheme']);
    }

    public function testItForeignIndividualIncludesCfAndEmailRouting(): void
    {
        $client = $this->makeClient(380, 'individual', [
            'id_number' => 'RSSMRA85M01H501Z',
            'routing_id' => '',
            'vat_number' => '',
        ]);

        $meta = $this->runMutatorWithMock($client, fn () => false);

        $this->assertEquals('IT:CF', $meta['routing']['eIdentifiers'][0]['scheme']);
        $this->assertEquals('RSSMRA85M01H501Z', $meta['routing']['eIdentifiers'][0]['id']);
        $this->assertArrayHasKey('emails', $meta['routing']);
        $this->assertContains('test@example.com', $meta['routing']['emails']);
    }

    // ──────────────────────────────────────────────────────
    // IN routes via email
    // ──────────────────────────────────────────────────────

    public function testInBusinessRoutesViaEmail(): void
    {
        $client = $this->makeClient(356, 'business', [
            'vat_number' => '22ABCDE1234F1Z1', // GSTIN
            'id_number' => '',
        ]);

        $meta = $this->runMutatorWithMock($client, fn () => false);

        // IN routes via email, not eIdentifiers
        $this->assertArrayHasKey('emails', $meta['routing'] ?? []);
    }

    // ──────────────────────────────────────────────────────
    // Explicit routing_id discovery failure falls through
    // ──────────────────────────────────────────────────────

    public function testExplicitRoutingIdFailsFallsThrough(): void
    {
        $client = $this->makeClient(276, 'business', [
            'vat_number' => 'DE123456789',
            'routing_id' => 'BADSCHEME:BADID',
        ]);

        $attempts = [];
        $meta = $this->runMutatorWithMock($client, function ($identifier, $scheme) use (&$attempts) {
            $attempts[] = $scheme;
            return false;
        });

        // First attempt is the explicit routing_id, then falls through to standard resolution
        $this->assertEquals('BADSCHEME', $attempts[0] ?? null);
        // Should still resolve via standard DE:VAT
        $this->assertNotEmpty($meta['routing']['eIdentifiers'] ?? []);
        $this->assertEquals('DE:VAT', $meta['routing']['eIdentifiers'][0]['scheme']);
    }

    // ──────────────────────────────────────────────────────
    // GLN priority — a valid GLN in routing_id ALWAYS wins
    // over handler candidates, regardless of country and
    // regardless of whether discovery succeeds.
    // ──────────────────────────────────────────────────────

    public function testValidGlnOnFrClientBeatsSiretCandidate(): void
    {
        // FR handler would normally produce FR:SIRET/SIRENE from id_number.
        // A valid GLN in routing_id must take priority.
        $client = $this->makeClient(250, 'business', [
            'vat_number' => 'FR12345678901',
            'id_number'  => '123456789', // would otherwise become FR:SIRENE
            'routing_id' => '0088:1234567890128',
        ]);

        $meta = $this->runMutatorWithMock($client, fn () => false);

        $this->assertEquals('0088', $meta['routing']['eIdentifiers'][0]['scheme']);
        $this->assertEquals('1234567890128', $meta['routing']['eIdentifiers'][0]['id']);
    }

    public function testGlnWithSchemePrefixOnFrClientWins(): void
    {
        $client = $this->makeClient(250, 'business', [
            'id_number'  => '123456789',
            'routing_id' => '0088:1234567890128',
        ]);

        $meta = $this->runMutatorWithMock($client, fn () => false);

        $this->assertEquals('0088', $meta['routing']['eIdentifiers'][0]['scheme']);
        $this->assertEquals('1234567890128', $meta['routing']['eIdentifiers'][0]['id']);
    }

    public function testValidGlnOnDeClientBeatsVatCandidate(): void
    {
        $client = $this->makeClient(276, 'business', [
            'vat_number' => 'DE123456789',
            'routing_id' => '0088:1234567890128',
        ]);

        $meta = $this->runMutatorWithMock($client, fn () => false);

        $this->assertEquals('0088', $meta['routing']['eIdentifiers'][0]['scheme']);
        $this->assertEquals('1234567890128', $meta['routing']['eIdentifiers'][0]['id']);
    }

    public function testValidGlnOnBeClientBeatsBeEnCandidate(): void
    {
        $client = $this->makeClient(56, 'business', [
            'vat_number' => 'BE0202239951',
            'routing_id' => '0088:1234567890128',
        ]);

        $meta = $this->runMutatorWithMock($client, fn () => false);

        $this->assertEquals('0088', $meta['routing']['eIdentifiers'][0]['scheme']);
    }

    public function testGlnSurvivesFailedDiscovery(): void
    {
        // User's complaint: GLN was silently dropped when discovery failed,
        // falling through to SIRET. That must not happen — GLN is authoritative.
        $client = $this->makeClient(250, 'business', [
            'id_number'  => '123456789',
            'routing_id' => '0088:1234567890128',
        ]);

        // Discovery returns false for everything
        $meta = $this->runMutatorWithMock($client, fn () => false);

        $this->assertEquals('0088', $meta['routing']['eIdentifiers'][0]['scheme'], 'GLN must not silently fall through when discovery fails');
    }

    public function testMalformed0088RoutingIdFallsThroughToHandler(): void
    {
        $client = $this->makeClient(276, 'business', [
            'vat_number' => 'DE123456789',
            'routing_id' => '0088:123456789012',
        ]);

        $meta = $this->runMutatorWithMock($client, fn () => false);

        $this->assertEquals('DE:VAT', $meta['routing']['eIdentifiers'][0]['scheme']);
    }

    public function testShortNumericRoutingIdNotTreatedAsGln(): void
    {
        // 10 digits is not a GLN. Must not be used.
        $client = $this->makeClient(276, 'business', [
            'vat_number' => 'DE123456789',
            'routing_id' => '1234567890',
        ]);

        $meta = $this->runMutatorWithMock($client, fn () => false);

        $this->assertEquals('DE:VAT', $meta['routing']['eIdentifiers'][0]['scheme']);
    }
}
