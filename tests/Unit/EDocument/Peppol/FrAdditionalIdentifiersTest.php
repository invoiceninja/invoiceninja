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

use App\Services\EDocument\Standards\Peppol\FR;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class FrAdditionalIdentifiersTest extends TestCase
{
    private FR $fr;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fr = new FR();
    }

    public function testSirenIsInferredFromVatNumberWhenLuhnValid(): void
    {
        // SIREN 732829320 (Danone) — Luhn valid
        $result = $this->fr->getAdditionalIdentifiers([
            'classification' => 'business',
            'vat_number' => 'FR44732829320',
        ]);

        $this->assertSame(
            [
                ['identifier' => '732829320', 'scheme' => 'FR:SIRENE', 'required' => true],
                ['identifier' => '732829320', 'scheme' => 'FR:CTC', 'required' => true],
            ],
            $result,
        );
    }

    public function testValidSiretFromIdNumberIsAdded(): void
    {
        $result = $this->fr->getAdditionalIdentifiers([
            'classification' => 'business',
            'vat_number' => 'FR44732829320',
            'id_number' => '732 829 320 00074', // Luhn-valid SIRET
        ]);

        $this->assertSame(
            [
                ['identifier' => '732829320', 'scheme' => 'FR:SIRENE', 'required' => true],
                ['identifier' => '732829320', 'scheme' => 'FR:CTC', 'required' => true],
                ['identifier' => '73282932000074', 'scheme' => 'FR:SIRET', 'required' => false],
            ],
            $result,
        );
    }

    public function testCheckdigitInvalidSiretIsNotSubmitted(): void
    {
        // 12345678900038 is structurally 14 digits but fails the Luhn check
        $result = $this->fr->getAdditionalIdentifiers([
            'classification' => 'business',
            'vat_number' => 'FR44732829320',
            'id_number' => '12345678900038',
        ]);

        $this->assertSame(
            [
                ['identifier' => '732829320', 'scheme' => 'FR:SIRENE', 'required' => true],
                ['identifier' => '732829320', 'scheme' => 'FR:CTC', 'required' => true],
            ],
            $result,
        );
    }

    public function testCheckdigitInvalidSirenDerivedFromVatIsNotSubmitted(): void
    {
        // VAT trailing 9 digits = 123456789, which fails the SIREN Luhn check
        $result = $this->fr->getAdditionalIdentifiers([
            'classification' => 'business',
            'vat_number' => 'FR00123456789',
        ]);

        $this->assertSame([], $result);
    }

    public function testIndividualReceivesNoAdditionalIdentifier(): void
    {
        $result = $this->fr->getAdditionalIdentifiers([
            'classification' => 'individual',
            'vat_number' => 'FR44732829320',
            'id_number' => '73282932000074',
        ]);

        $this->assertSame([], $result);
    }

    public function testMissingVatAndIdNumberReturnsEmpty(): void
    {
        $this->assertSame([], $this->fr->getAdditionalIdentifiers(['classification' => 'business']));
    }

    public function testFrenchIdentifiersUseTheRequiredStorecoveNetworks(): void
    {
        CarbonImmutable::setTestNow('2026-08-03 23:30:00 Europe/Paris');

        $this->assertSame([
            [
                'name' => 'peppol',
                'sub_networks' => ['main', 'france'],
            ],
        ], $this->fr->getIdentifierNetworkSpecifications('FR:SIRENE'));

        $this->assertSame([
            [
                'name' => 'peppol',
                'sub_networks' => ['main', 'france'],
            ],
            [
                'name' => 'dgfip',
                'sub_networks' => ['main'],
                'annuaire' => [
                    'start_date' => '2026-09-01',
                ],
            ],
        ], $this->fr->getIdentifierNetworkSpecifications('FR:CTC'));

        CarbonImmutable::setTestNow('2026-09-02 23:30:00 Europe/Paris');

        $this->assertSame(
            '2026-09-04',
            $this->fr->getIdentifierNetworkSpecifications('FR:CTC')[1]['annuaire']['start_date'],
        );

        CarbonImmutable::setTestNow();
    }
}
