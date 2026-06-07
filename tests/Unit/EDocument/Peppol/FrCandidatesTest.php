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

class FrCandidatesTest extends TestCase
{
    private FR $fr;

    private StorecoveRouter $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fr = new FR();
        $this->router = new StorecoveRouter();
    }

    private function client(?string $idNumber, ?string $vatNumber): object
    {
        return (object) [
            'id_number' => $idNumber,
            'vat_number' => $vatNumber,
        ];
    }

    public function testSirenFromIdNumber(): void
    {
        $this->assertSame(
            [['scheme' => 'FR:SIRENE', 'id' => '732829320']],
            $this->fr->getCandidates($this->client('732829320', null), 'business', $this->router),
        );
    }

    public function testSiretFromIdNumber(): void
    {
        $this->assertSame(
            [['scheme' => 'FR:SIRET', 'id' => '73282932000074']],
            $this->fr->getCandidates($this->client('732 829 320 00074', 'FR44732829320'), 'business', $this->router),
        );
    }

    public function testFallsBackToSirenFromVatWhenNoIdNumber(): void
    {
        $this->assertSame(
            [['scheme' => 'FR:SIRENE', 'id' => '732829320']],
            $this->fr->getCandidates($this->client(null, 'FR44732829320'), 'business', $this->router),
        );
    }

    public function testNeitherIdNumberNorVatReturnsEmpty(): void
    {
        $this->assertSame(
            [],
            $this->fr->getCandidates($this->client(null, null), 'business', $this->router),
        );
    }

    public function testGovernmentRoutesToChorusPro(): void
    {
        $this->assertSame(
            [['scheme' => '0009', 'id' => '11000201100044']],
            $this->fr->getCandidates($this->client(null, null), 'government', $this->router),
        );
    }
}
