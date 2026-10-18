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

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Country;
use App\Services\EDocument\Gateway\Storecove\RoutingResolver;
use App\Services\EDocument\Gateway\Storecove\StorecoveProxy;
use App\Services\EDocument\Gateway\Storecove\StorecoveRouter;
use App\Services\EDocument\Standards\Peppol\CountryFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * Finland Finvoice (Storecove): FI:OPID in routing_id, FI:OVT in id_number, FI:VAT on the document.
 */
class FinlandFinvoiceTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    private const OPID = '003708599126';

    private const OVT = '003709824102';

    private const VAT = 'FI09824102';

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTestData();
    }

    private function fiCountryId(): int
    {
        return (int) Country::where('iso_3166_2', 'FI')->firstOrFail()->id;
    }

    private function makeFiClient(array $extra = []): Client
    {
        $client = Client::factory()->create(array_merge([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'country_id' => $this->fiCountryId(),
            'classification' => 'business',
            'vat_number' => self::VAT,
            'id_number' => self::OVT,
            'routing_id' => self::OPID,
        ], $extra));

        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'is_primary' => 1,
            'email' => 'fi-client@example.com',
        ]);

        return $client->fresh(['country']);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveRouting(Client $client): array
    {
        $client->load('country');
        $client->setRelation('company', $this->company);

        $this->invoice->client_id = $client->id;
        $this->invoice->company_id = $this->company->id;
        $this->invoice->save();
        $this->invoice->setRelation('client', $client);
        $this->invoice->setRelation('company', $this->company);

        $proxyMock = $this->createMock(StorecoveProxy::class);
        $proxyMock->method('discovery')->willReturn(false);
        $proxyMock->method('setCompany')->willReturnSelf();

        $resolver = new RoutingResolver($this->invoice, $proxyMock, new StorecoveRouter());

        return $resolver->resolve();
    }

    public function testRoutingResolverEmitsOpidAndOvtWithoutDiscovery(): void
    {
        $client = $this->makeFiClient();
        $result = $this->resolveRouting($client);

        $this->assertSame('eIdentifiers', $result['type']);
        $identifiers = $result['meta']['routing']['eIdentifiers'] ?? [];
        $this->assertCount(2, $identifiers);
        $this->assertSame('FI:OPID', $identifiers[0]['scheme']);
        $this->assertSame(self::OPID, $identifiers[0]['id']);
        $this->assertSame('FI:OVT', $identifiers[1]['scheme']);
        $this->assertSame(self::OVT, $identifiers[1]['id']);
    }

    public function testDocumentPublicIdentifiersIncludeOvtAndVat(): void
    {
        $client = $this->makeFiClient();
        $router = new StorecoveRouter();

        $pairs = CountryFactory::make('FI')->storecoveCustomerPartyPublicIdentifiers($client, $this->invoice, $router);

        $this->assertCount(2, $pairs);
        $this->assertSame('FI:OVT', $pairs[0]['scheme']);
        $this->assertSame(self::OVT, $pairs[0]['id']);
        $this->assertSame('FI:VAT', $pairs[1]['scheme']);
        $this->assertSame(self::VAT, $pairs[1]['id']);
    }

    public function testBuyerEndpointIdUsesOvtFromIdNumberNotOpidInRoutingId(): void
    {
        $client = $this->makeFiClient();
        $router = new StorecoveRouter();

        $resolved = CountryFactory::make('FI')->resolveClientEndpointScheme($client, $router);

        $this->assertSame('0037', $resolved['scheme']);
        $this->assertSame(self::OVT, $resolved['id']);
    }

    public function testBuyerEndpointIdEmptyWhenOvtMissing(): void
    {
        $client = $this->makeFiClient(['id_number' => '']);
        $router = new StorecoveRouter();

        $resolved = CountryFactory::make('FI')->resolveClientEndpointScheme($client, $router);

        $this->assertSame('', $resolved['scheme']);
        $this->assertSame('', $resolved['id']);
    }

    public function testValidationPassesWithCanonicalFinvoiceFields(): void
    {
        $client = $this->makeFiClient();

        $errors = CountryFactory::make('FI')->validateReceiverRoutingIdentifiers(
            $client,
            'business',
            new StorecoveRouter(),
            'AT',
        );

        $this->assertSame([], $errors);
    }

    public function testValidationBlocksMissingOperatorId(): void
    {
        $client = $this->makeFiClient(['routing_id' => '']);

        $errors = CountryFactory::make('FI')->validateReceiverRoutingIdentifiers(
            $client,
            'business',
            new StorecoveRouter(),
            null,
        );

        $fields = array_column($errors, 'field');
        $this->assertContains('routing_id', $fields);
    }

    public function testValidationBlocksOvtInRoutingId(): void
    {
        $client = $this->makeFiClient(['routing_id' => '0216:' . self::OVT]);

        $errors = CountryFactory::make('FI')->validateReceiverRoutingIdentifiers(
            $client,
            'business',
            new StorecoveRouter(),
            null,
        );

        $fields = array_column($errors, 'field');
        $this->assertContains('routing_id', $fields);
    }

    public function testRoutingResolverDoesNotFallBackToSingleOvtWhenOpidMissing(): void
    {
        $client = $this->makeFiClient(['routing_id' => '0216:' . self::OVT]);
        $result = $this->resolveRouting($client);

        $this->assertSame('none', $result['type']);
    }

    public function testGovernmentClassificationUsesDualRouting(): void
    {
        $client = $this->makeFiClient(['classification' => 'government']);
        $result = $this->resolveRouting($client);

        $this->assertSame('eIdentifiers', $result['type']);
        $this->assertCount(2, $result['meta']['routing']['eIdentifiers'] ?? []);
    }
}
