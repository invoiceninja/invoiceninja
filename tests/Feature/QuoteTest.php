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

namespace Tests\Feature;

use App\DataMapper\ClientSettings;
use App\Events\Quote\QuoteWasCancelled;
use App\Exceptions\QuoteConversion;
use App\Listeners\Quote\QuoteCancelledActivity;
use App\Models\Activity;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Project;
use App\Models\Quote;
use App\Utils\HtmlEngine;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Event;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 *
 *  App\Http\Controllers\QuoteController
 */
class QuoteTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;
    protected function setUp(): void
    {
        parent::setUp();

        Session::start();
        Model::reguard();

        $this->makeTestData();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

    }

    public function testHasLapsedValidUntilIsInclusiveOfDueDate()
    {
        $quote = $this->makeDraftQuote(now()->format('Y-m-d'));

        $this->assertFalse($quote->hasLapsedValidUntil());
        $this->assertFalse($quote->hasLapsedValidUntil(now()->format('Y-m-d')));
        $this->assertTrue($quote->hasLapsedValidUntil(now()->subDays(7)->format('Y-m-d')));
        $this->assertFalse($quote->hasLapsedValidUntil(''));
    }

    public function testSentQuoteWithDueDateTodayIsNotExpired()
    {
        $quote = $this->makeDraftQuote(now()->format('Y-m-d'), Quote::STATUS_SENT);

        $this->assertEquals(Quote::STATUS_SENT, $quote->status_id);
        $this->assertEquals(Quote::STATUS_SENT, $quote->getRawOriginal('status_id'));
    }

    public function testSentQuoteWithPastDueDateIsExpired()
    {
        $quote = $this->makeDraftQuote(now()->subDays(7)->format('Y-m-d'), Quote::STATUS_SENT);

        $this->assertEquals(Quote::STATUS_EXPIRED, $quote->status_id);
        $this->assertEquals(Quote::STATUS_SENT, $quote->getRawOriginal('status_id'));
    }

    public function testStoreRejectsExpiredDueDate()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/quotes', [
            'client_id' => $this->client->hashed_id,
            'date' => now()->subDays(10)->format('Y-m-d'),
            'due_date' => now()->subDays(7)->format('Y-m-d'),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['due_date']);
    }

    public function testStoreAcceptsDueDateOfToday()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/quotes', [
            'client_id' => $this->client->hashed_id,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->format('Y-m-d'),
        ]);

        $response->assertStatus(200);
        $this->assertEquals(now()->format('Y-m-d'), $response->json('data.due_date'));
    }

    public function testStoreRejectsExpiredDueDateWithMarkSent()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/quotes?mark_sent=true', [
            'client_id' => $this->client->hashed_id,
            'date' => now()->subDays(10)->format('Y-m-d'),
            'due_date' => now()->subDays(7)->format('Y-m-d'),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['due_date']);
    }

    public function testUpdateRejectsExpiredDueDate()
    {
        $quote = $this->makeDraftQuote(now()->addDays(7)->format('Y-m-d'));

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/quotes/'.$quote->hashed_id, [
            'due_date' => now()->subDays(7)->format('Y-m-d'),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['due_date']);
    }

    public function testUpdateAllowsOtherFieldsWhenExpiredDueDateUnchanged()
    {
        $due_date = now()->subDays(7)->format('Y-m-d');
        $quote = $this->makeDraftQuote($due_date, Quote::STATUS_SENT);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/quotes/'.$quote->hashed_id, [
            'due_date' => $due_date,
            'terms' => 'updated terms',
            'public_notes' => 'updated notes',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('updated terms', $response->json('data.terms'));
        $this->assertEquals('updated notes', $response->json('data.public_notes'));
        $this->assertEquals($due_date, $response->json('data.due_date'));
    }

    public function testUpdateRejectsChangingDueDateToExpired()
    {
        $quote = $this->makeDraftQuote(now()->addDays(7)->format('Y-m-d'));

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/quotes/'.$quote->hashed_id, [
            'due_date' => now()->subDays(7)->format('Y-m-d'),
            'terms' => 'should not persist',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['due_date']);

        $quote->refresh();
        $this->assertNotEquals('should not persist', $quote->terms);
        $this->assertEquals(now()->addDays(7)->format('Y-m-d'), $quote->due_date->format('Y-m-d'));
    }

    public function testUpdateAllowsOtherFieldsWhenExpiredDueDateOmitted()
    {
        $due_date = now()->subDays(7)->format('Y-m-d');
        $quote = $this->makeDraftQuote($due_date, Quote::STATUS_SENT);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/quotes/'.$quote->hashed_id, [
            'terms' => 'omitted due date terms',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('omitted due date terms', $response->json('data.terms'));
        $this->assertEquals($due_date, $response->json('data.due_date'));
    }

    public function testUpdateAllowsOtherFieldsOnDraftWithExpiredDueDate()
    {
        $due_date = now()->subDays(7)->format('Y-m-d');
        $quote = $this->makeDraftQuote($due_date);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/quotes/'.$quote->hashed_id, [
            'due_date' => $due_date,
            'private_notes' => 'draft notes',
            'footer' => 'draft footer',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('draft notes', $response->json('data.private_notes'));
        $this->assertEquals('draft footer', $response->json('data.footer'));
        $this->assertEquals($due_date, $response->json('data.due_date'));
        $this->assertEquals(Quote::STATUS_DRAFT, $quote->fresh()->getRawOriginal('status_id'));
    }

    public function testUpdateRejectsChangingExpiredDueDateToAnotherExpiredDate()
    {
        $due_date = now()->setTimezone($this->client->timezone()->name)->subDays(7)->format('Y-m-d');
        $quote = $this->makeDraftQuote($due_date, Quote::STATUS_SENT);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/quotes/'.$quote->hashed_id, [
            'due_date' => now()->setTimezone($this->client->timezone()->name)->subDays(1)->format('Y-m-d'),
            'terms' => 'should not persist',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['due_date']);

        $quote->refresh();
        $this->assertNotEquals('should not persist', $quote->terms);
        $this->assertEquals($due_date, $quote->due_date->format('Y-m-d'));
    }

    public function testUpdateAllowsExtendingExpiredDueDateToToday()
    {
        $quote = $this->makeDraftQuote(now()->subDays(7)->format('Y-m-d'), Quote::STATUS_SENT);
        $today = now()->format('Y-m-d');

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/quotes/'.$quote->hashed_id, [
            'due_date' => $today,
            'terms' => 'extended today',
        ]);

        $response->assertStatus(200);
        $this->assertEquals($today, $response->json('data.due_date'));
        $this->assertEquals('extended today', $response->json('data.terms'));
    }

    public function testUpdateAllowsExtendingExpiredDueDateToFuture()
    {
        $quote = $this->makeDraftQuote(now()->subDays(7)->format('Y-m-d'), Quote::STATUS_SENT);
        $future = now()->addDays(14)->format('Y-m-d');

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/quotes/'.$quote->hashed_id, [
            'due_date' => $future,
            'terms' => 'extended future',
        ]);

        $response->assertStatus(200);
        $this->assertEquals($future, $response->json('data.due_date'));
        $this->assertEquals('extended future', $response->json('data.terms'));
    }

    public function testUpdateAllowsUnchangedFutureDueDateWithOtherFields()
    {
        $due_date = now()->addDays(7)->format('Y-m-d');
        $quote = $this->makeDraftQuote($due_date);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/quotes/'.$quote->hashed_id, [
            'due_date' => $due_date,
            'terms' => 'still valid',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('still valid', $response->json('data.terms'));
        $this->assertEquals($due_date, $response->json('data.due_date'));
    }

    public function testUpdateAllowsChangingDueDateToToday()
    {
        $quote = $this->makeDraftQuote(now()->addDays(7)->format('Y-m-d'));
        $today = now()->format('Y-m-d');

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/quotes/'.$quote->hashed_id, [
            'due_date' => $today,
        ]);

        $response->assertStatus(200);
        $this->assertEquals($today, $response->json('data.due_date'));
    }

    public function testUpdateAllowsClearingDueDateOnExpiredQuote()
    {
        $quote = $this->makeDraftQuote(now()->subDays(7)->format('Y-m-d'), Quote::STATUS_SENT);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/quotes/'.$quote->hashed_id, [
            'due_date' => null,
            'terms' => 'cleared due date',
        ]);

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data.due_date'));
        $this->assertEquals('cleared due date', $response->json('data.terms'));
    }

    public function testUpdateWithoutDueDateDoesNotRequireClientId()
    {
        $quote = $this->makeDraftQuote(now()->addDays(7)->format('Y-m-d'));

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/quotes/'.$quote->hashed_id, [
            'public_notes' => 'partial update',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('partial update', $response->json('data.public_notes'));
    }

    public function testPutMarkSentRejectsExpiredQuote()
    {
        $due_date = now()->subDays(7)->format('Y-m-d');
        $quote = $this->makeDraftQuote($due_date);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/quotes/'.$quote->hashed_id.'?mark_sent=true', [
            'client_id' => $this->client->hashed_id,
            'due_date' => $due_date,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['due_date']);

        $this->assertEquals(Quote::STATUS_DRAFT, $quote->fresh()->getRawOriginal('status_id'));
    }

    public function testPutMarkSentRejectsStoredExpiredDueDateWhenOmitted()
    {
        $quote = $this->makeDraftQuote(now()->subDays(7)->format('Y-m-d'));

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/quotes/'.$quote->hashed_id.'?mark_sent=true', [
            'public_notes' => 'mark sent only',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['due_date']);

        $this->assertEquals(Quote::STATUS_DRAFT, $quote->fresh()->getRawOriginal('status_id'));
    }

    public function testPutMarkSentTodayGeneratesNumberWhenSent()
    {
        $this->setCounterNumberApplied('when_sent');

        $quote = $this->makeDraftQuote(now()->format('Y-m-d'));

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/quotes/'.$quote->hashed_id.'?mark_sent=true', [
            'due_date' => now()->format('Y-m-d'),
        ]);

        $response->assertStatus(200);
        $this->assertEquals(Quote::STATUS_SENT, $response->json('data.status_id'));
        $this->assertNotEmpty($response->json('data.number'));
    }

    public function testPutSendEmailRejectsExpiredQuote()
    {
        $due_date = now()->subDays(7)->format('Y-m-d');
        $quote = $this->makeDraftQuote($due_date);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/quotes/'.$quote->hashed_id.'?send_email=true', [
            'due_date' => $due_date,
            'terms' => 'should not persist',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['due_date']);

        $this->assertEquals(Quote::STATUS_DRAFT, $quote->fresh()->getRawOriginal('status_id'));
        $this->assertNotEquals('should not persist', $quote->fresh()->terms);
    }

    public function testPutEmailRejectsExpiredQuote()
    {
        $due_date = now()->subDays(7)->format('Y-m-d');
        $quote = $this->makeDraftQuote($due_date, Quote::STATUS_SENT);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/quotes/'.$quote->hashed_id.'?email=true', [
            'due_date' => $due_date,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['due_date']);
    }

    public function testPutMarkSentSucceedsAfterExtendingExpiredDueDate()
    {
        $quote = $this->makeDraftQuote(now()->subDays(7)->format('Y-m-d'));
        $today = now()->format('Y-m-d');

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/quotes/'.$quote->hashed_id.'?mark_sent=true', [
            'due_date' => $today,
        ]);

        $response->assertStatus(200);
        $this->assertEquals(Quote::STATUS_SENT, $response->json('data.status_id'));
        $this->assertEquals($today, $response->json('data.due_date'));
    }

    public function testEmailsEndpointRejectsExpiredQuote()
    {
        $quote = $this->makeDraftQuote(now()->subDays(7)->format('Y-m-d'), Quote::STATUS_SENT);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/emails', [
            'entity' => 'quote',
            'entity_id' => $quote->hashed_id,
            'template' => 'email_template_quote',
        ]);

        $response->assertStatus(422);
        $this->assertEquals(Quote::STATUS_SENT, $quote->fresh()->getRawOriginal('status_id'));
    }

    public function testBulkSendEmailRejectsExpiredQuote()
    {
        $quote = $this->makeDraftQuote(now()->subDays(7)->format('Y-m-d'));

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/quotes/bulk', [
            'action' => 'send_email',
            'ids' => [$quote->hashed_id],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ids']);

        $this->assertEquals(Quote::STATUS_DRAFT, $quote->fresh()->getRawOriginal('status_id'));
    }

    public function testBulkMarkSentRejectsExpiredQuote()
    {
        $quote = $this->makeDraftQuote(now()->subDays(7)->format('Y-m-d'));

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/quotes/bulk', [
            'action' => 'mark_sent',
            'ids' => [$quote->hashed_id],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ids']);

        $this->assertEquals(Quote::STATUS_DRAFT, $quote->fresh()->getRawOriginal('status_id'));
    }

    public function testBulkEmailRejectsExpiredQuote()
    {
        $quote = $this->makeDraftQuote(now()->subDays(7)->format('Y-m-d'));

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/quotes/bulk', [
            'action' => 'email',
            'ids' => [$quote->hashed_id],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ids']);
    }

    public function testBulkMarkSentTodayGeneratesNumberWhenSent()
    {
        $this->setCounterNumberApplied('when_sent');

        $quote = $this->makeDraftQuote(now()->format('Y-m-d'));

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/quotes/bulk', [
            'action' => 'mark_sent',
            'ids' => [$quote->hashed_id],
        ]);

        $response->assertStatus(200);
        $this->assertEquals(Quote::STATUS_SENT, $response->json('data.0.status_id'));
        $this->assertNotEmpty($response->json('data.0.number'));
    }

    private function makeDraftQuote(string $due_date, int $status_id = Quote::STATUS_DRAFT): Quote
    {
        return Quote::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'status_id' => $status_id,
            'number' => $status_id === Quote::STATUS_DRAFT ? null : 'QT-'.uniqid(),
            'date' => now()->subDays(10)->format('Y-m-d'),
            'due_date' => $due_date,
        ]);
    }

    private function setCounterNumberApplied(string $value): void
    {
        $settings = $this->company->settings;
        $settings->counter_number_applied = $value;
        $this->company->settings = $settings;
        $this->company->save();
    }

    public function testBulkConvertSucceedsForSentQuote()
    {
        $quote = $this->makeDraftQuote(now()->addDays(7)->format('Y-m-d'), Quote::STATUS_SENT);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/quotes/bulk', [
            'action' => 'convert',
            'ids' => [$quote->hashed_id],
        ]);

        $response->assertStatus(200);
        $this->assertNotEmpty($response->json('data.0.invoice_id'));
    }

    public function testBulkConvertToInvoiceSucceedsForSentQuote()
    {
        $quote = $this->makeDraftQuote(now()->format('Y-m-d'), Quote::STATUS_SENT);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/quotes/bulk', [
            'action' => 'convert_to_invoice',
            'ids' => [$quote->hashed_id],
        ]);

        $response->assertStatus(200);
        $this->assertNotEmpty($response->json('data.0.invoice_id'));
    }

    public function testBulkConvertRejectsExpiredQuote()
    {
        $quote = $this->makeDraftQuote(now()->subDays(7)->format('Y-m-d'), Quote::STATUS_SENT);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/quotes/bulk', [
            'action' => 'convert',
            'ids' => [$quote->hashed_id],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['action']);
        $this->assertNull($quote->fresh()->invoice_id);
    }

    public function testBulkConvertRejectsAlreadyConvertedQuote()
    {
        $quote = $this->makeDraftQuote(now()->addDays(7)->format('Y-m-d'), Quote::STATUS_SENT);
        $quote->service()->convert()->save();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/quotes/bulk', [
            'action' => 'convert_to_invoice',
            'ids' => [$quote->hashed_id],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['action']);
    }

    public function testQuoteDueDateInjectionValidationLayer()
    {

        $data = [
            'client_id' => $this->client->hashed_id,
            'partial_due_date' => now()->format('Y-m-d'),
            'partial' => 1,
            'amount' => 20,
        ];

        $response = $this->withHeaders([
                    'X-API-SECRET' => config('ninja.api_secret'),
                    'X-API-TOKEN' => $this->token,
                ])->postJson('/api/v1/quotes', $data);

        $arr = $response->json();
        // nlog($arr);

        $this->assertNotEmpty($arr['data']['due_date']);

    }

    public function testNullDueDates()
    {

        $data = [
            'client_id' => $this->client->hashed_id,
            'due_date' => '',
        ];

        $response = $this->withHeaders([
                    'X-API-SECRET' => config('ninja.api_secret'),
                    'X-API-TOKEN' => $this->token,
                ])->postJson('/api/v1/quotes', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEmpty($arr['data']['due_date']);

        $response = $this->withHeaders([
                            'X-API-SECRET' => config('ninja.api_secret'),
                            'X-API-TOKEN' => $this->token,
                        ])->putJson('/api/v1/quotes/'.$arr['data']['id'], $arr['data']);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEmpty($arr['data']['due_date']);

    }


    public function testNonNullDueDates()
    {

        $data = [
            'client_id' => $this->client->hashed_id,
            'due_date' => now()->addDays(10),
        ];

        $response = $this->withHeaders([
                    'X-API-SECRET' => config('ninja.api_secret'),
                    'X-API-TOKEN' => $this->token,
                ])->postJson('/api/v1/quotes', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertNotEmpty($arr['data']['due_date']);

        $response = $this->withHeaders([
                            'X-API-SECRET' => config('ninja.api_secret'),
                            'X-API-TOKEN' => $this->token,
                        ])->putJson('/api/v1/quotes/'.$arr['data']['id'], $arr['data']);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertNotEmpty($arr['data']['due_date']);

    }

    public function testPartialDueDates()
    {

        $data = [
            'client_id' => $this->client->hashed_id,
            'due_date' => now()->addDay()->format('Y-m-d'),
        ];

        $response = $this->withHeaders([
                    'X-API-SECRET' => config('ninja.api_secret'),
                    'X-API-TOKEN' => $this->token,
                ])->postJson('/api/v1/quotes', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertNotNull($arr['data']['due_date']);
        $this->assertEmpty($arr['data']['partial_due_date']);

        $data = [
            'client_id' => $this->client->hashed_id,
            'due_date' => now()->addDay()->format('Y-m-d'),
            'partial' => 1,
            'partial_due_date' => now()->format('Y-m-d'),
            'amount' => 20,
        ];

        $response = $this->withHeaders([
                    'X-API-SECRET' => config('ninja.api_secret'),
                    'X-API-TOKEN' => $this->token,
                ])->postJson('/api/v1/quotes', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals(now()->addDay()->format('Y-m-d'), $arr['data']['due_date']);
        $this->assertEquals(now()->format('Y-m-d'), $arr['data']['partial_due_date']);
        $this->assertEquals(1, $arr['data']['partial']);

        $response = $this->withHeaders([
                    'X-API-SECRET' => config('ninja.api_secret'),
                    'X-API-TOKEN' => $this->token,
                ])->putJson('/api/v1/quotes/'.$arr['data']['id'], $arr['data']);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals(now()->addDay()->format('Y-m-d'), $arr['data']['due_date']);
        $this->assertEquals(now()->format('Y-m-d'), $arr['data']['partial_due_date']);
        $this->assertEquals(1, $arr['data']['partial']);

    }

    public function testQuoteToProjectConversion2()
    {
        $settings = ClientSettings::defaults();
        $settings->default_task_rate = 41;

        $c = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'settings' => $settings,
        ]);

        $q = Quote::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $c->id,
            'status_id' => 2,
            'date' => now(),
            'line_items' => [
                [
                    'type_id' => '2',
                    'cost' => 200,
                    'quantity' => 2,
                    'notes' => 'Test200',
                ],
                [
                    'type_id' => '2',
                    'cost' => 100,
                    'quantity' => 1,
                    'notes' => 'Test100',
                ],
                [
                    'type_id' => '1',
                    'cost' => 10,
                    'quantity' => 1,
                    'notes' => 'Test',
                ],

            ],
        ]);

        $q->calc()->getQuote();
        $q->fresh();

        $p = $q->service()->convertToProject();

        $this->assertEquals(3, $p->budgeted_hours);
        $this->assertEquals(2, $p->tasks()->count());

        $t = $p->tasks()->where('description', 'Test200')->first();

        $this->assertEquals(200, $t->rate);

        $t = $p->tasks()->where('description', 'Test100')->first();

        $this->assertEquals(100, $t->rate);


    }

    public function testQuoteToProjectConversion()
    {
        $project = $this->quote->service()->convertToProject();

        $this->assertInstanceOf('\App\Models\Project', $project);
    }

    public function testQuoteConversion()
    {
        $invoice = $this->quote->service()->convertToInvoice();

        $this->assertInstanceOf('\App\Models\Invoice', $invoice);

        $this->expectException(QuoteConversion::class);

        $invoice = $this->quote->service()->convertToInvoice();

    }

    public function testQuoteDownloadPDF()
    {
        $i = $this->quote->invitations->first();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get("/api/v1/quote/{$i->key}/download");

        $response->assertStatus(200);
        $this->assertTrue($response->headers->get('content-type') == 'application/pdf');
    }

    public function testQuoteListApproved()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/quotes?client_status=approved');

        $response->assertStatus(200);
    }


    public function testQuoteConvertToProject()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/quotes/bulk', ['action' => 'convert_to_project', 'ids' => [$this->quote->hashed_id]]);

        $response->assertStatus(200);

        $res = $response->json();

        $this->assertNotNull($res['data'][0]['project_id']);

        $project = Project::find($this->decodePrimaryKey($res['data'][0]['project_id']));

        $this->assertEquals($project->name, ctrans('texts.quote_number_short') . " " . $this->quote->number." [{$this->quote->client->present()->name()}]");
    }

    public function testQuoteList()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/quotes');

        $response->assertStatus(200);
    }

    public function testQuoteRESTEndPoints()
    {
        $response = null;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->get('/api/v1/quotes/'.$this->encodePrimaryKey($this->quote->id));
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
        }

        if ($response) {
            $response->assertStatus(200);
        }

        $this->assertNotNull($response);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/quotes/'.$this->encodePrimaryKey($this->quote->id).'/edit');

        $response->assertStatus(200);

        $quote_update = [
            'status_id' => Quote::STATUS_APPROVED,
            'client_id' => $this->encodePrimaryKey($this->quote->client_id),
            'number'    => 'Rando',
        ];

        $this->assertNotNull($this->quote);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/quotes/'.$this->encodePrimaryKey($this->quote->id), $quote_update);

        $response->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/quotes/'.$this->encodePrimaryKey($this->quote->id), $quote_update);

        $response->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/quotes/', $quote_update);

        $response->assertStatus(302);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->delete('/api/v1/quotes/'.$this->encodePrimaryKey($this->quote->id));

        $response->assertStatus(200);

        $client_contact = ClientContact::whereClientId($this->client->id)->first();

        $data = [
            'client_id' => $this->encodePrimaryKey($this->client->id),
            'date' => '2019-12-14',
            'line_items' => [],
            'invitations' => [
                ['client_contact_id' => $this->encodePrimaryKey($client_contact->id)],
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/quotes', $data);

        $response->assertStatus(200);
    }

    public function testQuoteTermsPreserveViewUrlTemplateHref(): void
    {
        config([
            'app.url' => 'https://ninja.test',
            'ninja.app_url' => 'https://ninja.test',
        ]);

        $terms = '<p><a href="$view_url">View quote online</a></p>';

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/quotes/'.$this->encodePrimaryKey($this->quote->id), [
            'client_id' => $this->encodePrimaryKey($this->quote->client_id),
            'terms' => $terms,
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('data.terms', $terms);

        $quote = $this->quote->fresh();

        $this->assertSame($terms, $quote->terms);

        $invitation = $quote->invitations()->firstOrFail();
        $variables = (new HtmlEngine($invitation))->generateLabelsAndValues();
        $renderedTerms = $quote->parseHtmlVariables('terms', $variables);

        $this->assertStringContainsString('href="'.$invitation->getLink().'"', $renderedTerms);
        $this->assertStringNotContainsString('$view_url', $renderedTerms);
    }

    public function testSentQuoteCanBeCancelledAndRecordsActivity(): void
    {
        Event::fake([QuoteWasCancelled::class]);

        $quote = $this->makeDraftQuote(now()->addDays(7)->format('Y-m-d'), Quote::STATUS_SENT);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->getJson('/api/v1/quotes/' . $quote->hashed_id . '/cancel');

        $response->assertStatus(200)
            ->assertJsonPath('data.status_id', (string) Quote::STATUS_CANCELLED);

        $this->assertSame(Quote::STATUS_CANCELLED, $quote->fresh()->status_id);
        Event::assertDispatched(QuoteWasCancelled::class);

        $cancelled_quote = $quote->fresh();
        app(QuoteCancelledActivity::class)->handle(new QuoteWasCancelled(
            $cancelled_quote,
            $cancelled_quote->company,
            Ninja::eventVars($this->user->id),
        ));

        $this->assertDatabaseHas('activities', [
            'quote_id' => $quote->id,
            'client_id' => $quote->client_id,
            'user_id' => $this->user->id,
            'activity_type_id' => Activity::CANCELLED_QUOTE,
        ]);
    }

    public function testOnlySentQuotesCanBeCancelled(): void
    {
        $statuses = [
            Quote::STATUS_DRAFT,
            Quote::STATUS_APPROVED,
            Quote::STATUS_CONVERTED,
            Quote::STATUS_REJECTED,
            Quote::STATUS_CANCELLED,
        ];

        foreach ($statuses as $status) {
            $quote = $this->makeDraftQuote(now()->addDays(7)->format('Y-m-d'), $status);

            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->getJson('/api/v1/quotes/' . $quote->hashed_id . '/cancel');

            $response->assertStatus(422)->assertJsonValidationErrors(['action']);
            $this->assertSame($status, $quote->fresh()->status_id);
        }

        $expired = $this->makeDraftQuote(
            now()->setTimezone($this->client->timezone()->name)->subDays(7)->format('Y-m-d'),
            Quote::STATUS_SENT,
        );

        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->getJson('/api/v1/quotes/' . $expired->hashed_id . '/cancel')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['action']);

        $this->assertSame(Quote::STATUS_SENT, $expired->fresh()->getRawOriginal('status_id'));
    }

    public function testBulkCancelRequiresEveryQuoteToBeSent(): void
    {
        $sent = $this->makeDraftQuote(now()->addDays(7)->format('Y-m-d'), Quote::STATUS_SENT);
        $draft = $this->makeDraftQuote(now()->addDays(7)->format('Y-m-d'));

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/quotes/bulk', [
            'action' => 'cancel',
            'ids' => [$sent->hashed_id, $draft->hashed_id],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['ids']);
        $this->assertSame(Quote::STATUS_SENT, $sent->fresh()->status_id);
        $this->assertSame(Quote::STATUS_DRAFT, $draft->fresh()->status_id);
    }

    public function testCancelledQuotesCannotBeConvertedOrReminded(): void
    {
        $quote = $this->makeDraftQuote(now()->addDays(7)->format('Y-m-d'), Quote::STATUS_CANCELLED);

        $this->assertFalse($quote->service()->isConvertable());
        $this->assertFalse($quote->canRemind());
        $this->assertSame('Cancelled', Quote::stringStatus(Quote::STATUS_CANCELLED));
        $this->assertStringContainsString('Cancelled', Quote::badgeForStatus(Quote::STATUS_CANCELLED));
    }
}
