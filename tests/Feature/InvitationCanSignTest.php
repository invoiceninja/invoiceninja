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

namespace Tests\Feature;

use App\Jobs\RecurringInvoice\SendRecurring;
use App\Models\ClientContact;
use App\Models\Invoice;
use App\Models\QuoteInvitation;
use App\Models\VendorContact;
use App\Repositories\InvoiceRepository;
use App\Repositories\PurchaseOrderRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * `can_sign` marks the invitations DocuNinja asks to sign. Three paths lost it:
 *
 *  - an invitation submitted without a `key` whose contact already had a
 *    (trashed) invitation was restored with its old `can_sign`, ignoring the
 *    request's value;
 *  - a quote converted to an invoice dropped every quote invitation's flag,
 *    after which the save's fallback made the primary contact the signer;
 *  - an invoice generated from a recurring invoice dropped every flag too,
 *    after which the portal made whoever opened it first the signer.
 */
class InvitationCanSignTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        config(['ninja.environment' => 'hosted']);

        $this->account->plan = 'enterprise';
        $this->account->plan_term = 'month';
        $this->account->plan_started = now()->subMonth();
        $this->account->plan_paid = now()->subMonth();
        $this->account->plan_expires = now()->addYear();
        $this->account->saveQuietly();

        $this->company->enable_modules = true;
        $this->company->saveQuietly();
        $this->company->refresh();

        $this->assertTrue($this->company->docuninjaActive());
    }

    private function secondClientContact(): ClientContact
    {
        return ClientContact::query()
            ->where('client_id', $this->client->id)
            ->where('is_primary', false)
            ->firstOrFail();
    }

    public function test_a_key_less_invitation_restored_takes_the_requests_can_sign(): void
    {
        $primary = $this->client->contacts()->where('is_primary', true)->firstOrFail();
        $second = $this->secondClientContact();

        $repo = new InvoiceRepository();
        $invoice = $repo->save([
            'client_id' => $this->client->id,
            'invitations' => [
                ['client_contact_id' => $primary->id, 'can_sign' => true],
                ['client_contact_id' => $second->id, 'can_sign' => false],
            ],
        ], \App\Factory\InvoiceFactory::create($this->company->id, $this->user->id));

        $this->assertTrue((bool) $invoice->invitations()->where('client_contact_id', $primary->id)->first()->can_sign);

        // The same contacts again, without keys: the server soft-deletes and
        // restores each invitation. The request moves the signer to the second
        // contact, and the restore must apply it.
        $invoice = $repo->save([
            'invitations' => [
                ['client_contact_id' => $primary->id, 'can_sign' => false],
                ['client_contact_id' => $second->id, 'can_sign' => true],
            ],
        ], $invoice->fresh());

        $invitations = $invoice->fresh()->invitations;
        $this->assertCount(2, $invitations);
        $this->assertFalse((bool) $invitations->firstWhere('client_contact_id', $primary->id)->can_sign);
        $this->assertTrue((bool) $invitations->firstWhere('client_contact_id', $second->id)->can_sign);
    }

    public function test_a_key_less_invitation_without_can_sign_keeps_its_stored_value(): void
    {
        $second = $this->secondClientContact();
        $repo = new InvoiceRepository();
        $invoice = $repo->save([
            'client_id' => $this->client->id,
            'invitations' => [
                ['client_contact_id' => $second->id, 'can_sign' => true],
            ],
        ], \App\Factory\InvoiceFactory::create($this->company->id, $this->user->id));

        $invoice = $repo->save([
            'invitations' => [
                ['client_contact_id' => $second->id],
            ],
        ], $invoice->fresh());

        $this->assertTrue((bool) $invoice->fresh()->invitations->first()->can_sign);
    }

    public function test_a_purchase_order_restore_takes_the_requests_can_sign(): void
    {
        $contacts = VendorContact::query()->where('vendor_id', $this->vendor->id)->orderBy('id')->get();
        $this->assertCount(2, $contacts);
        [$primary, $second] = [$contacts[0], $contacts[1]];

        $repo = new PurchaseOrderRepository();
        $po = $repo->save([
            'vendor_id' => $this->vendor->id,
            'invitations' => [
                ['vendor_contact_id' => $primary->id, 'can_sign' => true],
                ['vendor_contact_id' => $second->id, 'can_sign' => false],
            ],
        ], \App\Factory\PurchaseOrderFactory::create($this->company->id, $this->user->id));

        $po = $repo->save([
            'invitations' => [
                ['vendor_contact_id' => $primary->id, 'can_sign' => false],
                ['vendor_contact_id' => $second->id, 'can_sign' => true],
            ],
        ], $po->fresh());

        $invitations = $po->fresh()->invitations;
        $this->assertFalse((bool) $invitations->firstWhere('vendor_contact_id', $primary->id)->can_sign);
        $this->assertTrue((bool) $invitations->firstWhere('vendor_contact_id', $second->id)->can_sign);
    }

    public function test_converting_a_quote_keeps_each_invitations_can_sign(): void
    {
        $second = $this->secondClientContact();
        $quote = $this->quote;
        $quote->invitations()->forceDelete();

        foreach ($this->client->contacts as $contact) {
            QuoteInvitation::factory()->create([
                'user_id' => $this->user->id,
                'company_id' => $this->company->id,
                'client_contact_id' => $contact->id,
                'quote_id' => $quote->id,
                'can_sign' => $contact->id === $second->id,
            ]);
        }

        $invoice = $quote->fresh()->service()->convertToInvoice();

        $invitations = Invoice::find($invoice->id)->invitations;
        $this->assertTrue((bool) $invitations->firstWhere('client_contact_id', $second->id)->can_sign);
        $this->assertSame(1, $invitations->where('can_sign', true)->count());
    }

    public function test_an_invoice_generated_from_a_recurring_invoice_keeps_can_sign(): void
    {
        $second = $this->secondClientContact();
        $recurring = $this->recurring_invoice;

        $recurring->invitations()->forceDelete();
        foreach ($this->client->contacts as $contact) {
            $ri = \App\Factory\RecurringInvoiceInvitationFactory::create($this->company->id, $this->user->id);
            $ri->recurring_invoice_id = $recurring->id;
            $ri->client_contact_id = $contact->id;
            $ri->key = \Illuminate\Support\Str::random(32);
            $ri->can_sign = $contact->id === $second->id;
            $ri->save();
        }

        $invoice = \App\Factory\RecurringInvoiceToInvoiceFactory::create($recurring->fresh(), $this->client);
        $invoice->save();

        $job = new SendRecurring($recurring->fresh(), $this->company->db);
        $method = new \ReflectionMethod($job, 'createRecurringInvitations');
        $invoice = $method->invoke($job, $invoice);

        $invitations = $invoice->invitations;
        $this->assertTrue((bool) $invitations->firstWhere('client_contact_id', $second->id)->can_sign);
        $this->assertSame(1, $invitations->where('can_sign', true)->count());
    }

    public function test_a_restore_ignores_can_sign_when_docuninja_is_off(): void
    {
        $this->company->enable_modules = false;
        $this->company->saveQuietly();
        $this->company->refresh();
        $this->assertFalse($this->company->docuninjaActive());

        $second = $this->secondClientContact();
        $repo = new InvoiceRepository();
        $invoice = $repo->save([
            'client_id' => $this->client->id,
            'invitations' => [
                ['client_contact_id' => $second->id],
            ],
        ], \App\Factory\InvoiceFactory::create($this->company->id, $this->user->id));
        $stored = (bool) $invoice->fresh()->invitations->first()->can_sign;

        $invoice = $repo->save([
            'invitations' => [
                ['client_contact_id' => $second->id, 'can_sign' => ! $stored],
            ],
        ], $invoice->fresh());

        $this->assertSame($stored, (bool) $invoice->fresh()->invitations->first()->can_sign);
    }

    public function test_a_purchase_order_restore_without_can_sign_keeps_its_stored_value(): void
    {
        $second = VendorContact::query()->where('vendor_id', $this->vendor->id)->orderBy('id')->get()[1];

        $repo = new PurchaseOrderRepository();
        $po = $repo->save([
            'vendor_id' => $this->vendor->id,
            'invitations' => [
                ['vendor_contact_id' => $second->id, 'can_sign' => true],
            ],
        ], \App\Factory\PurchaseOrderFactory::create($this->company->id, $this->user->id));

        $po = $repo->save([
            'invitations' => [
                ['vendor_contact_id' => $second->id],
            ],
        ], $po->fresh());

        $this->assertTrue((bool) $po->fresh()->invitations->first()->can_sign);
    }
}
