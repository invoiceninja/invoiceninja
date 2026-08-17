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

use App\Livewire\PdfSlot;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Vendor;
use App\Models\VendorContact;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Mockery;
use Tests\MockAccountData;
use Tests\TestCase;

class PdfPreviewRouteTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testPdfSlotBuildsStableAuthenticatedPreviewUrls(): void
    {
        $invoice_invitation = $this->invoice->invitations()->firstOrFail();

        Livewire::test(PdfSlot::class, [
            'class' => $this->invoice::class,
            'entity_id' => $this->invoice->id,
            'invitation_id' => $invoice_invitation->id,
            'db' => $this->company->db,
        ])
            ->call('getPdf')
            ->assertSet('pdf', route('client.invoices.showBlob', [
                'entity_type' => 'invoice',
                'invitation_key' => $invoice_invitation->key,
            ], false));

        $purchase_order_invitation = $this->purchase_order->invitations()->firstOrFail();

        Livewire::test(PdfSlot::class, [
            'class' => $this->purchase_order::class,
            'entity_id' => $this->purchase_order->id,
            'invitation_id' => $purchase_order_invitation->id,
            'db' => $this->company->db,
        ])
            ->call('getPdf')
            ->assertSet('pdf', route('vendor.purchase_order.showBlob', [
                'entity_type' => 'purchase_order',
                'invitation_key' => $purchase_order_invitation->key,
            ], false));
    }

    public function testPdfPreviewRoutesRejectAnotherClientOrVendor(): void
    {
        $foreign_client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
        ]);
        $foreign_contact = ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $foreign_client->id,
            'company_id' => $this->company->id,
        ]);
        $invoice_invitation = $this->invoice->invitations()->firstOrFail();

        $this->actingAs($foreign_contact, 'contact')
            ->get(route('client.invoices.showBlob', [
                'entity_type' => 'invoice',
                'invitation_key' => $invoice_invitation->key,
            ], false))
            ->assertNotFound();

        $foreign_vendor = Vendor::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
        ]);
        $foreign_vendor_contact = VendorContact::factory()->create([
            'user_id' => $this->user->id,
            'vendor_id' => $foreign_vendor->id,
            'company_id' => $this->company->id,
        ]);
        $purchase_order_invitation = $this->purchase_order->invitations()->firstOrFail();

        $this->actingAs($foreign_vendor_contact, 'vendor')
            ->get(route('vendor.purchase_order.showBlob', [
                'entity_type' => 'purchase_order',
                'invitation_key' => $purchase_order_invitation->key,
            ], false))
            ->assertNotFound();
    }

    public function testClientCanRequestTheSamePreviewUrlRepeatedly(): void
    {
        $pdf = '%PDF-1.7 test-pdf';
        $generator = Mockery::mock('overload:App\Jobs\Entity\CreateRawPdf');
        $generator->shouldReceive('handle')->andReturn($pdf);

        $invitation = $this->invoice->invitations()->firstOrFail();
        $url = route('client.invoices.showBlob', [
            'entity_type' => 'invoice',
            'invitation_key' => $invitation->key,
        ], false);

        $this->actingAs($this->contact, 'contact');

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->get($url)
                ->assertOk()
                ->assertHeader('Content-Type', 'application/pdf')
                ->assertHeader('Content-Disposition', 'inline')
                ->assertContent($pdf);
        }
    }
}
