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

namespace App\Http\Controllers\ClientPortal;

use App\Utils\Ninja;
use App\Models\Quote;
use App\Utils\HtmlEngine;
use Illuminate\View\View;
use Illuminate\Http\Request;
use App\Models\QuoteInvitation;
use App\Utils\Traits\MakesHash;
use App\Events\Quote\QuoteWasViewed;
use App\Http\Controllers\Controller;
use App\Jobs\Invoice\InjectSignature;
use Illuminate\Contracts\View\Factory;
use App\Events\Misc\InvitationWasViewed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Services\Quote\QuoteOptions;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Http\Requests\ClientPortal\Quotes\ShowQuoteRequest;
use App\Http\Requests\ClientPortal\Quotes\ShowQuotesRequest;
use App\Http\Requests\ClientPortal\Quotes\ProcessQuotesInBulkRequest;

class QuoteController extends Controller
{
    use MakesHash;

    /**
     * Display a listing of the quotes.
     *
     * @return Factory|View
     */
    public function index(ShowQuotesRequest $request)
    {
        return $this->render('quotes.index');
    }

    /**
     * Display the specified resource.
     *
     * @param ShowQuoteRequest $request
     * @param Quote $quote
     * @return Factory|View|BinaryFileResponse
     */
    public function show(ShowQuoteRequest $request, Quote $quote)
    {
        /* If the quote is expired, convert the status here */

        /** @var \App\Models\QuoteInvitation|null $invitation */
        $invitation = $quote->invitations()->where('client_contact_id', auth()->guard('contact')->user()->id)->first() ?? $quote->invitations()->first();
        $variables = ($invitation && auth()->guard('contact')->user()->client->getSetting('show_accept_quote_terms')) ? (new HtmlEngine($invitation))->generateLabelsAndValues() : false;
        $docuninja_active = $quote->company->docuninjaActive();
        $signature_accepted = $quote->sync?->dn_completed ?? false;

        $data = [
            'quote' => $quote,
            '_key' => $invitation ? $invitation->key : false,
            'invitation' => $invitation,
            'variables' => $variables,
            'requires_signature' => !$signature_accepted && $quote->client->getSetting('require_quote_signature') && $quote->company->account->hasFeature(\App\Models\Account::FEATURE_INVOICE_SETTINGS),
            'docuninja_active' => $docuninja_active && !$signature_accepted && $quote->client->getSetting('require_quote_signature'),
        ];

        if ($invitation && auth()->guard('contact')->check() && ! request()->has('silent') && ! $invitation->viewed_date) {
            $invitation->markViewed();

            event(new InvitationWasViewed($quote, $invitation, $quote->company, Ninja::eventVars()));
            event(new QuoteWasViewed($invitation, $invitation->company, Ninja::eventVars()));
        }

        if ($quote->hasOptionSelection() && in_array($quote->status_id, [Quote::STATUS_DRAFT, Quote::STATUS_SENT]) && ! $quote->invoice_id && ! $quote->is_deleted) {
            if ($invitation && $invitation->client_contact_id === auth()->guard('contact')->id()
                && $this->hasActiveSigning($invitation)) {
                return $this->signQuote($invitation);
            }

            return $this->approve([$quote->id]);
        }

        if ($quote->hasOptions() && in_array($quote->status_id, [Quote::STATUS_DRAFT, Quote::STATUS_SENT]) && ! $quote->invoice_id && ! $quote->is_deleted) {
            return redirect()->route('client.quote.options', $quote->hashed_id);
        }

        if ($request->query('mode') === 'fullscreen') {
            return render('quotes.show-fullscreen', $data);
        }

        return $this->render('quotes.show', $data);
    }

    public function options(ShowQuoteRequest $request, Quote $quote)
    {
        if (! $quote->hasOptions() || ! in_array($quote->status_id, [Quote::STATUS_DRAFT, Quote::STATUS_SENT]) || $quote->invoice_id || $quote->is_deleted) {
            return redirect()->route('client.quote.show', $quote->hashed_id);
        }

        return $this->render('quotes.options', ['quote' => $quote]);
    }

    public function restartOptions(ShowQuoteRequest $request, Quote $quote)
    {
        if ($quote->company->docuninjaActive() && $quote->client->getSetting('require_quote_signature')) {
            return redirect()->route('client.quote.show', $quote->hashed_id);
        }

        $request->validate(['restart_confirmed' => ['required', 'accepted']]);
        app(QuoteOptions::class)->restart($quote);

        return redirect()->route('client.quote.options', $quote->hashed_id);
    }

