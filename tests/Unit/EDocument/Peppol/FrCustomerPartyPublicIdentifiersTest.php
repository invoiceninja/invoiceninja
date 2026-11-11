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

namespace Tests\Unit\EDocument\Peppol;

use App\Services\EDocument\Gateway\Storecove\StorecoveRouter;
use App\Services\EDocument\Standards\Peppol\FR;
use Tests\TestCase;

class FrCustomerPartyPublicIdentifiersTest extends TestCase
{
    private FR $fr;

    private StorecoveRouter $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fr = new FR();
        $this->router = new StorecoveRouter();
    }

    private function client(?string $idNumber, ?string $vatNumber, string $classification = 'business'): object
    {
        return (object) [
            'country' => (object) ['iso_3166_2' => 'FR'],
            'classification' => $classification,
            'id_number' => $idNumber,
            'vat_number' => $vatNumber,
        ];
    }

    public function testBusinessSiretFromIdNumberResolvesConcreteScheme(): void
    {
        $pairs = $this->fr->storecoveCustomerPartyPublicIdentifiers(
            $this->client('732 829 320 00074', 'FR44732829320'),
            (object) [],
            $this->router,
        );

        $this->assertSame([
            ['scheme' => 'FR:SIRET', 'id' => '73282932000074'],
            ['scheme' => 'FR:VAT', 'id' => 'FR44732829320'],
        ], $pairs);

        foreach ($pairs as $pair) {
            $this->assertStringNotContainsString(' or ', $pair['scheme']);
        }
    }

    public function testBusinessSirenFromIdNumberResolvesConcreteScheme(): void
    {
        $pairs = $this->fr->storecoveCustomerPartyPublicIdentifiers(
            $this->client('732829320', 'FR44732829320'),
            (object) [],
            $this->router,
        );

        $this->assertSame([
            ['scheme' => 'FR:SIRENE', 'id' => '732829320'],
            ['scheme' => 'FR:VAT', 'id' => 'FR44732829320'],
        ], $pairs);
    }

    public function testBusinessFallsBackToSirenFromVatWhenNoIdNumber(): void
    {
        $pairs = $this->fr->storecoveCustomerPartyPublicIdentifiers(
            $this->client(null, 'FR44732829320'),
            (object) [],
            $this->router,
        );

        $this->assertSame([
            ['scheme' => 'FR:SIRENE', 'id' => '732829320'],
            ['scheme' => 'FR:VAT', 'id' => 'FR44732829320'],
        ], $pairs);
    }

    public function testBusinessWithNoUsableIdentifiersReturnsEmpty(): void
    {
        $pairs = $this->fr->storecoveCustomerPartyPublicIdentifiers(
            $this->client(null, null),
            (object) [],
            $this->router,
        );

        $this->assertSame([], $pairs);
    }

    public function testGovernmentNeverLeaksCompoundScheme(): void
    {
        $client = $this->client('73282932000074', 'FR44732829320', 'government');
        $client->routing_id = '73282932000074';

        $pairs = $this->fr->storecoveCustomerPartyPublicIdentifiers($client, (object) [], $this->router);

        foreach ($pairs as $pair) {
            $this->assertStringNotContainsString(' or ', $pair['scheme']);
        }
    }
}
