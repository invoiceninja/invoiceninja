<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Integration;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use JsonSchema\Exception\ValidationException;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers App\Http\ValidationRules\Invoice\LockedInvoiceRule
 */
class CheckLockedInvoiceValidationTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testValidationWorksForLockedInvoiceWhenOff()
    {
        $invoice_update = [
            'po_number' => 'test',
        ];

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->put('/api/v1/invoices/'.$this->encodePrimaryKey($this->invoice->id), $invoice_update)
                ->assertStatus(200);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);

            $this->assertNotNull($message);
            \Log::error($message);
        }
    }

    public function testValidationFailsForLockedInvoiceWhenSent()
    {
        $this->company->settings->lock_invoices = 'when_sent';
        $this->company->save();

        $settings = $this->client->settings;
        $settings->lock_invoices = 'when_sent';
        $this->client->settings = $settings;
        $this->client->save();

        $this->invoice = $this->invoice->service()->markSent()->save();

        $invoice_update = [
            'po_number' => 'test',
        ];

        $this->assertEquals($this->invoice->status_id, \App\Models\Invoice::STATUS_SENT);

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->put('/api/v1/invoices/'.$this->encodePrimaryKey($this->invoice->id), $invoice_update);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);

            $this->assertNotNull($message);
            \Log::error($message);
        }

        if ($response) {
            $response->assertStatus(302);
        }
    }

    public function testValidationFailsForLockedInvoiceWhenPaid()
    {
        $this->company->settings->lock_invoices = 'when_paid';
        $this->company->save();

        $settings = $this->client->settings;
        $settings->lock_invoices = 'when_paid';
        $this->client->settings = $settings;
        $this->client->save();

        $this->invoice = $this->invoice->service()->markPaid()->save();

        $invoice_update = [
            'po_number' => 'test',
        ];

        $this->assertEquals($this->invoice->status_id, \App\Models\Invoice::STATUS_PAID);

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->put('/api/v1/invoices/'.$this->encodePrimaryKey($this->invoice->id), $invoice_update);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);

            $this->assertNotNull($message);
            \Log::error($message);
        }

        if ($response) {
            $response->assertStatus(302);
        }
    }
}