    public function bulk(ProcessQuotesInBulkRequest $request)
    {
        if ($request->has('request_hash')) {
            $request_hash = $request->input('request_hash');
            $cached_request = Cache::pull($request_hash);

            abort_unless(
                is_array($cached_request)
                && ($cached_request['client_contact_id'] ?? null) === auth()->guard('contact')->id()
                && is_array($cached_request['request'] ?? null),
                404
            );

            $request->merge($cached_request['request']);
        }

        $transformed_ids = $this->transformKeys($request->quotes);

        if ($request->action == 'download') {
            return $this->downloadQuotes((array) $transformed_ids);
        }

        if ($request->action == 'approve') {

            $quotes = Quote::query()
                ->whereIn('id', $transformed_ids)
                ->where('client_id', auth()->guard('contact')->user()->client_id)
                ->where('company_id', auth()->guard('contact')->user()->company_id)
                ->whereIn('status_id', [Quote::STATUS_DRAFT, Quote::STATUS_SENT])
                ->get();
            $quotes->each(fn ($quote) => app(QuoteOptions::class)->assertConfirmed($quote));
            if ($quotes->count() === 1 && count($transformed_ids) === 1
                && $quotes->first()->hasOptionSelection() && ! $request->filled('selection_key')) {
                return redirect()->route('client.quote.show', $quotes->first()->hashed_id);
            }

            if ($request->filled('selection_key') || $quotes->contains(fn ($quote) => $quote->hasOptionSelection())) {
                abort_unless(
                    $quotes->count() === 1 && count($transformed_ids) === 1
                    && $quotes->first()->hasPendingOptionSelection()
                    && $quotes->first()->backup->option_selection['key'] === $request->input('selection_key'),
                    409
                );
            }
            $option_quote = $quotes->first(fn ($quote) => $quote->hasOptions());
            if ($option_quote) {
                return redirect()->route('client.quote.options', $option_quote->hashed_id);
            }

            if ($quotes->count() === 1 && $quotes->first()->hasPendingOptionSelection()) {
                $invitation = $quotes->first()->invitations()
                    ->where('client_contact_id', auth()->guard('contact')->id())->first();

                if ($invitation && $this->hasActiveSigning($invitation)) {
                    return $this->signQuote($invitation);
                }
            }

            if ($request->has('process') && ! $request->has('request_hash')
                && $quotes->count() === 1 && $quotes->first()->hasPendingOptionSelection()) {
                $request->validate(['user_input' => ['sometimes', 'nullable', 'string']]);
                app(QuoteOptions::class)->captureApprovalInput(
                    $quotes->first(),
                    $request->input('user_input'),
                    $request->string('selection_key')->toString(),
                );
            }

            if (auth()->guard('contact')->user()->company->docuninjaActive()
            && auth()->guard('contact')->user()->client->getSetting('require_quote_signature')) {
                $invitations = \App\Models\QuoteInvitation::with('quote')
                                        ->whereIn('quote_id', $transformed_ids)
                                        ->where('client_contact_id', auth()->guard('contact')->user()->id)
                                        ->get()
                                        ->filter(function ($invitation) {
                                            return !$invitation->quote->sync?->dn_completed;
                                        });

                if ($invitations->count() > 0) {
                    $invitation = $invitations->first();

                    return $this->signQuote($invitation, $request->except(['_token', 'request_hash']));
                }
            }

            return $this->approve(
                (array) $transformed_ids,
                $request->has('process') || $request->has('request_hash'),
                selection_key: $request->input('selection_key'),
            );
        }

        if ($request->action == 'reject') {
            return $this->reject((array) $transformed_ids, $request->has('process'));
        }

        return back();
    }

    private function hasActiveSigning(QuoteInvitation $invitation): bool
    {
        $quote = $invitation->quote;

        if (! $quote->hasPendingOptionSelection() || $quote->sync?->dn_completed
            || $quote->is_deleted || $quote->invoice_id || $quote->hasLapsedValidUntil()
            || ! $quote->company->docuninjaActive() || ! $quote->client->getSetting('require_quote_signature')) {
            return false;
        }

        $context = Cache::get($invitation->key, []);

        return ($context['selection_key'] ?? null) === $quote->backup->option_selection['key']
            || (! $quote->sync?->dn_dirty && filled($quote->sync?->getInvitation($invitation->key)['dn_id'] ?? null));
    }

