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

use App\DataMapper\ClientSettings;
use App\Factory\InvoiceItemFactory;
use App\Livewire\PdfSlot;
use App\Models\BaseModel;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Vendor;
use App\Models\VendorContact;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Mockery;
use ReflectionClass;
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
        $this->actingAs($this->contact, 'contact');

        Livewire::test(PdfSlot::class, $this->pdfSlotParameters('invoice', $this->invoice, $invoice_invitation))
            ->call('getPdf')
            ->assertSet('pdf', route('client.invoices.showBlob', [
                'entity_type' => 'invoice',
                'invitation_key' => $invoice_invitation->key,
            ], false));

        $purchase_order_invitation = $this->purchase_order->invitations()->firstOrFail();
        $this->actingAs($purchase_order_invitation->contact, 'vendor');

        Livewire::test(PdfSlot::class, $this->pdfSlotParameters('purchase_order', $this->purchase_order, $purchase_order_invitation))
            ->call('getPdf')
            ->assertSet('pdf', route('vendor.purchase_order.showBlob', [
                'entity_type' => 'purchase_order',
                'invitation_key' => $purchase_order_invitation->key,
            ], false));
    }

    public function testPdfSlotShowsProductTagsAsSeparateLabels(): void
    {
        $settings = $this->company->settings;
        $product_columns = $settings->pdf_variables->product_columns;
        $product_columns[] = '$product.tags';
        $settings->pdf_variables->product_columns = array_values(array_unique($product_columns));
        $this->company->settings = $settings;
        $this->company->save();
        $this->company->refresh();

        $this->assertContains('$product.tags', $this->company->settings->pdf_variables->product_columns);

        $this->client->group_settings_id = null;
        $this->client->settings = ClientSettings::defaults();
        $this->client->save();

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 100;
        $item->line_total = 100;
        $item->notes = 'Tagged product';
        $item->tags = 'Retail,Priority Customer';
        $this->invoice->line_items = [$item];
        $this->invoice->save();

        $invitation = $this->invoice->invitations()->firstOrFail();
        $this->actingAs($this->contact, 'contact');

        Livewire::test(PdfSlot::class, $this->pdfSlotParameters('invoice', $this->invoice, $invitation))
            ->assertSee('Retail')
            ->assertSee('Priority Customer');
    }

    public function testPdfSlotSupportsEveryClientEntityType(): void
    {
        $this->actingAs($this->contact, 'contact');

        foreach ([
            'invoice' => $this->invoice,
            'quote' => $this->quote,
            'credit' => $this->credit,
            'recurring_invoice' => $this->recurring_invoice,
        ] as $entity_type => $entity) {
            $invitation = $entity->invitations()->first();

            $component = Livewire::test(
                PdfSlot::class,
                $this->pdfSlotParameters($entity_type, $entity, $invitation)
            );

            $component->assertSet('entity_type', $entity_type);
            $this->assertNotNull($component->get('invitation_key'));
        }
    }

    public function testPdfSlotUsesTheRequestedInvitation(): void
    {
        $invitations = $this->invoice->invitations()->orderBy('id')->get();
        $this->assertCount(2, $invitations);

        $selected_invitation = $invitations->last();
        $this->actingAs($this->contact, 'contact');

        Livewire::test(
            PdfSlot::class,
            $this->pdfSlotParameters('invoice', $this->invoice, $selected_invitation)
        )->assertSet('invitation_key', $selected_invitation->key);
    }

    public function testPdfSlotSnapshotContainsOnlyLockedOpaqueStateAndUiState(): void
    {
        $invitation = $this->invoice->invitations()->firstOrFail();
        $this->actingAs($this->contact, 'contact');

        $component = Livewire::test(
            PdfSlot::class,
            $this->pdfSlotParameters('invoice', $this->invoice, $invitation)
        );

        $data = $component->getData();

        $this->assertSame([
            'entity_type',
            'entity_key',
            'invitation_key',
            'pdf',
            'with_close_button',
        ], array_keys($data));
        $this->assertSame('invoice', $data['entity_type']);
        $this->assertSame($this->invoice->hashed_id, $data['entity_key']);
        $this->assertSame($invitation->key, $data['invitation_key']);
        $this->assertNotContains($this->invoice->id, $data, true);
        $this->assertNotContains($invitation->id, $data, true);

        $serialized_state = json_encode($data, JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString($this->invoice::class, $serialized_state);
        $this->assertStringNotContainsString($this->company->db, $serialized_state);

        $reflection = new ReflectionClass(PdfSlot::class);

        $this->assertFalse($reflection->hasProperty('entity'));

        foreach (['resolved_entity', 'resolved_invitation', 'settings', 'html_variables', 'preference_product_notes_for_html_view'] as $property_name) {
            $this->assertTrue($reflection->getProperty($property_name)->isPrivate());
        }
    }

    public function testPdfSlotPublicStateCannotBeTamperedWith(): void
    {
        $reflection = new ReflectionClass(PdfSlot::class);

        foreach (['entity_type', 'entity_key', 'invitation_key', 'pdf', 'with_close_button'] as $property_name) {
            $this->assertCount(1, $reflection->getProperty($property_name)->getAttributes(Locked::class));
        }

        $invitation = $this->invoice->invitations()->firstOrFail();
        $this->actingAs($this->contact, 'contact');

        $component = Livewire::test(
            PdfSlot::class,
            $this->pdfSlotParameters('invoice', $this->invoice, $invitation)
        );

        $this->expectException(CannotUpdateLockedPropertyException::class);

        $component->set('entity_key', 'tampered-entity-key');
    }

    public function testPdfSlotRejectsClassNamesAsEntityTypes(): void
    {
        $invitation = $this->invoice->invitations()->firstOrFail();
        $this->actingAs($this->contact, 'contact');

        Livewire::test(PdfSlot::class, [
            ...$this->pdfSlotParameters('invoice', $this->invoice, $invitation),
            'entity_type' => $this->invoice::class,
        ])->assertStatus(404);
    }

    public function testPdfSlotRejectsAnotherClientsEntity(): void
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
        $invitation = $this->invoice->invitations()->firstOrFail();
        $this->actingAs($foreign_contact, 'contact');

        Livewire::test(
            PdfSlot::class,
            $this->pdfSlotParameters('invoice', $this->invoice, $invitation)
        )->assertStatus(403);
    }

    public function testPdfSlotRejectsAnotherVendorsEntity(): void
    {
        $foreign_vendor = Vendor::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
        ]);
        $foreign_contact = VendorContact::factory()->create([
            'user_id' => $this->user->id,
            'vendor_id' => $foreign_vendor->id,
            'company_id' => $this->company->id,
        ]);
        $invitation = $this->purchase_order->invitations()->firstOrFail();
        $this->actingAs($foreign_contact, 'vendor');

        Livewire::test(
            PdfSlot::class,
            $this->pdfSlotParameters('purchase_order', $this->purchase_order, $invitation)
        )->assertStatus(403);
    }

    public function testPdfSlotReauthorizesTheEntityOnEveryLivewireRequest(): void
    {
        $invitation = $this->invoice->invitations()->firstOrFail();
        $this->actingAs($this->contact, 'contact');

        $component = Livewire::test(
            PdfSlot::class,
            $this->pdfSlotParameters('invoice', $this->invoice, $invitation)
        );

        $foreign_client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
        ]);

        $this->invoice->client_id = $foreign_client->id;
        $this->invoice->save();

        $component->call('getPdf')->assertStatus(403);
    }

    public function testPdfSlotResolvesTheEntityOnlyOncePerRender(): void
    {
        $invitation = $this->invoice->invitations()->firstOrFail();
        $this->actingAs($this->contact, 'contact');
        $queries = [];

        DB::listen(static function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        Livewire::test(
            PdfSlot::class,
            $this->pdfSlotParameters('invoice', $this->invoice, $invitation)
        );

        $invoice_queries = array_values(array_filter(
            $queries,
            static fn(string $query): bool => preg_match('/from [`"]invoices[`"]/i', $query) === 1
        ));

        $this->assertCount(1, $invoice_queries, implode(PHP_EOL, $invoice_queries));
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

    private function pdfSlotParameters(string $entity_type, BaseModel $entity, ?BaseModel $invitation): array
    {
        return [
            'entity_type' => $entity_type,
            'entity_key' => $entity->hashed_id,
            'invitation_key' => $invitation?->key,
            'db' => $this->company->db,
        ];
    }
}
