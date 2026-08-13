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
use Tests\MockAccountData;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class ValidateCreditEntityTest extends TestCase
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

    public function testValidateEntityAcceptsCreditsForNonPeppol(): void
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/einvoice/validateEntity', [
            'entity' => 'credits',
            'entity_id' => $this->credit->hashed_id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'passes' => true,
                'credits' => [],
            ]);
    }

    public function testValidateEntityCreditsDoesNotTypeErrorForVerifactu(): void
    {
        $settings = $this->company->settings;
        $settings->e_invoice_type = 'VERIFACTU';
        $this->company->settings = $settings;
        $this->company->save();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/einvoice/validateEntity', [
            'entity' => 'credits',
            'entity_id' => $this->credit->hashed_id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'passes' => true,
            ]);
    }

    public function testValidateEntityRejectsUnknownEntity(): void
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/einvoice/validateEntity', [
            'entity' => 'quotes',
            'entity_id' => $this->credit->hashed_id,
        ]);

        $response->assertStatus(401);
    }
}
