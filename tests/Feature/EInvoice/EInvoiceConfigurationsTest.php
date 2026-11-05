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

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\MockAccountData;
use Tests\TestCase;

class EInvoiceConfigurationsTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );
    }

    public function testConfigurationsDecodesExistingInvoiceEInvoiceData(): void
    {
        $this->invoice->e_invoice = [
            'Invoice' => [
                'Note' => 'existing-peppol-note',
            ],
        ];
        $this->invoice->save();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/einvoice/configurations', [
            'entity' => 'invoice',
            'entity_id' => $this->invoice->hashed_id,
            'payment_means' => [[
                'code' => '30',
                'iban' => '123456789101112254',
                'bic_swift' => 'DEUTDEFF',
                'account_holder' => 'John Doe Company Limited',
            ]],
        ]);

        $response->assertSuccessful();

        $this->invoice->refresh();

        $this->assertSame('existing-peppol-note', $this->invoice->e_invoice->Invoice->Note);
        $this->assertCount(1, $this->invoice->e_invoice->Invoice->PaymentMeans);
        $this->assertSame('30', $this->invoice->e_invoice->Invoice->PaymentMeans[0]->PaymentMeansCode->value);
    }

    public function testConfigurationsUsesEmptyPeppolInvoiceWhenInvoiceHasNoEInvoice(): void
    {
        $this->invoice->e_invoice = null;
        $this->invoice->save();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/einvoice/configurations', [
            'entity' => 'invoice',
            'entity_id' => $this->invoice->hashed_id,
            'payment_means' => [[
                'code' => '30',
                'iban' => '123456789101112254',
                'bic_swift' => 'DEUTDEFF',
                'account_holder' => 'John Doe Company Limited',
            ]],
        ]);

        $response->assertSuccessful();

        $this->invoice->refresh();

        $this->assertObjectNotHasProperty('Note', $this->invoice->e_invoice->Invoice);
        $this->assertCount(1, $this->invoice->e_invoice->Invoice->PaymentMeans);
    }

    public function testConfigurationsUsesEmptyPeppolInvoiceForCompanyEntity(): void
    {
        $this->company->e_invoice = null;
        $this->company->save();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/einvoice/configurations', [
            'entity' => 'company',
            'payment_means' => [[
                'code' => '30',
                'iban' => '123456789101112254',
                'bic_swift' => 'DEUTDEFF',
                'account_holder' => 'John Doe Company Limited',
            ]],
        ]);

        $response->assertSuccessful();

        $this->company->refresh();

        $this->assertCount(1, $this->company->e_invoice->Invoice->PaymentMeans);
        $this->assertSame('30', $this->company->e_invoice->Invoice->PaymentMeans[0]->PaymentMeansCode->value);
    }
}
