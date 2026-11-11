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

namespace Tests\Feature\Scheduler;

use App\Models\Company;
use App\Models\Credit;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * Proves the store-scheduler entity_id check:
 * an entity is accepted only when it exists on the authenticated company,
 * and requests that do not send an entity still store.
 */
class StoreSchedulerEntityValidationTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Session::start();
        Model::reguard();

        $this->makeTestData();

        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function testSchedulerWithoutEntityStillStores(): void
    {
        $response = $this->postScheduler([
            'name' => 'Statement without an entity',
            'frequency_id' => 5,
            'next_run' => now()->addDays(2)->format('Y-m-d'),
            'template' => 'client_statement',
            'parameters' => [],
        ]);

        $response->assertStatus(200);
    }

    public function testEmailRecordAcceptsInvoiceFromTheSameCompany(): void
    {
        $response = $this->postEmailRecord('invoice', $this->invoice->hashed_id);

        $response->assertStatus(200);
    }

    public function testEmailRecordRejectsInvoiceFromAnotherCompany(): void
    {
        $otherCompany = Company::factory()->create([
            'account_id' => $this->company->account_id,
        ]);

        $foreignInvoice = Invoice::factory()->create([
            'company_id' => $otherCompany->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'amount' => 50,
            'balance' => 50,
            'status_id' => Invoice::STATUS_SENT,
        ]);

        $response = $this->postEmailRecord('invoice', $foreignInvoice->hashed_id);

        $response->assertStatus(422);
        $response->assertJsonFragment(['Invalid Entity ID Selected']);
    }

    public function testEmailRecordRejectsCreditIdThatDoesNotExist(): void
    {
        // Hashes do not encode entity type, so invoice and credit IDs can overlap.
        $missingCreditId = (int) Credit::withTrashed()->max('id') + 1;
        $this->assertDatabaseMissing('credits', ['id' => $missingCreditId]);

        $response = $this->postEmailRecord('credit', $this->encodePrimaryKey($missingCreditId));

        $response->assertStatus(422);
        $response->assertJsonFragment(['Invalid Entity ID Selected']);
    }

    public function testEmailRecordAcceptsCreditFromTheSameCompany(): void
    {
        $response = $this->postEmailRecord('credit', $this->credit->hashed_id);

        $response->assertStatus(200);
    }

    public function testEmailRecordAcceptsQuoteFromTheSameCompany(): void
    {
        $response = $this->postEmailRecord('quote', $this->quote->hashed_id);

        $response->assertStatus(200);
    }

    public function testEmailRecordAcceptsPurchaseOrderFromTheSameCompany(): void
    {
        $response = $this->postEmailRecord('purchase_order', $this->purchase_order->hashed_id);

        $response->assertStatus(200);
    }

    public function testEmailRecordRejectsEntityWhenEntityIdIsMissing(): void
    {
        $response = $this->postScheduler([
            'name' => 'Email record missing entity id',
            'frequency_id' => 0,
            'next_run' => now()->addDay()->format('Y-m-d'),
            'template' => 'email_record',
            'parameters' => [
                'entity' => 'invoice',
            ],
        ]);

        $response->assertStatus(422);
    }

    public function testEmailRecordRejectsAnUndecodableEntityId(): void
    {
        $response = $this->postEmailRecord('invoice', 'not-a-real-id');

        $response->assertStatus(422);
        $response->assertJsonFragment(['Invalid Entity ID Selected']);
    }

    private function postEmailRecord(string $entity, string $entityId)
    {
        return $this->postScheduler([
            'name' => 'Email record '.$entity,
            'frequency_id' => 0,
            'next_run' => now()->addDay()->format('Y-m-d'),
            'template' => 'email_record',
            'parameters' => [
                'entity' => $entity,
                'entity_id' => $entityId,
            ],
        ]);
    }

    private function postScheduler(array $data)
    {
        return $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/task_schedulers', $data);
    }
}
