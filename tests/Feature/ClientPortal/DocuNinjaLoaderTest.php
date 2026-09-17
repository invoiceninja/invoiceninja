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
use Illuminate\Support\Facades\Http;
use App\Livewire\Flow2\DocuNinjaLoader;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\DatabaseTransactions;

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

        config(['ninja.docuninja_api_url' => 'https://api.docuninja.test']);
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
    public function testSuccessfulSignablePersistsInvitationAndSignalsReady()
    {
        $this->skipWithoutAdminApi();

        $invitation = $this->signingInvitation();

        Http::fake([
            '*signable_entity*' => Http::response([
                'document_id' => 'doc-uuid-1',
                'document_invitation_id' => 'doc-invitation-uuid-1',
                'sig' => 'signature-payload',
                'success' => true,
                'error' => null,
            ], 200),
        ]);

        Livewire::test(DocuNinjaLoader::class, [
            'invitation_id' => $invitation->id,
            '_key' => $this->bootContext(),
        ])
            ->call('loadDocuNinjaData')
            ->assertDispatched('docuninja-loader-ready')
            ->assertNotDispatched('docuninja-signature-captured')
            ->assertSet('isReady', true)
            ->assertSet('error', null);

        $this->invoice->refresh();

        $stored = $this->invoice->sync->getInvitation($invitation->key);

        $this->assertNotNull($stored);
        $this->assertSame('doc-uuid-1', $stored['dn_id']);
        $this->assertSame('doc-invitation-uuid-1', $stored['dn_invitation_id']);
        $this->assertSame('signature-payload', $stored['dn_sig']);
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

        $sync = new InvoiceSync();
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
