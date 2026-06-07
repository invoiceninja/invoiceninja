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

use App\Services\EDocument\Gateway\Storecove\Storecove;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StorecoveCheckNetworkStatusTest extends TestCase
{
    private Storecove $storecove;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storecove = new Storecove();
    }

    public function testFrBusinessUsesConcreteVatSchemeNotCompoundRoutingScheme(): void
    {
        Http::fake([
            '*discovery/exists*' => Http::response(['code' => 'NotFound'], 200),
        ]);

        $this->storecove->checkNetworkStatus([
            'country' => 'FR',
            'classification' => 'business',
            'vat_number' => 'FR44732829320',
        ]);

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            $this->assertSame('FR:VAT', $body['scheme']);
            $this->assertStringNotContainsString(' or ', $body['scheme']);

            return true;
        });
    }

    public function testEmptyTaxSchemeSkipsDiscoveryEntirely(): void
    {
        Http::fake();

        // FR government has no tax identifier (config column 2 is false)
        $result = $this->storecove->checkNetworkStatus([
            'country' => 'FR',
            'classification' => 'government',
            'vat_number' => 'FR44732829320',
        ]);

        $this->assertFalse($result);
        Http::assertNothingSent();
    }

    public function testExistsSplitsCompoundSchemeAndSucceedsOnSecondAtomicScheme(): void
    {
        Http::fake([
            '*discovery/exists*' => function ($request) {
                $body = json_decode($request->body(), true);

                return $body['scheme'] === 'FR:SIRET'
                    ? Http::response(['code' => 'OK'], 200)
                    : Http::response(['code' => 'NotFound'], 200);
            },
        ]);

        $this->assertTrue(
            $this->storecove->exists('73282932000074', 'FR:SIRENE or FR:SIRET')
        );
    }
}
