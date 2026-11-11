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

namespace Tests\Feature\ClientPortal;

use App\DataMapper\QuoteSync;
use App\Livewire\Flow2\DocuNinja;
use App\Livewire\Flow2\DocuNinjaLoader;
use App\Livewire\Sign;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\Quote;
use App\Models\QuoteInvitation;
use App\Models\Timezone;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class QuoteApprovalLivewireTest extends TestCase
{
    use DatabaseTransactions;

    private Account $account;

    private Company $company;

    private Client $client;

    private ClientContact $contact;

    private Quote $quote;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = Account::factory()->create([
            'plan' => 'pro',
            'plan_expires' => now()->addMonth(),
        ]);
        $user = User::factory()->create([
            'account_id' => $this->account->id,
            'email' => uniqid('quote-approval') . '@example.test',
        ]);
        $this->company = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);
        $this->client = Client::factory()->create([
            'user_id' => $user->id,
            'company_id' => $this->company->id,
        ]);
        $this->contact = ClientContact::factory()->create([
            'user_id' => $user->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
        ]);
        $this->quote = Quote::factory()->create([
            'user_id' => $user->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'due_date' => now()->addMonth(),
            'status_id' => Quote::STATUS_SENT,
            'sync' => new QuoteSync(),
        ]);
        QuoteInvitation::factory()->create([
            'user_id' => $user->id,
            'company_id' => $this->company->id,
            'client_contact_id' => $this->contact->id,
            'quote_id' => $this->quote->id,
        ]);

        $this->actingAs($this->contact, 'contact');

        $settings = $this->client->settings;
        $settings->auto_convert_quote = false;
        $this->client->settings = $settings;
        $this->client->save();

    }

    public function testDocuNinjaSignatureCompletesThePortalQuoteApprovalFlow(): void
    {
        $invitation = $this->quote->invitations()
            ->where('client_contact_id', $this->contact->id)
            ->firstOrFail();
        $request_hash = Str::random(64);

        Cache::put($request_hash, [
            'client_contact_id' => $this->contact->id,
            'request' => [
                'action' => 'approve',
                'process' => 'true',
                'quotes' => [$this->quote->hashed_id],
            ],
        ], now()->addHour());

        Livewire::test(Sign::class, [
            '_key' => $invitation->key,
            'invitation_id' => $invitation->id,
            'entity_type' => 'quote',
            'db' => $this->company->db,
            'request_hash' => $request_hash,
        ])
            ->assertSet('initializing', false)
            ->assertSet('component', DocuNinjaLoader::class)
            ->call('docuninjaLoaderReady')
            ->assertSet('docu_ninja_ready', true)
            ->assertSet('component', DocuNinja::class)
            ->call('docuNinjaSignatureCaptured')
            ->assertSet('signature_accepted', true)
            ->assertRedirectToRoute('client.quotes.approval.continue', [
                'request_hash' => $request_hash,
            ]);

        $this->assertTrue((bool) $this->quote->fresh()->sync?->dn_completed);
        $this->assertSame(Quote::STATUS_SENT, $this->quote->fresh()->status_id);
        $this->assertTrue(Cache::has($request_hash));

        $this->get(route('client.quotes.approval.continue', $request_hash))
            ->assertOk()
            ->assertSee('method="post"', false);

        $this->assertSame(Quote::STATUS_SENT, $this->quote->fresh()->status_id);

        $this->post(route('client.quotes.bulk'), [
            '_token' => csrf_token(),
            'request_hash' => $request_hash,
        ])->assertRedirect();

        $this->assertSame(Quote::STATUS_APPROVED, $this->quote->fresh()->status_id);
        $this->assertFalse(Cache::has($request_hash));

        Cache::forget($invitation->key);
    }

    public function testStandaloneQuoteSignatureSignalsTheEmbeddingPortalFlow(): void
    {
        $invitation = $this->quote->invitations()
            ->where('client_contact_id', $this->contact->id)
            ->firstOrFail();

        Livewire::test(Sign::class, [
            '_key' => $invitation->key,
            'invitation_id' => $invitation->id,
            'entity_type' => 'quote',
            'db' => $this->company->db,
            'request_hash' => null,
        ])
            ->call('docuNinjaSignatureCaptured')
            ->assertSet('signature_accepted', true)
            ->assertDispatched('quote-signed')
            ->assertNoRedirect();

        $this->assertTrue((bool) $this->quote->fresh()->sync?->dn_completed);

        Cache::forget($invitation->key);
    }

    #[DataProvider('signedQuoteExpiryCases')]
    public function testSignedQuoteApprovalRespectsClientCalendarDate(
        string $serverTimezone,
        string $clientTimezone,
        string $clientNow,
        ?string $dueDate,
        bool $expectedApproved,
    ): void {
        $originalTimezone = date_default_timezone_get();
        $requestHash = Str::random(64);

        try {
            config(['app.timezone' => $serverTimezone]);
            date_default_timezone_set($serverTimezone);
            $this->travelTo(Carbon::parse($clientNow, $clientTimezone));

            $settings = $this->client->settings;
            $settings->timezone_id = Timezone::where('name', $clientTimezone)->firstOrFail()->id;
            $this->client->settings = $settings;
            $this->client->save();

            // Start where DocuNinja leaves the flow: signed, but not yet approved.
            $sync = new QuoteSync();
            $sync->dn_completed = true;
            $this->quote->forceFill(['due_date' => $dueDate, 'sync' => $sync])->saveQuietly();

            Cache::put($requestHash, [
                'client_contact_id' => $this->contact->id,
                'request' => [
                    'action' => 'approve',
                    'process' => 'true',
                    'quotes' => [$this->quote->hashed_id],
                ],
            ], now()->addHour());

            // Exercise the approval endpoint without unrelated portal login middleware.
            $response = $this->withoutMiddleware()->post(route('client.quotes.bulk'), [
                'request_hash' => $requestHash,
            ]);

            $response->assertSessionHasNoErrors();
            $response->assertRedirect($expectedApproved
                ? route('client.quote.show', $this->quote->hashed_id)
                : route('client.quotes.index'));
            $quote = $this->quote->fresh();
            $this->assertSame(
                $expectedApproved ? Quote::STATUS_APPROVED : Quote::STATUS_SENT,
                $quote->getRawOriginal('status_id'),
            );
            $this->assertTrue($quote->sync->dn_completed);
            $this->assertFalse(Cache::has($requestHash));
        } finally {
            Cache::forget($requestHash);
            $this->travelBack();
            date_default_timezone_set($originalTimezone);
        }
    }

    public static function signedQuoteExpiryCases(): iterable
    {
        yield 'Sydney due date at noon, UTC server' => [
            'UTC', 'Australia/Sydney', '2026-09-09 12:00:00', '2026-09-09', true,
        ];
        yield 'Sydney due date at last second, UTC server' => [
            'UTC', 'Australia/Sydney', '2026-09-09 23:59:59', '2026-09-09', true,
        ];
        yield 'Sydney midnight after due date, UTC server' => [
            'UTC', 'Australia/Sydney', '2026-09-10 00:00:00', '2026-09-09', false,
        ];
        yield 'Sydney midnight after due date, Sydney server' => [
            'Australia/Sydney', 'Australia/Sydney', '2026-09-10 00:00:00', '2026-09-09', false,
        ];
        yield 'Los Angeles due date at last second, Sydney server' => [
            'Australia/Sydney', 'America/Los_Angeles', '2026-09-09 23:59:59', '2026-09-09', true,
        ];
        yield 'Los Angeles midnight after due date, UTC server' => [
            'UTC', 'America/Los_Angeles', '2026-09-10 00:00:00', '2026-09-09', false,
        ];
        yield 'Sydney future due date' => [
            'UTC', 'Australia/Sydney', '2026-09-08 12:00:00', '2026-09-09', true,
        ];
        yield 'Sydney no due date' => [
            'UTC', 'Australia/Sydney', '2026-09-10 00:00:00', null, true,
        ];
    }
}
