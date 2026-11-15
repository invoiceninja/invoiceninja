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

use Tests\TestCase;
use Livewire\Livewire;
use Tests\MockAccountData;
use App\DataMapper\InvoiceSync;
use App\DataMapper\QuoteSync;
use App\DataMapper\PurchaseOrderSync;
use App\Repositories\InvoiceRepository;
use App\Repositories\QuoteRepository;
use App\Repositories\PurchaseOrderRepository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Event;
use App\Livewire\Flow2\DocuNinjaLoader;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use InvoiceNinja\AdminApi\Observers\InvoiceObserver;
use InvoiceNinja\AdminApi\Observers\QuoteObserver;
use InvoiceNinja\AdminApi\Observers\PurchaseOrderObserver;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Regression tests for the DocuNinja signable failure path.
 *
 * Production incident (Sentry 2026-08-14): signable_entity threw a TypeError for a
 * company that was never provisioned in DocuNinja. Signable::get() flattened the
 * non-2xx response into ['success' => false, ...empty ids], which the loader read as
 * "already completed" — dispatching docuninja-signature-captured, setting
 * dn_completed = true and advancing the client to payment without a signature.
 *
 * The fix guards on the presence of a usable payload before anything is persisted
 * or dispatched. `success: false` now only ever means "already completed".
 */
class DocuNinjaLoaderTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        foreach ([$this->company, $this->client->group_settings] as $settings_entity) {
            $settings = $settings_entity->settings;
            $settings->company_logo = '';
            $settings_entity->settings = $settings;
            $settings_entity->saveQuietly();
        }

        Http::preventStrayRequests();

        config([
            'ninja.docuninja_api_url' => 'https://api.docuninja.test',
            'ninja.pdf_generator' => 'simulator',
            'ninja.invoiceninja_hosted_pdf_generation' => false,
            'ninja.phantomjs_pdf_generation' => false,
        ]);
    }

    /**
     * Seeds the Cache-backed secure context the component reads in mount().
     */
    private function bootContext(string $entity_type = 'invoice'): string
    {
        $key = \Illuminate\Support\Str::uuid()->toString();

        Cache::put($key, [
            'db' => $this->company->db,
            'entity_type' => $entity_type,
        ], now()->addHour());

        return $key;
    }

    private function signingInvitation(): \App\Models\InvoiceInvitation
    {
        $invitation = $this->invoice->invitations->first();
        $invitation->can_sign = true;
        $invitation->saveQuietly();

        return $invitation;
    }

    private function skipWithoutAdminApi(): void
    {
        if (! class_exists(\InvoiceNinja\AdminApi\Services\DocuNinja\DocuNinja::class)) {
            $this->markTestSkipped('invoiceninja/admin-api is not installed');
        }
    }

    public static function entityTypes(): array
    {
        return [['invoice'], ['quote'], ['purchase_order']];
    }

    private function linkedInvitation(string $entity_type, bool $dirty = false)
    {
        $entity = $this->{$entity_type};
        $invitation = $entity->invitations->first();
        $invitation->can_sign = true;
        $invitation->saveQuietly();

        $sync = match ($entity_type) {
            'invoice' => new InvoiceSync(),
            'quote' => new QuoteSync(),
            'purchase_order' => new PurchaseOrderSync(),
        };
        $sync->qb_id = 'QB-123';
        if ($entity_type !== 'purchase_order') {
            $sync->qb_sync_token = '7';
        }
        $sync->dn_dirty = $dirty;
        $sync->addInvitation($invitation->key, 'document-uuid', 'invitation-uuid', 'stored-signature');
        $entity->sync = $sync;
        $entity->saveQuietly();

        return $invitation;
    }

    #[DataProvider('entityTypes')]
    public function testDocumentUpdatesMarkExistingSigningPdfDirty(string $entity_type): void
    {
        $this->skipWithoutAdminApi();
        $this->linkedInvitation($entity_type);
        $entity = $this->{$entity_type};
        $observer = match ($entity_type) {
            'invoice' => new InvoiceObserver(),
            'quote' => new QuoteObserver(),
            'purchase_order' => new PurchaseOrderObserver(),
        };
        $repository = match ($entity_type) {
            'invoice' => new InvoiceRepository(),
            'quote' => new QuoteRepository(),
            'purchase_order' => new PurchaseOrderRepository(),
        };
        $invitations = $entity->sync->invitations;
        Http::fake();

        // Exercise the repository's manually emitted update event without waiting
        // for the test transaction to commit before invoking the observer.
        Event::listen('eloquent.updated: '.get_class($entity), fn ($updated) => $observer->updated($updated));
        $repository->save(['public_notes' => 'Revised scope'], $entity);

        $entity->refresh();
        $this->assertTrue($entity->sync->dn_dirty);
        $this->assertSame($invitations, $entity->sync->invitations);
        $this->assertSame('QB-123', $entity->sync->qb_id);
        Http::assertNothingSent();
    }

    #[DataProvider('entityTypes')]
    public function testCompletedAndUnlinkedDocumentsAreNotMarkedDirty(string $entity_type): void
    {
        $this->skipWithoutAdminApi();
        $this->linkedInvitation($entity_type);
        $entity = $this->{$entity_type};
        $observer = match ($entity_type) {
            'invoice' => new InvoiceObserver(),
            'quote' => new QuoteObserver(),
            'purchase_order' => new PurchaseOrderObserver(),
        };
        $sync = $entity->sync;
        $sync->dn_completed = true;
        $entity->sync = $sync;
        $entity->saveQuietly();

        $observer->updated($entity);
        $this->assertFalse($entity->fresh()->sync->dn_dirty);

        $sync->dn_completed = false;
        $sync->invitations = [];
        $entity->sync = $sync;
        $entity->saveQuietly();

        $observer->updated($entity);
        $this->assertFalse($entity->fresh()->sync->dn_dirty);
    }

    #[DataProvider('entityTypes')]
    public function testDirtyStoredInvitationRefreshesPdfWithoutRewritingCredentials(string $entity_type): void
    {
        $this->skipWithoutAdminApi();
        $invitation = $this->linkedInvitation($entity_type, true);
        $entity = $this->{$entity_type};
        $invitations = $entity->sync->invitations;
        Http::fake(['*refresh_ninja_pdf' => Http::response(['success' => true])]);

        Livewire::test(DocuNinjaLoader::class, [
            'invitation_id' => $invitation->id,
            '_key' => $this->bootContext($entity_type),
        ])->call('loadDocuNinjaData')
            ->assertDispatched('docuninja-loader-ready')
            ->assertNotDispatched('docuninja-signature-captured')
            ->assertSet('isReady', true)
            ->assertSet('error', null);

        $entity->refresh();
        $this->assertFalse($entity->sync->dn_dirty);
        $this->assertSame($invitations, $entity->sync->invitations);
        $this->assertSame('QB-123', $entity->sync->qb_id);
        if ($entity_type !== 'purchase_order') {
            $this->assertSame('7', $entity->sync->qb_sync_token);
        }
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['entity_type'] === $entity_type
            && $request['entity_id'] === $entity->hashed_id
            && $request['company_key'] === $entity->company->company_key
            && $request['document_id'] === 'document-uuid'
            && strlen(base64_decode($request['file'], true)) > 0);
    }

    #[DataProvider('entityTypes')]
    public function testRefreshFailureKeepsFlagAndPreventsSigningUntilRetry(string $entity_type): void
    {
        $this->skipWithoutAdminApi();
        $invitation = $this->linkedInvitation($entity_type, true);
        $entity = $this->{$entity_type};
        $invitations = $entity->sync->invitations;
        Http::fake(['*refresh_ninja_pdf' => Http::sequence()->pushStatus(503)->push(['success' => true])]);

        $component = Livewire::test(DocuNinjaLoader::class, [
            'invitation_id' => $invitation->id,
            '_key' => $this->bootContext($entity_type),
        ])->call('loadDocuNinjaData')
            ->assertNotDispatched('docuninja-loader-ready')
            ->assertNotDispatched('docuninja-signature-captured')
            ->assertSet('isReady', false);

        $this->assertTrue($entity->fresh()->sync->dn_dirty);
        $this->assertSame($invitations, $entity->fresh()->sync->invitations);

        $component->call('retryLoading')
            ->assertDispatched('docuninja-loader-ready')
            ->assertSet('isReady', true)
            ->assertSet('error', null);

        $this->assertFalse($entity->fresh()->sync->dn_dirty);
        Http::assertSentCount(2);
    }

    #[DataProvider('entityTypes')]
    public function testAnEditDuringRefreshLeavesFlagPendingWithoutBlockingSigning(string $entity_type): void
    {
        $this->skipWithoutAdminApi();
        $invitation = $this->linkedInvitation($entity_type, true);
        $entity = $this->{$entity_type};
        Http::fake(function () use ($entity) {
            $entity->newQuery()->where('id', $entity->id)->update([
                'public_notes' => 'Edited during refresh',
                'updated_at' => now()->addMinute()->format('Y-m-d H:i:s.u'),
            ]);

            return Http::response(['success' => true]);
        });

        Livewire::test(DocuNinjaLoader::class, [
            'invitation_id' => $invitation->id,
            '_key' => $this->bootContext($entity_type),
        ])->call('loadDocuNinjaData')
            ->assertDispatched('docuninja-loader-ready')
            ->assertSet('isReady', true)
            ->assertSet('error', null);

        $this->assertTrue($entity->fresh()->sync->dn_dirty);
        $this->assertSame('Edited during refresh', $entity->fresh()->public_notes);
    }

    /**
     * The bug: a 404 (company never provisioned in DocuNinja) must not be
     * mistaken for a captured signature.
     */
    public function testFailedSignableDoesNotMarkDocumentAsSigned()
    {
        $this->skipWithoutAdminApi();

        $invitation = $this->signingInvitation();

        Http::fake([
            '*signable_entity*' => Http::response(['message' => 'Company not found'], 404),
        ]);

        Livewire::test(DocuNinjaLoader::class, [
            'invitation_id' => $invitation->id,
            '_key' => $this->bootContext(),
        ])
            ->call('loadDocuNinjaData')
            ->assertNotDispatched('docuninja-signature-captured')
            ->assertNotDispatched('docuninja-loader-ready')
            ->assertSet('isLoading', false)
            ->assertSet('isReady', false)
            ->assertSet('error', ctrans('texts.docuninja_unavailable'));

        $this->invoice->refresh();

        $this->assertFalse((bool) $this->invoice->sync?->dn_completed, 'A failed API call must not mark the invoice as signed');
        $this->assertEmpty($this->invoice->sync?->invitations ?? [], 'A failed API call must not persist an empty invitation');
    }

    /**
     * DocuNinja also returns 400 for legitimate business refusals (voided /
     * expired document, no matching invitation). Those are errors, not signatures.
     */
    public function testUnsignableDocumentDoesNotMarkDocumentAsSigned()
    {
        $this->skipWithoutAdminApi();

        $invitation = $this->signingInvitation();

        Http::fake([
            '*signable_entity*' => Http::response([
                'document_id' => '',
                'document_invitation_id' => '',
                'sig' => '',
                'success' => false,
                'error' => 'This document is not signable',
            ], 400),
        ]);

        Livewire::test(DocuNinjaLoader::class, [
            'invitation_id' => $invitation->id,
            '_key' => $this->bootContext(),
        ])
            ->call('loadDocuNinjaData')
            ->assertNotDispatched('docuninja-signature-captured')
            ->assertSet('isReady', false)
            ->assertSet('error', ctrans('texts.docuninja_unavailable'));

        $this->invoice->refresh();

        $this->assertFalse((bool) $this->invoice->sync?->dn_completed);
        $this->assertEmpty($this->invoice->sync?->invitations ?? []);
    }

    /**
     * The happy path must still persist the invitation and hand off to the
     * DocuNinja component.
     */
    #[DataProvider('entityTypes')]
    public function testSuccessfulSignablePersistsInvitationAndSignalsReady(string $entity_type): void
    {
        $this->skipWithoutAdminApi();

        $entity = $this->{$entity_type};
        $invitation = $entity->invitations->first();
        $invitation->can_sign = true;
        $invitation->saveQuietly();

        Http::fake([
            '*signable_entity*' => Http::response([
                'document_id' => 'doc-uuid-1',
                'document_invitation_id' => 'doc-invitation-uuid-1',
                'sig' => 'signature-payload',
                'success' => true,
                'error' => null,
            ], 201),
        ]);

        Livewire::test(DocuNinjaLoader::class, [
            'invitation_id' => $invitation->id,
            '_key' => $this->bootContext($entity_type),
        ])
            ->call('loadDocuNinjaData')
            ->assertDispatched('docuninja-loader-ready')
            ->assertNotDispatched('docuninja-signature-captured')
            ->assertSet('isReady', true)
            ->assertSet('error', null);

        $entity->refresh();

        $stored = $entity->sync->getInvitation($invitation->key);

        $this->assertNotNull($stored);
        $this->assertSame('doc-uuid-1', $stored['dn_id']);
        $this->assertSame('doc-invitation-uuid-1', $stored['dn_invitation_id']);
        $this->assertSame('signature-payload', $stored['dn_sig']);
        $this->assertCount(1, $entity->sync->invitations);
        $this->assertFalse($entity->sync->dn_dirty);
        Http::assertSentCount(1);
    }

    /**
     * A genuinely completed document still short-circuits to "signature captured"
     * without touching the API. This is the meaning `success: false` retains.
     *
     * The fixture mirrors what a real portal-side completion leaves behind: the
     * invitation was resolved before signing, so a usable row is always present.
     */
    public function testAlreadyCompletedDocumentDispatchesSignatureCaptured()
    {
        $invitation = $this->signingInvitation();

        $sync = new InvoiceSync(dn_dirty: true);
        $sync->addInvitation($invitation->key, 'doc-uuid-5', 'doc-invitation-uuid-5', 'captured-signature');
        $sync->dn_completed = true;
        $this->invoice->sync = $sync;
        $this->invoice->saveQuietly();

        Http::fake();

        Livewire::test(DocuNinjaLoader::class, [
            'invitation_id' => $invitation->id,
            '_key' => $this->bootContext(),
        ])
            ->call('loadDocuNinjaData')
            ->assertDispatched('docuninja-signature-captured')
            ->assertSet('isLoading', false)
            ->assertSet('error', null);

        Http::assertNothingSent();
    }

    /**
     * An already-issued, not-yet-signed invitation is served from stored sync
     * data — no API call, straight to the DocuNinja component.
     */
    public function testStoredInvitationIsReusedWithoutCallingTheApi()
    {
        $invitation = $this->signingInvitation();

        $sync = new InvoiceSync();
        $sync->addInvitation($invitation->key, 'doc-uuid-2', 'doc-invitation-uuid-2', 'stored-signature');
        $this->invoice->sync = $sync;
        $this->invoice->saveQuietly();

        Http::fake();

        Livewire::test(DocuNinjaLoader::class, [
            'invitation_id' => $invitation->id,
            '_key' => $this->bootContext(),
        ])
            ->call('loadDocuNinjaData')
            ->assertDispatched('docuninja-loader-ready')
            ->assertNotDispatched('docuninja-signature-captured')
            ->assertSet('isReady', true);

        Http::assertNothingSent();
    }
}