    private function signQuote(QuoteInvitation $invitation, ?array $approval_request = null)
    {
        $quote = $invitation->quote;
        $request_hash = \Illuminate\Support\Str::random(64);

        Cache::put($request_hash, [
            'client_contact_id' => auth()->guard('contact')->id(),
            'request' => $approval_request ?? [
                'quotes' => [$quote->hashed_id],
                'action' => 'approve',
                'process' => true,
                'selection_key' => $quote->backup->option_selection['key'],
            ],
        ], 60 * 60 * 24);

        return $this->render('components.docuninja', [
            'invitation_id' => $invitation->id,
            'entity_type' => 'quote',
            'entity_number' => $quote->number,
            'db' => $invitation->company->db,
            'request_hash' => $request_hash,
            '_key' => $invitation->key,
        ]);
    }

    public function continueApproval(string $request_hash)
    {
        $cached_request = Cache::get($request_hash);

        abort_unless(
            is_array($cached_request)
            && ($cached_request['client_contact_id'] ?? null) === auth()->guard('contact')->id()
            && is_array($cached_request['request'] ?? null),
            404
        );

        return $this->render('quotes.continue-approval', [
            'request_hash' => $request_hash,
        ]);
    }

    public function downloadQuotes($ids)
    {
        /** @var \App\Models\ClientContact $client_contact **/
        $client_contact = auth()->user();

        $data['quotes'] = Quote::query()
                            ->whereIn('id', $ids)
                            ->where('client_id', $client_contact->client_id)
                            ->withTrashed()
                            ->get();

        if (count($data['quotes']) == 0) {
            return back()->with(['message' => ctrans('texts.no_items_selected')]);
        }

        return $this->render('quotes.download', $data);
    }

    public function download(Request $request)
    {
        $transformed_ids = $this->transformKeys($request->quotes);

        return $this->downloadQuotePdf((array) $transformed_ids);
    }

    protected function downloadQuotePdf(array $ids)
    {

        /** @var \App\Models\ClientContact $client_contact **/
        $client_contact = auth()->user();

        $quote_invitations = QuoteInvitation::query()
            ->with('quote')
            ->whereIn('quote_id', $ids)
            ->where('client_contact_id', $client_contact->id)
            ->withTrashed()
            ->get();

        if ($quote_invitations->isEmpty()) {
            return redirect()
                ->route('client.quotes.index')
                ->with('message', ctrans('texts.no_quotes_available_for_download'));
        }

        if ($quote_invitations->count() == 1) {
            $invitation = $quote_invitations->first();
            $file = (new \App\Jobs\Entity\CreateRawPdf($invitation))->handle();
            return response()->streamDownload(function () use ($file) {
                echo $file;
            }, $invitation->quote->numberFormatter() . ".pdf", ['Content-Type' => 'application/pdf']);
        }

        return $this->buildZip($quote_invitations);
    }

    private function buildZip($quote_invitations)
    {
        // create new archive
        $zipFile = new \PhpZip\ZipFile();
        try {
            foreach ($quote_invitations as $invitation) {
                $file = (new \App\Jobs\Entity\CreateRawPdf($invitation))->handle();
                $zipFile->addFromString($invitation->quote->numberFormatter() . '.pdf', $file);
            }

            $filename = date('Y-m-d-h-i-s') . '_' . str_replace(' ', '_', trans('texts.quotes')) . '.zip';
            $filepath = sys_get_temp_dir() . '/' . $filename;

            $zipFile->saveAsFile($filepath) // save the archive to a file
                   ->close(); // close archive

            return response()->download($filepath, $filename)->deleteFileAfterSend(true);
        } catch (\PhpZip\Exception\ZipException $e) {
        } finally {
            $zipFile->close();
        }
    }

