<?php

namespace Tests\Feature;

use Tests\TestCase;
use Tests\MockAccountData;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Credit;
use App\Models\CreditInvitation;
use App\Models\Invoice;
use App\Models\InvoiceInvitation;
use App\Models\Quote;
use App\Models\QuoteInvitation;
use App\Models\RecurringInvoiceInvitation;
use App\Services\Invoice\CreateInvitations as InvoiceCreateInvitations;
use App\Services\Quote\CreateInvitations as QuoteCreateInvitations;
use App\Services\Credit\CreateInvitations as CreditCreateInvitations;
use App\Services\Recurring\CreateRecurringInvitations;
use App\Services\Email\EmailObject;
use App\Services\Email\Email;
use App\Repositories\ClientContactRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Facades\Queue;

class CcOnlyContactTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Model::reguard();

        $this->makeTestData();
    }

    // ─── cc_contacts() helper ───────────────────────────────────

    public function testCcContactsMethodReturnsAddresses(): void
    {
        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'email' => 'cc1@test.com',
            'cc_only' => true,
            'is_locked' => false,
            'is_primary' => false,
        ]);

        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'email' => 'cc2@test.com',
            'cc_only' => true,
            'is_locked' => false,
            'is_primary' => false,
        ]);

        $addresses = $this->client->cc_contacts();

        $this->assertCount(2, $addresses);
        $this->assertInstanceOf(Address::class, $addresses[0]);

        $emails = array_map(fn ($a) => $a->address, $addresses);
        $this->assertContains('cc1@test.com', $emails);
        $this->assertContains('cc2@test.com', $emails);
    }

    public function testCcContactsExcludesLockedContacts(): void
    {
        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'email' => 'locked@test.com',
            'cc_only' => true,
            'is_locked' => true,
            'is_primary' => false,
        ]);

        $emails = array_map(fn ($a) => $a->address, $this->client->cc_contacts());
        $this->assertNotContains('locked@test.com', $emails);
    }

    public function testCcContactsLimitedToFour(): void
    {
        for ($i = 0; $i < 6; $i++) {
            ClientContact::factory()->create([
                'user_id' => $this->user->id,
                'client_id' => $this->client->id,
                'company_id' => $this->company->id,
                'email' => "cc-limit-{$i}@test.com",
                'cc_only' => true,
                'is_locked' => false,
                'is_primary' => false,
            ]);
        }

        $this->assertCount(4, $this->client->cc_contacts(), 'CC contacts should be capped at 4');
    }

    public function testCcContactsExcludesEmptyEmail(): void
    {
        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'email' => '',
            'cc_only' => true,
            'is_locked' => false,
            'is_primary' => false,
        ]);

        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'email' => null,
            'cc_only' => true,
            'is_locked' => false,
            'is_primary' => false,
        ]);

        $this->assertCount(0, $this->client->cc_contacts());
    }

    public function testCcContactsExcludesNonCcOnlyContacts(): void
    {
        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'email' => 'normal@test.com',
            'cc_only' => false,
            'send_email' => true,
            'is_locked' => false,
            'is_primary' => false,
        ]);

        $emails = array_map(fn ($a) => $a->address, $this->client->cc_contacts());
        $this->assertNotContains('normal@test.com', $emails);
    }

    // ─── Invoice CreateInvitations ──────────────────────────────

    public function testInvoiceCcOnlyContactGetsNoInvitation(): void
    {
        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'email' => 'cc-inv@test.com',
            'send_email' => true,
            'cc_only' => true,
            'is_primary' => false,
        ]);

        $this->invoice->invitations()->delete();

        (new InvoiceCreateInvitations($this->invoice->fresh()))->run();

        $this->assertNull(
            InvoiceInvitation::where('invoice_id', $this->invoice->id)
                ->whereHas('contact', fn ($q) => $q->where('email', 'cc-inv@test.com'))
                ->first()
        );
    }

    public function testInvoiceExistingInvitationDeletedWhenContactBecomesCcOnly(): void
    {
        $contact = ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'email' => 'was-to@test.com',
            'send_email' => true,
            'cc_only' => false,
            'is_primary' => false,
        ]);

        $this->invoice->invitations()->delete();
        (new InvoiceCreateInvitations($this->invoice->fresh()))->run();

        $this->assertNotNull(
            InvoiceInvitation::where('invoice_id', $this->invoice->id)
                ->where('client_contact_id', $contact->id)
                ->first(),
            'Should have invitation before becoming CC-only'
        );

        $contact->update(['cc_only' => true]);

        (new InvoiceCreateInvitations($this->invoice->fresh()))->run();

        $invitation = InvoiceInvitation::withTrashed()
            ->where('invoice_id', $this->invoice->id)
            ->where('client_contact_id', $contact->id)
            ->first();

        $this->assertNotNull($invitation->deleted_at, 'Invitation should be soft-deleted');
    }

    public function testInvoiceRegularContactStillGetsInvitation(): void
    {
        $regular = ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'email' => 'regular@test.com',
            'send_email' => true,
            'cc_only' => false,
            'is_primary' => false,
        ]);

        $cc = ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'email' => 'cc-only@test.com',
            'send_email' => true,
            'cc_only' => true,
            'is_primary' => false,
        ]);

        $this->invoice->invitations()->delete();
        (new InvoiceCreateInvitations($this->invoice->fresh()))->run();

        $this->assertNotNull(
            InvoiceInvitation::where('invoice_id', $this->invoice->id)
                ->where('client_contact_id', $regular->id)
                ->first(),
            'Regular contact should still get invitation'
        );

        $this->assertNull(
            InvoiceInvitation::where('invoice_id', $this->invoice->id)
                ->where('client_contact_id', $cc->id)
                ->first(),
            'CC contact should not get invitation'
        );
    }

    // ─── Quote CreateInvitations ────────────────────────────────

    public function testQuoteCcOnlyContactGetsNoInvitation(): void
    {
        $cc = ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'email' => 'cc-quote@test.com',
            'send_email' => true,
            'cc_only' => true,
            'is_primary' => false,
        ]);

        $this->quote->invitations()->delete();
        (new QuoteCreateInvitations($this->quote->fresh()))->run();

        $this->assertNull(
            QuoteInvitation::where('quote_id', $this->quote->id)
                ->where('client_contact_id', $cc->id)
                ->first()
        );
    }

    public function testQuoteExistingInvitationDeletedWhenContactBecomesCcOnly(): void
    {
        $contact = ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'email' => 'was-to-quote@test.com',
            'send_email' => true,
            'cc_only' => false,
            'is_primary' => false,
        ]);

        $this->quote->invitations()->delete();
        (new QuoteCreateInvitations($this->quote->fresh()))->run();

        $this->assertNotNull(
            QuoteInvitation::where('quote_id', $this->quote->id)
                ->where('client_contact_id', $contact->id)
                ->first()
        );

        $contact->update(['cc_only' => true]);
        (new QuoteCreateInvitations($this->quote->fresh()))->run();

        $invitation = QuoteInvitation::withTrashed()
            ->where('quote_id', $this->quote->id)
            ->where('client_contact_id', $contact->id)
            ->first();

        $this->assertNotNull($invitation->deleted_at);
    }

    // ─── Credit CreateInvitations ───────────────────────────────

    public function testCreditCcOnlyContactGetsNoInvitation(): void
    {
        $cc = ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'email' => 'cc-credit@test.com',
            'send_email' => true,
            'cc_only' => true,
            'is_primary' => false,
        ]);

        $this->credit->invitations()->delete();
        (new CreditCreateInvitations($this->credit->fresh()))->run();

        $this->assertNull(
            CreditInvitation::where('credit_id', $this->credit->id)
                ->where('client_contact_id', $cc->id)
                ->first()
        );
    }

    public function testCreditExistingInvitationDeletedWhenContactBecomesCcOnly(): void
    {
        $contact = ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'email' => 'was-to-credit@test.com',
            'send_email' => true,
            'cc_only' => false,
            'is_primary' => false,
        ]);

        $this->credit->invitations()->delete();
        (new CreditCreateInvitations($this->credit->fresh()))->run();

        $this->assertNotNull(
            CreditInvitation::where('credit_id', $this->credit->id)
                ->where('client_contact_id', $contact->id)
                ->first()
        );

        $contact->update(['cc_only' => true]);
        (new CreditCreateInvitations($this->credit->fresh()))->run();

        $invitation = CreditInvitation::withTrashed()
            ->where('credit_id', $this->credit->id)
            ->where('client_contact_id', $contact->id)
            ->first();

        $this->assertNotNull($invitation->deleted_at);
    }

    // ─── Recurring Invoice CreateInvitations ────────────────────

    public function testRecurringInvoiceCcOnlyContactGetsNoInvitation(): void
    {
        $cc = ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'email' => 'cc-recurring@test.com',
            'send_email' => true,
            'cc_only' => true,
            'is_primary' => false,
        ]);

        $this->recurring_invoice->invitations()->delete();
        (new CreateRecurringInvitations($this->recurring_invoice->fresh()))->run();

        $this->assertNull(
            RecurringInvoiceInvitation::where('recurring_invoice_id', $this->recurring_invoice->id)
                ->where('client_contact_id', $cc->id)
                ->first()
        );
    }

    public function testRecurringInvoiceExistingInvitationDeletedWhenContactBecomesCcOnly(): void
    {
        $contact = ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'email' => 'was-to-recurring@test.com',
            'send_email' => true,
            'cc_only' => false,
            'is_primary' => false,
        ]);

        $this->recurring_invoice->invitations()->delete();
        (new CreateRecurringInvitations($this->recurring_invoice->fresh()))->run();

        $this->assertNotNull(
            RecurringInvoiceInvitation::where('recurring_invoice_id', $this->recurring_invoice->id)
                ->where('client_contact_id', $contact->id)
                ->first()
        );

        $contact->update(['cc_only' => true]);
        (new CreateRecurringInvitations($this->recurring_invoice->fresh()))->run();

        $invitation = RecurringInvoiceInvitation::withTrashed()
            ->where('recurring_invoice_id', $this->recurring_invoice->id)
            ->where('client_contact_id', $contact->id)
            ->first();

        $this->assertNotNull($invitation->deleted_at);
    }

    // ─── Fallback: all contacts cc_only ─────────────────────────

    public function testFallbackCreatesInvitationWhenAllContactsAreCcOnly(): void
    {
        // Make all existing contacts cc_only
        $this->client->contacts()->update(['cc_only' => true, 'send_email' => true]);

        $this->invoice->invitations()->delete();
        $invoice = (new InvoiceCreateInvitations($this->invoice->fresh()))->run();

        $this->assertGreaterThan(
            0,
            $invoice->invitations()->count(),
            'Fallback should create at least one invitation even when all contacts are CC-only'
        );
    }

    // ─── Email dispatch: CC addresses populated ─────────────────

    public function testEmailDispatchIncludesCcAddresses(): void
    {
        Queue::fake();

        // Ensure isPremium() passes on hosted CI (needs isHosted + isNinja + paid + non-trial + >2 months old)
        config(['ninja.environment' => 'hosted', 'ninja.production' => true]);
        $this->account->plan = 'pro';
        $this->account->plan_paid = now()->subMonths(3);
        $this->account->plan_expires = now()->addMonth();
        $this->account->created_at = now()->subMonths(3);
        $this->account->save();
        $this->company->load('account');

        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'email' => 'cc-dispatch@test.com',
            'cc_only' => true,
            'is_locked' => false,
            'is_primary' => false,
        ]);

        $mo = new EmailObject();
        $mo->entity_id = $this->invoice->id;
        $mo->entity_class = Invoice::class;
        $mo->invitation_id = $this->invoice->invitations->first()->id;
        $mo->client_id = $this->client->id;
        $mo->email_template_body = 'email_template_invoice';
        $mo->email_template_subject = 'email_subject_invoice';

        // Manually run the Email job handle to test EmailDefaults::setCc()
        $email = new Email($mo, $this->company);
        $email->handle();

        $cc_emails = array_map(fn ($a) => $a->address, $mo->cc);

        $this->assertContains('cc-dispatch@test.com', $cc_emails, 'CC-only contact should be in the CC list');
    }

    // ─── API layer ──────────────────────────────────────────────

    public function testCcOnlyExposedInApiResponse(): void
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->getJson('/api/v1/clients/' . $this->client->hashed_id . '?include=contacts');

        $response->assertStatus(200);

        $contacts = $response->json('data.contacts');
        $this->assertNotEmpty($contacts);

        foreach ($contacts as $contact) {
            $this->assertArrayHasKey('cc_only', $contact);
            $this->assertIsBool($contact['cc_only']);
        }
    }

    public function testCcOnlyCanBeSetViaClientCreate(): void
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/clients', [
            'name' => 'CC Test Client',
            'contacts' => [
                [
                    'email' => 'primary-new@test.com',
                    'send_email' => true,
                    'cc_only' => false,
                    'is_primary' => true,
                ],
                [
                    'email' => 'cc-new@test.com',
                    'send_email' => true,
                    'cc_only' => true,
                    'is_primary' => false,
                ],
            ],
        ]);

        $response->assertStatus(200);

        $contacts = $response->json('data.contacts');
        $primary = collect($contacts)->firstWhere('email', 'primary-new@test.com');
        $cc = collect($contacts)->firstWhere('email', 'cc-new@test.com');

        $this->assertFalse($primary['cc_only']);
        $this->assertTrue($cc['cc_only']);
    }

    public function testCcOnlyCanBeUpdatedViaApi(): void
    {
        $secondary = ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'email' => 'secondary-api@test.com',
            'cc_only' => false,
            'is_primary' => false,
        ]);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/clients/' . $this->client->hashed_id, [
            'contacts' => [
                [
                    'id' => $this->contact->hashed_id,
                    'email' => $this->contact->email,
                    'is_primary' => true,
                ],
                [
                    'id' => $secondary->hashed_id,
                    'email' => $secondary->email,
                    'cc_only' => true,
                ],
            ],
        ]);

        $response->assertStatus(200);

        $updated = collect($response->json('data.contacts'))
            ->firstWhere('id', $secondary->hashed_id);

        $this->assertTrue($updated['cc_only']);
    }

    public function testCcOnlyCannotBeSetOnPrimaryContactViaApi(): void
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/clients/' . $this->client->hashed_id, [
            'contacts' => [
                [
                    'id' => $this->contact->hashed_id,
                    'email' => $this->contact->email,
                    'cc_only' => true,
                ],
            ],
        ]);

        $response->assertStatus(200);

        $updated = collect($response->json('data.contacts'))
            ->firstWhere('id', $this->contact->hashed_id);

        $this->assertFalse($updated['cc_only'], 'Primary contact cc_only must always be forced to false');
    }

    public function testCcOnlyDefaultsToFalse(): void
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/clients', [
            'name' => 'Default Test Client',
            'contacts' => [
                [
                    'email' => 'default-cc@test.com',
                    'is_primary' => true,
                ],
            ],
        ]);

        $response->assertStatus(200);

        $contact = collect($response->json('data.contacts'))
            ->firstWhere('email', 'default-cc@test.com');

        $this->assertFalse($contact['cc_only'], 'cc_only should default to false');
    }

    // ─── Primary contact cc_only protection ──────────────────────

    public function testPrimaryContactCcOnlyAlwaysForcedFalse(): void
    {
        $repo = new ClientContactRepository();

        $repo->save([
            'contacts' => [
                [
                    'email' => 'primary@test.com',
                    'is_primary' => true,
                    'cc_only' => true,
                ],
                [
                    'email' => 'secondary@test.com',
                    'is_primary' => false,
                    'cc_only' => true,
                ],
            ],
        ], $this->client);

        $primary = $this->client->contacts()->where('email', 'primary@test.com')->first();
        $secondary = $this->client->contacts()->where('email', 'secondary@test.com')->first();

        $this->assertTrue((bool) $primary->is_primary);
        $this->assertFalse((bool) $primary->cc_only, 'Primary contact must never have cc_only enabled');
        $this->assertTrue((bool) $secondary->cc_only, 'Secondary contact should retain cc_only');
    }

    // ─── First-invitation CC logic ───────────────────────────────

    public function testCcOnlyContactsAttachedToFirstInvitationOnly(): void
    {
        Queue::fake();

        // Ensure isPremium() passes
        config(['ninja.environment' => 'hosted', 'ninja.production' => true]);
        $this->account->plan = 'pro';
        $this->account->plan_paid = now()->subMonths(3);
        $this->account->plan_expires = now()->addMonth();
        $this->account->created_at = now()->subMonths(3);
        $this->account->save();
        $this->company->load('account');

        // Ensure primary contact has send_email
        $this->contact->update(['send_email' => true, 'cc_only' => false]);

        // Create a second send_email contact
        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'email' => 'contact2@test.com',
            'send_email' => true,
            'cc_only' => false,
            'is_primary' => false,
        ]);

        // Create a cc_only contact
        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'email' => 'cc-first-only@test.com',
            'cc_only' => true,
            'is_locked' => false,
            'is_primary' => false,
        ]);

        // Rebuild invitations so both send_email contacts get one
        $this->invoice->invitations()->forceDelete();
        (new InvoiceCreateInvitations($this->invoice->fresh()))->run();
        $this->invoice->refresh();

        $this->assertGreaterThanOrEqual(2, $this->invoice->invitations->count());

        $firstInvitation = $this->invoice->invitations()->orderBy('id')->first();
        $secondInvitation = $this->invoice->invitations()->orderBy('id')->skip(1)->first();

        $this->assertNotNull($firstInvitation);
        $this->assertNotNull($secondInvitation);

        // Build EmailObjects for each invitation (simulating the Email service path)
        $mo1 = new EmailObject();
        $mo1->entity_id = $this->invoice->id;
        $mo1->entity_class = Invoice::class;
        $mo1->invitation_id = $firstInvitation->id;
        $mo1->client_id = $this->client->id;
        $mo1->email_template_body = 'email_template_invoice';
        $mo1->email_template_subject = 'email_subject_invoice';

        $email1 = new Email($mo1, $this->company);
        $email1->handle();

        $mo2 = new EmailObject();
        $mo2->entity_id = $this->invoice->id;
        $mo2->entity_class = Invoice::class;
        $mo2->invitation_id = $secondInvitation->id;
        $mo2->client_id = $this->client->id;
        $mo2->email_template_body = 'email_template_invoice';
        $mo2->email_template_subject = 'email_subject_invoice';

        $email2 = new Email($mo2, $this->company);
        $email2->handle();

        $cc1 = array_map(fn ($a) => $a->address, $mo1->cc);
        $cc2 = array_map(fn ($a) => $a->address, $mo2->cc);

        $this->assertContains('cc-first-only@test.com', $cc1, 'First invitation should include cc_only contact');
        $this->assertNotContains('cc-first-only@test.com', $cc2, 'Second invitation should NOT include cc_only contact');
    }

    // ─── Entity update: invitation removed for cc_only contact ──

    public function testEntityUpdateRemovesInvitationForCcOnlyContact(): void
    {
        // Create a fresh invoice with a clean slate
        $invoice = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
        ]);

        $contact_to = ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'email' => 'stays-to@test.com',
            'send_email' => true,
            'cc_only' => false,
            'is_primary' => false,
        ]);

        $contact_cc = ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'email' => 'will-be-cc@test.com',
            'send_email' => true,
            'cc_only' => false,
            'is_primary' => false,
        ]);

        // Create invitations — both contacts should get one
        (new InvoiceCreateInvitations($invoice->fresh()))->run();

        $this->assertNotNull(
            InvoiceInvitation::where('invoice_id', $invoice->id)
                ->where('client_contact_id', $contact_to->id)
                ->first(),
            'TO contact should have invitation'
        );

        $this->assertNotNull(
            InvoiceInvitation::where('invoice_id', $invoice->id)
                ->where('client_contact_id', $contact_cc->id)
                ->first(),
            'Future CC contact should have invitation while still TO'
        );

        // Now flip the second contact to cc_only
        $contact_cc->update(['cc_only' => true]);

        // Re-run createInvitations (simulates what happens on next entity send)
        (new InvoiceCreateInvitations($invoice->fresh()))->run();

        // TO contact still has invitation
        $this->assertNotNull(
            InvoiceInvitation::where('invoice_id', $invoice->id)
                ->where('client_contact_id', $contact_to->id)
                ->first(),
            'TO contact should still have invitation'
        );

        // CC contact invitation was soft-deleted
        $this->assertNull(
            InvoiceInvitation::where('invoice_id', $invoice->id)
                ->where('client_contact_id', $contact_cc->id)
                ->first(),
            'CC-only contact invitation should be deleted'
        );
    }
}
