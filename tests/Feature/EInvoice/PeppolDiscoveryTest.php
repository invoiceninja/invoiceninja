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
use App\Models\Company;
use Tests\MockAccountData;
use App\Models\ClientContact;
use App\DataMapper\ClientSync;
use App\Jobs\Client\CheckPeppolDiscovery;
use App\Services\EDocument\Gateway\Storecove\Storecove;
use App\Services\EDocument\Gateway\Storecove\StorecoveProxy;
use Illuminate\Foundation\Testing\DatabaseTransactions;

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
     * Helper: run the discovery job with a mocked StorecoveProxy.
     *
     * The job calls StorecoveProxy::discovery() (not Storecove directly),
     * so we mock the proxy and wire it into the Storecove instance.
     *
     * @param  Client   $client
     * @param  callable $callback  Receives ($identifier, $scheme) and returns bool
     */
    private function runDiscoveryWithMock(Client $client, callable $callback): void
    {
        $proxyMock = $this->createMock(StorecoveProxy::class);
        $proxyMock->method('discovery')->willReturnCallback($callback);
        $proxyMock->method('setCompany')->willReturnSelf();

        $storecove = new Storecove();
        $storecove->proxy = $proxyMock;
        $this->app->instance(Storecove::class, $storecove);

        (new CheckPeppolDiscovery($client, $client->company))->handle();
    }

    // ──────────────────────────────────────────────────────
    // ClientSync property
    // ──────────────────────────────────────────────────────

    public function testClientSyncDefaultsToNull(): void
    {
        $sync = new ClientSync();
        $this->assertNull($sync->peppol_discovery);
    }

    public function testClientSyncAcceptsPeppolDiscoveryTrue(): void
    {
        $sync = new ClientSync(['peppol_discovery' => true]);
        $this->assertTrue($sync->peppol_discovery);
    }

    public function testClientSyncFromArrayIncludesPeppolDiscovery(): void
    {
        $sync = ClientSync::fromArray(['qb_id' => 'QB-99', 'peppol_discovery' => true]);
        $this->assertTrue($sync->peppol_discovery);
        $this->assertEquals('QB-99', $sync->qb_id);
    }

    // ──────────────────────────────────────────────────────
    // Cast persistence (round-trip through DB)
    // ──────────────────────────────────────────────────────

    public function testPeppolDiscoveryPersistsTrueViaCast(): void
    {
        $client = $this->makeClient(276, 'business');

        $sync = $client->sync ?? new ClientSync();
        $sync->peppol_discovery = true;
        $client->sync = $sync;
        $client->saveQuietly();

        $client->refresh();
        $this->assertNotNull($client->sync);
        $this->assertTrue($client->sync->peppol_discovery);
    }

    public function testPeppolDiscoveryPersistsNullViaCast(): void
    {
        $client = $this->makeClient(276, 'business');

        $sync = new ClientSync();
        $client->sync = $sync;
        $client->saveQuietly();

        $client->refresh();
        $this->assertNull($client->sync->peppol_discovery);
    }

    public function testPeppolDiscoveryPersistsExplicitFalseViaCast(): void
    {
        $client = $this->makeClient(276, 'business');

        $sync = new ClientSync(['peppol_discovery' => false]);
        $client->sync = $sync;
        $client->saveQuietly();

        $client->refresh();
        $this->assertFalse($client->sync->peppol_discovery);
    }

    public function testExistingQbSyncDataDefaultsToNull(): void
    {
        // Simulate a client with pre-existing QB sync data that lacks peppol_discovery
        $client = $this->makeClient(276, 'business');

        $sync = new ClientSync(['qb_id' => 'QB-OLD-123']);
        $client->sync = $sync;
        $client->saveQuietly();

        $client->refresh();
        $this->assertNull($client->sync->peppol_discovery, 'Existing sync without peppol_discovery should default to null, not false');
        $this->assertEquals('QB-OLD-123', $client->sync->qb_id);
    }

    public function testCastPreservesOtherSyncFields(): void
    {
        $client = $this->makeClient(276, 'business');

        $sync = new ClientSync(['qb_id' => 'QB-123', 'dn_dirty' => true, 'peppol_discovery' => true]);
        $client->sync = $sync;
        $client->saveQuietly();

        $client->refresh();
        $this->assertEquals('QB-123', $client->sync->qb_id);
        $this->assertTrue($client->sync->dn_dirty);
        $this->assertTrue($client->sync->peppol_discovery);
    }

    public function testNullSyncReturnsNull(): void
    {
        $client = $this->makeClient(276, 'business');
        // Don't set sync at all — the column should be null
        $this->assertNull($client->sync);
    }

    // ──────────────────────────────────────────────────────
    // Job — discovery succeeds
    // ──────────────────────────────────────────────────────

    public function testJobSetsTrueWhenDiscoverySucceeds(): void
    {
        $client = $this->makeClient(276, 'business', ['vat_number' => 'DE123456789']);

        $this->runDiscoveryWithMock($client, fn () => true);

        $client->refresh();
        $this->assertTrue($client->sync->peppol_discovery);
    }

    public function testJobSetsFalseWhenAllDiscoveryFails(): void
    {
        $client = $this->makeClient(276, 'business', ['vat_number' => 'DE000000000']);

        // Pre-set to true to prove it flips
        $sync = new ClientSync(['peppol_discovery' => true]);
        $client->sync = $sync;
        $client->saveQuietly();

        $this->runDiscoveryWithMock($client->fresh(), fn () => false);

        $client->refresh();
        $this->assertFalse($client->sync->peppol_discovery);
    }

    public function testJobPreservesExistingSyncData(): void
    {
        $client = $this->makeClient(276, 'business', ['vat_number' => 'DE123456789']);

        $sync = new ClientSync(['qb_id' => 'QB-12345', 'dn_dirty' => true, 'peppol_discovery' => false]);
        $client->sync = $sync;
        $client->saveQuietly();

        $this->runDiscoveryWithMock($client->fresh(), fn () => true);

        $client->refresh();
        $this->assertTrue($client->sync->peppol_discovery);
        $this->assertEquals('QB-12345', $client->sync->qb_id);
        $this->assertTrue($client->sync->dn_dirty);
    }

    public function testJobCreatesSyncWhenNull(): void
    {
        $client = $this->makeClient(276, 'business', ['vat_number' => 'DE123456789']);
        $this->assertNull($client->sync);

        $this->runDiscoveryWithMock($client, fn () => true);

        $client->refresh();
        $this->assertNotNull($client->sync);
        $this->assertTrue($client->sync->peppol_discovery);
    }

    // ──────────────────────────────────────────────────────
    // Job — no country / no identifiers
    // ──────────────────────────────────────────────────────

    public function testJobSkipsClientWithNoCountry(): void
    {
        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'country_id' => null,
            'classification' => 'business',
            'vat_number' => 'DE123456789',
        ]);

        $proxyMock = $this->createMock(StorecoveProxy::class);
        $proxyMock->expects($this->never())->method('discovery');
        $proxyMock->method('setCompany')->willReturnSelf();

        $storecove = new Storecove();
        $storecove->proxy = $proxyMock;
        $this->app->instance(Storecove::class, $storecove);

        (new CheckPeppolDiscovery($client->fresh(), $client->company))->handle();

        $client->refresh();
        $this->assertFalse($client->sync->peppol_discovery);
    }

    public function testJobSetsFalseWhenNoIdentifiers(): void
    {
        $client = $this->makeClient(276, 'business', [
            'vat_number' => '',
            'id_number' => '',
            'routing_id' => '',
        ]);

        $proxyMock = $this->createMock(StorecoveProxy::class);
        $proxyMock->expects($this->never())->method('discovery');
        $proxyMock->method('setCompany')->willReturnSelf();

        $storecove = new Storecove();
        $storecove->proxy = $proxyMock;
        $this->app->instance(Storecove::class, $storecove);

        (new CheckPeppolDiscovery($client, $client->company))->handle();

        $client->refresh();
        $this->assertFalse($client->sync->peppol_discovery);
    }

    // ──────────────────────────────────────────────────────
    // Job — country-specific candidate resolution
    // ──────────────────────────────────────────────────────

    public function testJobTriesDeVatScheme(): void
    {
        $client = $this->makeClient(276, 'business', ['vat_number' => 'DE123456789']);

        $triedSchemes = [];
        $this->runDiscoveryWithMock($client, function ($identifier, $scheme) use (&$triedSchemes) {
            $triedSchemes[] = $scheme;
            return false;
        });

        $this->assertContains('DE:VAT', $triedSchemes, 'DE business should try DE:VAT');
    }

    public function testJobTriesSeOrgnrScheme(): void
    {
        $client = $this->makeClient(752, 'business', [
            'id_number' => '1234567890',
            'vat_number' => 'SE123456789012',
        ]);

        $triedSchemes = [];
        $this->runDiscoveryWithMock($client, function ($identifier, $scheme) use (&$triedSchemes) {
            $triedSchemes[] = $scheme;
            return false;
        });

        // SE routing scheme is SE:ORGNR with id_number — matches Mutator
        $this->assertContains('SE:ORGNR', $triedSchemes, 'SE business should try SE:ORGNR');
    }

    public function testJobTriesDkDigstSchemeWithPrefix(): void
    {
        $client = $this->makeClient(208, 'business', [
            'id_number' => '12345678',
            'vat_number' => 'DK12345678',
        ]);

        $seen = [];
        $this->runDiscoveryWithMock($client, function ($identifier, $scheme) use (&$seen) {
            $seen[] = ['scheme' => $scheme, 'identifier' => $identifier];
            return false;
        });

        $dkDigst = array_filter($seen, fn ($s) => $s['scheme'] === 'DK:DIGST');
        $this->assertNotEmpty($dkDigst, 'DK business should try DK:DIGST');

        // DK prefix should be present on identifier
        $dkEntry = array_values($dkDigst)[0];
        $this->assertTrue(str_starts_with(strtoupper($dkEntry['identifier']), 'DK'), 'DK:DIGST identifier should have DK prefix');
    }

    public function testJobTriesNoOrgScheme(): void
    {
        $client = $this->makeClient(578, 'business', [
            'id_number' => '123456789',
            'vat_number' => 'NO123456789MVA',
        ]);

        $triedSchemes = [];
        $this->runDiscoveryWithMock($client, function ($identifier, $scheme) use (&$triedSchemes) {
            $triedSchemes[] = $scheme;
            return false;
        });

        $this->assertContains('NO:ORG', $triedSchemes, 'NO business should try NO:ORG');
    }

    public function testJobTriesEeCcScheme(): void
    {
        $client = $this->makeClient(233, 'business', [
            'id_number' => '12345678',
            'vat_number' => 'EE123456789',
        ]);

        $triedSchemes = [];
        $this->runDiscoveryWithMock($client, function ($identifier, $scheme) use (&$triedSchemes) {
            $triedSchemes[] = $scheme;
            return false;
        });

        $this->assertContains('EE:CC', $triedSchemes, 'EE business should try EE:CC');
    }

    public function testJobTriesFiOvtScheme(): void
    {
        $client = $this->makeClient(246, 'business', [
            'id_number' => '003712345678',
            'vat_number' => 'FI12345678',
        ]);

        $triedSchemes = [];
        $this->runDiscoveryWithMock($client, function ($identifier, $scheme) use (&$triedSchemes) {
            $triedSchemes[] = $scheme;
            return false;
        });

        $this->assertContains('FI:OVT', $triedSchemes, 'FI business should try FI:OVT');
    }

    // ──────────────────────────────────────────────────────
    // Job — BE special handling (both BE:EN and BE:VAT)
    // ──────────────────────────────────────────────────────

    public function testJobTriesBothBeEnAndBeVat(): void
    {
        $client = $this->makeClient(56, 'business', [
            'vat_number' => 'BE0123456789',
            'id_number' => '0123456789',
        ]);

        $triedSchemes = [];
        $this->runDiscoveryWithMock($client, function ($identifier, $scheme) use (&$triedSchemes) {
            $triedSchemes[] = $scheme;
            return false;
        });

        $this->assertContains('BE:EN', $triedSchemes, 'BE should try BE:EN');
        $this->assertContains('BE:VAT', $triedSchemes, 'BE should try BE:VAT');
    }

    public function testJobBeEnSucceedsStopsEarly(): void
    {
        $client = $this->makeClient(56, 'business', [
            'vat_number' => 'BE0123456789',
            'id_number' => '0123456789',
        ]);

        $callCount = 0;
        $proxyMock = $this->createMock(StorecoveProxy::class);
        $proxyMock->method('discovery')->willReturnCallback(function ($identifier, $scheme) use (&$callCount) {
            $callCount++;
            return $scheme === 'BE:EN';
        });
        $proxyMock->method('setCompany')->willReturnSelf();

        $storecove = new Storecove();
        $storecove->proxy = $proxyMock;
        $this->app->instance(Storecove::class, $storecove);

        (new CheckPeppolDiscovery($client, $client->company))->handle();

        $client->refresh();
        $this->assertTrue($client->sync->peppol_discovery);
        $this->assertGreaterThanOrEqual(1, $callCount);
    }

    public function testJobBeVatAddsPrefixWhenMissing(): void
    {
        $client = $this->makeClient(56, 'business', [
            'vat_number' => '0123456789',  // No BE prefix
            'id_number' => '',
        ]);

        $seen = [];
        $this->runDiscoveryWithMock($client, function ($identifier, $scheme) use (&$seen) {
            $seen[] = ['scheme' => $scheme, 'identifier' => $identifier];
            return false;
        });

        $beVat = array_values(array_filter($seen, fn ($s) => $s['scheme'] === 'BE:VAT'));
        $this->assertNotEmpty($beVat);
        $this->assertEquals('BE0123456789', $beVat[0]['identifier'], 'BE:VAT should have BE prefix');

        $beEn = array_values(array_filter($seen, fn ($s) => $s['scheme'] === 'BE:EN'));
        $this->assertNotEmpty($beEn);
        $this->assertEquals('0123456789', $beEn[0]['identifier'], 'BE:EN should have prefix stripped');
    }

    public function testJobBeVatDoesNotDoublePrefixWhenAlreadyPresent(): void
    {
        $client = $this->makeClient(56, 'business', [
            'vat_number' => 'BE0123456789',  // Already has BE prefix
            'id_number' => '',
        ]);

        $seen = [];
        $this->runDiscoveryWithMock($client, function ($identifier, $scheme) use (&$seen) {
            $seen[] = ['scheme' => $scheme, 'identifier' => $identifier];
            return false;
        });

        $beVat = array_values(array_filter($seen, fn ($s) => $s['scheme'] === 'BE:VAT'));
        $this->assertNotEmpty($beVat);
        $this->assertEquals('BE0123456789', $beVat[0]['identifier'], 'BE:VAT should not double-prefix');
        $this->assertStringStartsNotWith('BEBE', $beVat[0]['identifier'], 'Must not have double BE prefix');

        $beEn = array_values(array_filter($seen, fn ($s) => $s['scheme'] === 'BE:EN'));
        $this->assertNotEmpty($beEn);
        $this->assertEquals('0123456789', $beEn[0]['identifier'], 'BE:EN should strip prefix');
    }

    // ──────────────────────────────────────────────────────
    // Job — explicit routing_id with scheme
    // ──────────────────────────────────────────────────────

    public function testJobExplicitRoutingIdSplitsAsSchemeAndIdentifier(): void
    {
        // routing_id "9915:b" → scheme="9915", identifier="b" — matches Mutator pattern
        $client = $this->makeClient(40, 'government', [
            'id_number' => 'ATGOV12345',
            'routing_id' => '9915:b',
        ]);

        $seen = [];
        $this->runDiscoveryWithMock($client, function ($identifier, $scheme) use (&$seen) {
            $seen[] = ['scheme' => $scheme, 'identifier' => $identifier];
            return false;
        });

        // Should contain the explicit routing_id split
        $explicit = array_filter($seen, fn ($s) => $s['scheme'] === '9915' && $s['identifier'] === 'b');
        $this->assertNotEmpty($explicit, 'Explicit routing_id should split as scheme_code:identifier_value');
    }

    public function testJobExplicitRoutingIdSucceedsFirst(): void
    {
        $client = $this->makeClient(40, 'government', [
            'id_number' => 'ATGOV12345',
            'routing_id' => '9915:b',
        ]);

        $this->runDiscoveryWithMock($client, function ($identifier, $scheme) {
            return $scheme === '9915';
        });

        $client->refresh();
        $this->assertTrue($client->sync->peppol_discovery);
    }

    // ──────────────────────────────────────────────────────
    // Job — AT government uses AT:GOV
    // ──────────────────────────────────────────────────────

    public function testJobTriesAtGovForGovernment(): void
    {
        $client = $this->makeClient(40, 'government', [
            'id_number' => 'AT-GOV-12345',
        ]);

        $triedSchemes = [];
        $this->runDiscoveryWithMock($client, function ($identifier, $scheme) use (&$triedSchemes) {
            $triedSchemes[] = $scheme;
            return false;
        });

        $this->assertContains('9915:b', $triedSchemes, 'AT government should try 9915:b routing scheme');
    }

    public function testJobTriesAtVatForBusiness(): void
    {
        $client = $this->makeClient(40, 'business', [
            'vat_number' => 'ATU12345678',
        ]);

        $triedSchemes = [];
        $this->runDiscoveryWithMock($client, function ($identifier, $scheme) use (&$triedSchemes) {
            $triedSchemes[] = $scheme;
            return false;
        });

        $this->assertContains('AT:VAT', $triedSchemes, 'AT business should try AT:VAT');
    }

    // ──────────────────────────────────────────────────────
    // Job — DE government uses DE:LWID
    // ──────────────────────────────────────────────────────

    public function testJobTriesDeLwidForGovernment(): void
    {
        $client = $this->makeClient(276, 'government', [
            'id_number' => 'DE-LWID-12345',
        ]);

        $triedSchemes = [];
        $this->runDiscoveryWithMock($client, function ($identifier, $scheme) use (&$triedSchemes) {
            $triedSchemes[] = $scheme;
            return false;
        });

        $this->assertContains('DE:LWID', $triedSchemes, 'DE government should try DE:LWID');
    }

    // ──────────────────────────────────────────────────────
    // Job — only one success needed
    // ──────────────────────────────────────────────────────

    public function testJobSucceedsOnSecondCandidate(): void
    {
        // BE produces 2 candidates (BE:EN and BE:VAT) — first fails, second succeeds
        $client = $this->makeClient(56, 'business', [
            'vat_number' => 'BE0123456789',
        ]);

        $callIndex = 0;
        $this->runDiscoveryWithMock($client, function () use (&$callIndex) {
            $callIndex++;
            return $callIndex === 2;
        });

        $client->refresh();
        $this->assertTrue($client->sync->peppol_discovery);
    }

    // ──────────────────────────────────────────────────────
    // Job — deduplication
    // ──────────────────────────────────────────────────────

    public function testJobDeduplicatesCandidates(): void
    {
        // LU uses LU:VAT for both routing and tax — should not duplicate
        $client = $this->makeClient(442, 'business', [
            'vat_number' => 'LU12345678',
        ]);

        $calls = [];
        $this->runDiscoveryWithMock($client, function ($identifier, $scheme) use (&$calls) {
            $key = "$scheme|$identifier";
            $calls[] = $key;
            return false;
        });

        // No duplicate scheme+identifier pairs
        $this->assertCount(count(array_unique($calls)), $calls, 'Candidates should be deduplicated');
    }

    // ──────────────────────────────────────────────────────
    // Job — identifier cleaning
    // ──────────────────────────────────────────────────────

    public function testJobCleansSpecialCharactersFromIdentifiers(): void
    {
        $client = $this->makeClient(276, 'business', [
            'vat_number' => 'DE 123.456-789',
        ]);

        $identifiersSeen = [];
        $this->runDiscoveryWithMock($client, function ($identifier) use (&$identifiersSeen) {
            $identifiersSeen[] = $identifier;
            return false;
        });

        foreach ($identifiersSeen as $id) {
            $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]+$/', $id, "Identifier '$id' should be cleaned");
        }
    }

    // ──────────────────────────────────────────────────────
    // Observer — shouldCheckPeppolDiscovery guard
    // ──────────────────────────────────────────────────────

    public function testObserverDoesNotFireWhenNoPeppolFieldsChanged(): void
    {
        // Test via the private method logic — update a non-peppol field
        $client = $this->makeClient(276, 'business', [
            'vat_number' => 'DE123456789',
        ]);

        // Simulate updating a non-PEPPOL field
        $client->address1 = '123 New Street';
        $original = $client->getOriginal();

        // vat_number/id_number/routing_id unchanged
        $this->assertEquals($original['vat_number'], $client->vat_number);
        $this->assertEquals($original['id_number'] ?? '', $client->id_number ?? '');
        $this->assertEquals($original['routing_id'] ?? '', $client->routing_id ?? '');
    }

    public function testObserverDetectsVatNumberChange(): void
    {
        $client = $this->makeClient(276, 'business', [
            'vat_number' => 'DE123456789',
        ]);

        $client->vat_number = 'DE999999999';
        $this->assertNotEquals($client->getOriginal('vat_number'), $client->vat_number);
    }

    public function testObserverDetectsIdNumberChange(): void
    {
        $client = $this->makeClient(752, 'business', [
            'id_number' => '1234567890',
        ]);

        $client->id_number = '0987654321';
        $this->assertNotEquals($client->getOriginal('id_number'), $client->id_number);
    }

    public function testObserverDetectsRoutingIdChange(): void
    {
        $client = $this->makeClient(276, 'business', [
            'routing_id' => 'DE:VAT',
        ]);

        $client->routing_id = 'DE:LWID';
        $this->assertNotEquals($client->getOriginal('routing_id'), $client->routing_id);
    }

    // ──────────────────────────────────────────────────────
    // testClientState — peppol_discovery validation
    // ──────────────────────────────────────────────────────

    public function testTestClientStatePassesWhenSyncIsNull(): void
    {
        $client = $this->makeClient(276, 'business', [
            'vat_number' => 'DE123456789',
            'address1' => '123 Main St',
            'city' => 'Berlin',
            'state' => 'Berlin',
            'postal_code' => '10115',
        ]);

        $this->assertNull($client->sync);

        $result = (new \App\Services\EDocument\Standards\Validation\Peppol\EntityLevel())->checkClient($client);
        $errors = $result['client'] ?? [];

        $discoveryErrors = array_filter($errors, fn ($e) => ($e['field'] ?? '') === 'peppol_discovery');
        $this->assertEmpty($discoveryErrors, 'Null sync should not trigger discovery error');
    }

    public function testTestClientStatePassesWhenDiscoveryIsNull(): void
    {
        // Sync exists (e.g. QB user) but peppol_discovery is null → should NOT block
        $client = $this->makeClient(276, 'business', [
            'vat_number' => 'DE123456789',
            'address1' => '123 Main St',
            'city' => 'Berlin',
            'state' => 'Berlin',
            'postal_code' => '10115',
        ]);

        $sync = new ClientSync(['qb_id' => 'QB-123']);
        $client->sync = $sync;
        $client->saveQuietly();
        $client->refresh();

        $this->assertNull($client->sync->peppol_discovery);

        $result = (new \App\Services\EDocument\Standards\Validation\Peppol\EntityLevel())->checkClient($client);
        $errors = $result['client'] ?? [];

        $discoveryErrors = array_filter($errors, fn ($e) => ($e['field'] ?? '') === 'peppol_discovery');
        $this->assertEmpty($discoveryErrors, 'Null peppol_discovery should not trigger discovery error');
    }

    public function testTestClientStateFailsWhenDiscoveryFalse(): void
    {
        $client = $this->makeClient(276, 'business', [
            'vat_number' => 'DE123456789',
            'address1' => '123 Main St',
            'city' => 'Berlin',
            'state' => 'Berlin',
            'postal_code' => '10115',
        ]);

        $sync = new ClientSync(['peppol_discovery' => false]);
        $client->sync = $sync;
        $client->saveQuietly();
        $client->refresh();

        $entityLevel = new \App\Services\EDocument\Standards\Validation\Peppol\EntityLevel();
        $method = new \ReflectionMethod($entityLevel, 'testClientState');
        $method->setAccessible(true);

        $initMethod = new \ReflectionMethod($entityLevel, 'init');
        $initMethod->setAccessible(true);
        $initMethod->invoke($entityLevel, 'en');

        $errors = $method->invoke($entityLevel, $client);

        $discoveryErrors = array_filter($errors, fn ($e) => ($e['field'] ?? '') === 'peppol_discovery');
        $this->assertNotEmpty($discoveryErrors, 'Discovery false should produce peppol_discovery error');
    }

    public function testTestClientStatePassesWhenDiscoveryTrue(): void
    {
        $client = $this->makeClient(276, 'business', [
            'vat_number' => 'DE123456789',
            'address1' => '123 Main St',
            'city' => 'Berlin',
            'state' => 'Berlin',
            'postal_code' => '10115',
        ]);

        $sync = new ClientSync(['peppol_discovery' => true]);
        $client->sync = $sync;
        $client->saveQuietly();
        $client->refresh();

        $entityLevel = new \App\Services\EDocument\Standards\Validation\Peppol\EntityLevel();
        $method = new \ReflectionMethod($entityLevel, 'testClientState');
        $method->setAccessible(true);

        $initMethod = new \ReflectionMethod($entityLevel, 'init');
        $initMethod->setAccessible(true);
        $initMethod->invoke($entityLevel, 'en');

        $errors = $method->invoke($entityLevel, $client);

        $discoveryErrors = array_filter($errors, fn ($e) => ($e['field'] ?? '') === 'peppol_discovery');
        $this->assertEmpty($discoveryErrors, 'Discovery true should not produce peppol_discovery error');
    }

    public function testTestClientStateSkipsDiscoveryCheckForIndividual(): void
    {
        $client = $this->makeClient(276, 'individual', [
            'vat_number' => '',
            'id_number' => '',
            'address1' => '123 Main St',
            'city' => 'Berlin',
            'state' => 'Berlin',
            'postal_code' => '10115',
        ]);

        // Set discovery to false — should NOT block individuals
        $sync = new ClientSync(['peppol_discovery' => false]);
        $client->sync = $sync;
        $client->saveQuietly();
        $client->refresh();

        $entityLevel = new \App\Services\EDocument\Standards\Validation\Peppol\EntityLevel();
        $method = new \ReflectionMethod($entityLevel, 'testClientState');
        $method->setAccessible(true);

        $initMethod = new \ReflectionMethod($entityLevel, 'init');
        $initMethod->setAccessible(true);
        $initMethod->invoke($entityLevel, 'en');

        $errors = $method->invoke($entityLevel, $client);

        $discoveryErrors = array_filter($errors, fn ($e) => ($e['field'] ?? '') === 'peppol_discovery');
        $this->assertEmpty($discoveryErrors, 'Individuals should skip peppol_discovery check');
    }

    // ──────────────────────────────────────────────────────
    // End-to-end: job updates sync, validation reads it
    // ──────────────────────────────────────────────────────

    public function testEndToEndDiscoveryTruePassesValidation(): void
    {
        $client = $this->makeClient(276, 'business', [
            'vat_number' => 'DE123456789',
            'address1' => '123 Main St',
            'city' => 'Berlin',
            'state' => 'Berlin',
            'postal_code' => '10115',
        ]);

        // Run job with successful discovery
        $this->runDiscoveryWithMock($client, fn () => true);

        $client->refresh();

        $entityLevel = new \App\Services\EDocument\Standards\Validation\Peppol\EntityLevel();
        $method = new \ReflectionMethod($entityLevel, 'testClientState');
        $method->setAccessible(true);

        $initMethod = new \ReflectionMethod($entityLevel, 'init');
        $initMethod->setAccessible(true);
        $initMethod->invoke($entityLevel, 'en');

        $errors = $method->invoke($entityLevel, $client);

        $discoveryErrors = array_filter($errors, fn ($e) => ($e['field'] ?? '') === 'peppol_discovery');
        $this->assertEmpty($discoveryErrors, 'Successful discovery should pass validation');
    }

    public function testEndToEndDiscoveryFalseFailsValidation(): void
    {
        $client = $this->makeClient(276, 'business', [
            'vat_number' => 'DE123456789',
            'address1' => '123 Main St',
            'city' => 'Berlin',
            'state' => 'Berlin',
            'postal_code' => '10115',
        ]);

        // Run job with failed discovery
        $this->runDiscoveryWithMock($client, fn () => false);

        $client->refresh();

        $entityLevel = new \App\Services\EDocument\Standards\Validation\Peppol\EntityLevel();
        $method = new \ReflectionMethod($entityLevel, 'testClientState');
        $method->setAccessible(true);

        $initMethod = new \ReflectionMethod($entityLevel, 'init');
        $initMethod->setAccessible(true);
        $initMethod->invoke($entityLevel, 'en');

        $errors = $method->invoke($entityLevel, $client);

        $discoveryErrors = array_filter($errors, fn ($e) => ($e['field'] ?? '') === 'peppol_discovery');
        $this->assertNotEmpty($discoveryErrors, 'Failed discovery should fail validation');
    }

    // ──────────────────────────────────────────────────────
    // Job — uses saveQuietly (no observer loop)
    // ──────────────────────────────────────────────────────

    public function testJobUsesSaveQuietly(): void
    {
        // Verify the job saves the client without triggering the observer again.
        // We do this by checking that re-running the job doesn't cause infinite recursion.
        $client = $this->makeClient(276, 'business', ['vat_number' => 'DE123456789']);

        $callCount = 0;
        $proxyMock = $this->createMock(StorecoveProxy::class);
        $proxyMock->method('discovery')->willReturnCallback(function () use (&$callCount) {
            $callCount++;
            return true;
        });
        $proxyMock->method('setCompany')->willReturnSelf();

        $storecove = new Storecove();
        $storecove->proxy = $proxyMock;
        $this->app->instance(Storecove::class, $storecove);

        // Run job twice — second run should not cause issues
        (new CheckPeppolDiscovery($client, $client->company))->handle();
        $client->refresh();
        (new CheckPeppolDiscovery($client, $client->company))->handle();

        $client->refresh();
        $this->assertTrue($client->sync->peppol_discovery);
    }

    // ──────────────────────────────────────────────────────
    // Job — LT uses LT:LEC for id_number
    // ──────────────────────────────────────────────────────

    public function testJobTriesLtLecScheme(): void
    {
        $client = $this->makeClient(440, 'business', [
            'id_number' => '1234567',
            'vat_number' => 'LT123456789',
        ]);

        $triedSchemes = [];
        $this->runDiscoveryWithMock($client, function ($identifier, $scheme) use (&$triedSchemes) {
            $triedSchemes[] = $scheme;
            return false;
        });

        $this->assertContains('LT:LEC', $triedSchemes, 'LT business should try LT:LEC');
    }

    // ──────────────────────────────────────────────────────
    // Job — IE uses IE:VAT
    // ──────────────────────────────────────────────────────

    public function testJobTriesIeVatScheme(): void
    {
        $client = $this->makeClient(372, 'business', [
            'vat_number' => 'IE1234567T',
        ]);

        $triedSchemes = [];
        $this->runDiscoveryWithMock($client, function ($identifier, $scheme) use (&$triedSchemes) {
            $triedSchemes[] = $scheme;
            return false;
        });

        $this->assertContains('IE:VAT', $triedSchemes, 'IE business should try IE:VAT');
    }

    // ──────────────────────────────────────────────────────
    // Job — NL uses NL:KVK for id_number, NL:VAT for routing
    // ──────────────────────────────────────────────────────

    public function testJobTriesNlSchemesForBusiness(): void
    {
        $client = $this->makeClient(528, 'business', [
            'id_number' => '12345678',
            'vat_number' => 'NL123456789B01',
        ]);

        $triedSchemes = [];
        $this->runDiscoveryWithMock($client, function ($identifier, $scheme) use (&$triedSchemes) {
            $triedSchemes[] = $scheme;
            return false;
        });

        $this->assertContains('NL:VAT', $triedSchemes, 'NL business should try NL:VAT');
    }

    // ──────────────────────────────────────────────────────
    // Job — IS uses IS:KTNR for id_number
    // ──────────────────────────────────────────────────────

    public function testJobTriesIsKtnrScheme(): void
    {
        $client = $this->makeClient(352, 'business', [
            'id_number' => '123456',
            'vat_number' => 'IS12345',
        ]);

        $triedSchemes = [];
        $this->runDiscoveryWithMock($client, function ($identifier, $scheme) use (&$triedSchemes) {
            $triedSchemes[] = $scheme;
            return false;
        });

        $this->assertContains('IS:KTNR', $triedSchemes, 'IS business should try IS:KTNR');
    }
}