    protected function reject(array $ids, $process = false)
    {
        $quotes = Quote::query()
            ->whereIn('id', $ids)
            ->where('client_id', auth()->guard('contact')->user()->client_id)
            ->where('company_id', auth()->guard('contact')->user()->company_id)
            ->where('status_id', Quote::STATUS_SENT)
            ->withTrashed()
            ->get();

        if ($quotes->isEmpty()) {
            return redirect()
                ->route('client.quotes.index')
                ->with('message', ctrans('texts.quotes_with_status_sent_can_be_rejected'));
        }

        if ($process) {
            foreach ($quotes as $quote) {

                $quote->service()->reject(auth()->guard('contact')->user(), request()->input('user_input', ''))->save();

            }

            return redirect()
                ->route('client.quotes.index')
                ->withSuccess('Quote(s) rejected successfully.');
        }


        $variables = false;

        return $this->render('quotes.reject', [
            'quotes' => $quotes,
            'variables' => $variables,
        ]);

    }

    protected function approve(array $ids, $process = false, ?string $selection_key = null)
    {
        $quotes = Quote::query()
            ->whereIn('id', $ids)
            ->where('client_id', auth()->guard('contact')->user()->client->id)
            ->where('company_id', auth()->guard('contact')->user()->client->company_id)
            ->whereIn('status_id', [Quote::STATUS_DRAFT, Quote::STATUS_SENT])
            ->whereNull('invoice_id')
            ->withTrashed()
            ->get()
            ->reject(fn (Quote $quote) => $quote->hasLapsedValidUntil());

        if ($quotes->isEmpty()) {
            return redirect()
                ->route('client.quotes.index')
                ->with('message', ctrans('texts.quotes_with_status_sent_can_be_approved'));
        }

        $quotes->each(fn ($quote) => app(QuoteOptions::class)->assertConfirmed($quote));
        if (! $process && ($option_quote = $quotes->first(fn ($quote) => $quote->hasOptions()))) {
            return redirect()->route('client.quote.options', $option_quote->hashed_id);
        }

        if ($process) {
            foreach ($quotes as $index => $quote) {
                abort_if($quote->hasOptions(), 409, ctrans('texts.quote_options_changed'));

                if ($quote->hasOptionSelection()) {
                    $quote = DB::transaction(function () use ($quote, $selection_key) {
                        $quote = Quote::query()->where('company_id', $quote->company_id)->lockForUpdate()->findOrFail($quote->id);
                        abort_unless(
                            $quote->hasPendingOptionSelection()
                            && $quote->backup->option_selection['key'] === $selection_key
                            && in_array($quote->status_id, [Quote::STATUS_DRAFT, Quote::STATUS_SENT])
                            && ! $quote->is_deleted && ! $quote->invoice_id && ! $quote->hasLapsedValidUntil(),
                            409
                        );
                        app(QuoteOptions::class)->assertConfirmed($quote);
                        $quote->service()->approve(auth()->guard('contact')->user())->save();

                        return $quote;
                    });
                    $quotes[$index] = $quote;
                } else {
                    if (request()->has('user_input') && strlen(request()->input('user_input')) > 2) {
                        $quote->po_number = substr(request()->input('user_input'), 0, 180);
                        $quote->saveQuietly();
                    }

                    $quote->service()->approve(auth()->user())->save();
                }

                if (request()->has('signature') && ! is_null(request()->signature) && ! empty(request()->signature)) {
                    InjectSignature::dispatch($quote, auth()->guard('contact')->user()->id, request()->signature, request()->getClientIp());
                }
            }

            if ($quotes->count() == 1) {
                //forward client to the invoice if it exists
                if ($quotes->first()->invoice()->exists()) {
                    return redirect()->route('client.invoice.show', $quotes->first()->invoice->hashed_id);
                }

                return redirect()->route('client.quote.show', $quotes->first()->hashed_id);
            }

            return redirect()
                ->route('client.quotes.index')
                ->withSuccess('Quote(s) approved successfully.');
        }


        $variables = false;

        /** @var \App\Models\QuoteInvitation|null $invitation */
        $invitation = $quotes->first()->invitations()->first();
        if ($invitation) {
            $variables = (new HtmlEngine($invitation))->generateLabelsAndValues();
        }

        $variables = ($invitation && auth()->guard('contact')->user()->client->getSetting('show_accept_quote_terms')) ? (new HtmlEngine($invitation))->generateLabelsAndValues() : false;

        $requires_signature = !$quotes->first()->company->docuninjaActive() && $quotes->first()->client->getSetting('require_quote_signature');

        return $this->render('quotes.approve', [
            'quotes' => $quotes,
            'variables' => $variables,
            'requires_signature' => $requires_signature,
        ]);
    }
}
