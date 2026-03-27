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
use App\Models\Client;
use Tests\MockAccountData;
use App\Models\ClientContact;
use App\Services\EDocument\Gateway\Storecove\Storecove;
use App\Services\EDocument\Gateway\Storecove\StorecoveProxy;
use App\Services\EDocument\Gateway\Storecove\Mutator;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Tests that SendEDocument fails early when the Mutator cannot resolve
 * PEPPOL routing (i.e. the client has no usable identifiers).
 *
 * Discovery is performed live at send time via Mutator::setClientRoutingCode()
 * — these tests verify the scheme/identifier resolution that feeds into it.
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
     * Build a Mutator wired to a mocked Storecove, run setClientRoutingCode(),
     * and return the resulting storecove_meta.
     */
    private function runMutatorWithMock(Client $client, callable $discoveryCallback): array
    {
        $client->load('country', 'company');

        // Use the existing test invoice but point it at our client
        $this->invoice->client_id = $client->id;
        $this->invoice->save();
        $this->invoice->setRelation('client', $client);

        $proxyMock = $this->createMock(StorecoveProxy::class);
        $proxyMock->method('discovery')->willReturnCallback($discoveryCallback);
        $proxyMock->method('setCompany')->willReturnSelf();

        $storecove = new Storecove();
        $storecove->proxy = $proxyMock;

        $mutator = new Mutator($storecove);
        $mutator->setInvoice($this->invoice);

        $mutator->setClientRoutingCode();

        return $mutator->getStorecoveMeta();
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
        $this->assertEquals('04011000123456123456', $meta['routing']['eIdentifiers'][0]['id']);
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
}
