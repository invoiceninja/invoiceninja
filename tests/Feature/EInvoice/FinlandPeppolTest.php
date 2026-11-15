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
use App\Models\Credit;
use App\Services\EDocument\Support\GlnIdentifier;
use App\Services\EDocument\Gateway\Storecove\RoutingResolver;
use App\Services\EDocument\Gateway\Storecove\StorecoveProxy;
use App\Services\EDocument\Gateway\Storecove\StorecoveRouter;
use App\Services\EDocument\Standards\Peppol\CountryFactory;
use App\Services\EDocument\Standards\Validation\Peppol\EntityLevel;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\MockAccountData;
use Tests\TestCase;

class FinlandPeppolTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    private const OVT = '003709824102';

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTestData();
    }

    private function makeFiClient(array $extra = []): Client
    {
        $client = Client::factory()->create(array_merge([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'country_id' => Country::where('iso_3166_2', 'FI')->firstOrFail()->id,
            'classification' => 'business',
            'address1' => 'Mannerheimintie 1',
            'city' => 'Helsinki',
            'postal_code' => '00100',
            'vat_number' => 'FI09824102',
            'id_number' => self::OVT,
            'routing_id' => '',
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

    private function makeResolver(Client $client, bool $discovered = false, bool $is_credit = false): RoutingResolver
    {
        $document = $is_credit ? new Credit() : $this->invoice;
        $document->setRelation('client', $client);
        $document->setRelation('company', $this->company);

        $proxy = $this->createMock(StorecoveProxy::class);
        $proxy->method('setCompany')->willReturnSelf();
        $scheme = GlnIdentifier::tryParse((string) ($client->routing_id ?? '')) === null ? 'FI:OVT2' : '0088';
        $proxy->method('discovery')->with($this->anything(), $scheme)->willReturn($discovered);

        return new RoutingResolver($document, $proxy, new StorecoveRouter());
    }

    public static function validRecipientCases(): array
    {
        return [
            'OVT without operator' => [[], self::OVT],
            'legacy operator ignored when ID Number is valid' => [['routing_id' => '003708599126'], self::OVT],
            'bare digits are not a GLN override' => [['routing_id' => '5401205000102'], self::OVT],
            'ID Number preferred to numeric OVT routing' => [['routing_id' => '0216:003712345678'], self::OVT],
            'ID Number preferred to friendly OVT routing' => [['routing_id' => 'FI:OVT2:003712345678'], self::OVT],
            'bare OVT fallback' => [['id_number' => '', 'routing_id' => self::OVT], self::OVT],
            'numeric OVT fallback' => [['id_number' => '', 'routing_id' => '0216:' . self::OVT], self::OVT],
            'friendly OVT fallback' => [['id_number' => '', 'routing_id' => 'FI:OVT2:' . self::OVT], self::OVT],
            'invalid ID Number uses fallback' => [['id_number' => 'INVALID', 'routing_id' => self::OVT], self::OVT],
            'malformed GLN falls through' => [['routing_id' => '0088:123'], self::OVT],
            'nonnumeric GLN falls through' => [['routing_id' => '0088:ABCDEFGHIJKLM'], self::OVT],
            'other routing scheme falls through' => [['routing_id' => '0037:003712345678'], self::OVT],
            'five character suffix' => [['id_number' => self::OVT . 'TST01'], self::OVT . 'TST01'],
            'formatting normalized' => [['id_number' => '0037 0982410-2'], self::OVT],
            'GLN preferred to OVT' => [['routing_id' => '0088:5401205000102'], '5401205000102', '0088'],
            'GLN without OVT' => [['id_number' => '', 'routing_id' => '0088:5401205000102'], '5401205000102', '0088'],
            'GLN format needs no check digit' => [['routing_id' => '0088:0000000000001'], '0000000000001', '0088'],
        ];
    }

    #[DataProvider('validRecipientCases')]
    public function testRecipientIdentifierIsConsistent(array $extra, string $expected_id, string $expected_scheme = 'FI:OVT2'): void
    {
        $client = $this->makeFiClient($extra);
        $handler = CountryFactory::make('FI');
        $router = new StorecoveRouter();
        $expected = ['scheme' => $expected_scheme, 'id' => $expected_id];

        $this->assertSame([], $handler->validateReceiverRoutingIdentifiers($client, 'business', $router));
        $this->assertSame([], (new EntityLevel())->checkClient($client)['client'] ?? []);
        $this->assertSame([$expected], $handler->getCandidates($client, 'business', $router));
        $this->assertSame(['scheme' => $expected_scheme === '0088' ? '0088' : '0216', 'id' => $expected_id], $handler->resolveClientEndpointScheme($client, $router));
        $this->assertSame($expected, $handler->storecoveCustomerPartyPublicIdentifiers($client, $this->invoice, $router)[0]);

        $result = $this->makeResolver($client)->resolve();
        $this->assertSame('eIdentifiers', $result['type']);
        $this->assertSame(['eIdentifiers' => [$expected]], $result['meta']['routing']);
        $this->assertFalse($this->makeResolver($client)->discoverOnPeppol());
        $this->assertTrue($this->makeResolver($client, true)->discoverOnPeppol());
    }

    public static function invalidRecipientCases(): array
    {
        return [
            'missing OVT' => [['id_number' => ''], 'id_number'],
            'VAT is not an OVT fallback' => [['id_number' => '', 'vat_number' => 'FI09824102'], 'id_number'],
            'wrong prefix' => [['id_number' => '123709824102'], 'id_number'],
            'short OVT' => [['id_number' => '00370982410'], 'id_number'],
            'long suffix' => [['id_number' => self::OVT . 'ABCDEF'], 'id_number'],
            'lowercase suffix' => [['id_number' => self::OVT . 'abc'], 'id_number'],
            'invalid punctuation' => [['id_number' => self::OVT . '!'], 'id_number'],
            'legacy numeric scheme' => [['id_number' => '', 'routing_id' => '0037:' . self::OVT], 'routing_id'],
            'legacy friendly scheme' => [['id_number' => '', 'routing_id' => 'FI:OVT:' . self::OVT], 'routing_id'],
            'operator scheme' => [['id_number' => '', 'routing_id' => 'FI:OPID:003708599126'], 'routing_id'],
            'VAT scheme' => [['id_number' => '', 'routing_id' => '0213:FI09824102'], 'routing_id'],
            'short GLN without OVT' => [['id_number' => '', 'routing_id' => '0088:123'], 'routing_id'],
            'long GLN without OVT' => [['id_number' => '', 'routing_id' => '0088:12345678901234'], 'routing_id'],
            'nonnumeric GLN without OVT' => [['id_number' => '', 'routing_id' => '0088:ABCDEFGHIJKLM'], 'routing_id'],
            'bare GLN without OVT' => [['id_number' => '', 'routing_id' => '5401205000102'], 'routing_id'],
            'empty explicit OVT' => [['id_number' => '', 'routing_id' => '0216:'], 'routing_id'],
            'extra colon' => [['id_number' => '', 'routing_id' => 'FI:OVT2:' . self::OVT . ':ABC'], 'routing_id'],
            'invalid explicit OVT' => [['id_number' => '', 'routing_id' => '0216:12345678'], 'routing_id'],
            'invalid values in both fields' => [['id_number' => 'INVALID', 'routing_id' => 'INVALID'], 'id_number'],
        ];
    }

    #[DataProvider('invalidRecipientCases')]
    public function testInvalidRecipientCannotUseAnotherRoute(array $extra, string $expected_field): void
    {
        $client = $this->makeFiClient($extra);
        $handler = CountryFactory::make('FI');
        $router = new StorecoveRouter();
        $errors = (new EntityLevel())->checkClient($client)['client'] ?? [];

        $this->assertContains($expected_field, array_column($errors, 'field'));
        $this->assertSame([], $handler->getCandidates($client, 'business', $router));
        $this->assertSame(['scheme' => '', 'id' => ''], $handler->resolveClientEndpointScheme($client, $router));
        $this->assertSame([], $handler->storecoveCustomerPartyPublicIdentifiers($client, $this->invoice, $router));
        $this->assertSame('none', $this->makeResolver($client, true)->resolve()['type']);
        $this->assertFalse($this->makeResolver($client, true)->discoverOnPeppol());
    }

    public function testVatIsOptionalTaxDataAndNeverARoutingIdentifier(): void
    {
        $handler = CountryFactory::make('FI');
        $router = new StorecoveRouter();

        foreach (['', 'FI09824102'] as $vat_number) {
            $client = $this->makeFiClient(['vat_number' => $vat_number]);
            $this->assertSame([], $handler->validateReceiverRoutingIdentifiers($client, 'business', $router));
            $pairs = $handler->storecoveCustomerPartyPublicIdentifiers($client, $this->invoice, $router);
            $this->assertSame(['scheme' => 'FI:OVT2', 'id' => self::OVT], $pairs[0]);
            $this->assertCount($vat_number === '' ? 1 : 2, $pairs);
            if ($vat_number !== '') {
                $this->assertSame(['scheme' => 'FI:VAT', 'id' => $vat_number], $pairs[1]);
            }
            $this->assertSame([
                ['scheme' => 'FI:OVT2', 'id' => self::OVT],
            ], $this->makeResolver($client)->resolve()['meta']['routing']['eIdentifiers']);
        }
    }

    public function testInvoicesAndCreditsUseOvt2ForBusinessAndGovernment(): void
    {
        foreach (['business', 'government'] as $classification) {
            $this->assertTrue(CountryFactory::make('FI')->consumesBareRoutingId($classification));
            $client = $this->makeFiClient(['classification' => $classification]);
            foreach ([false, true] as $is_credit) {
                $result = $this->makeResolver($client, false, $is_credit)->resolve();
                $this->assertSame([
                    ['scheme' => 'FI:OVT2', 'id' => self::OVT],
                ], $result['meta']['routing']['eIdentifiers']);
            }
        }
    }

    public function testIndividualClassificationRemainsUnavailable(): void
    {
        $client = $this->makeFiClient(['classification' => 'individual']);

        $this->assertNotNull($client->checkDeliveryNetwork());
    }
}
