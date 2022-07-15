<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers\ClientPortal;

use App\Events\Misc\InvitationWasViewed;
use App\Events\Quote\QuoteWasApproved;
use App\Events\Quote\QuoteWasViewed;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClientPortal\Quotes\ProcessQuotesInBulkRequest;
use App\Http\Requests\ClientPortal\Quotes\ShowQuoteRequest;
use App\Http\Requests\ClientPortal\Quotes\ShowQuotesRequest;
use App\Jobs\Invoice\InjectSignature;
use App\Models\Quote;
use App\Utils\Ninja;
use App\Utils\TempFile;
use App\Utils\Traits\MakesHash;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

        $invitation = $quote->invitations()->where('client_contact_id', auth()->user()->id)->first();

        $data = [
            'quote' => $quote,
            'key' => $invitation ? $invitation->key : false,
        ];

        if ($invitation && auth()->guard('contact') && ! request()->has('silent') && ! $invitation->viewed_date) {
            $invitation->markViewed();

            event(new InvitationWasViewed($quote, $invitation, $quote->company, Ninja::eventVars()));
            event(new QuoteWasViewed($invitation, $invitation->company, Ninja::eventVars()));
        }

        if ($request->query('mode') === 'fullscreen') {
            return render('quotes.show-fullscreen', $data);
        }

        return $this->render('quotes.show', $data);
    }

    public function bulk(ProcessQuotesInBulkRequest $request)
    {
        $transformed_ids = $this->transformKeys($request->quotes);

        if ($request->action == 'download') {
            return $this->downloadQuotes((array) $transformed_ids);
        }

        if ($request->action = 'approve') {
            return $this->approve((array) $transformed_ids, $request->has('process'));
        }

        return back();
    }

    public function downloadQuotes($ids)
    {
        $data['quotes'] = Quote::whereIn('id', $ids)
                            ->whereClientId(auth()->user()->client->id)
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
        $quotes = Quote::whereIn('id', $ids)
            ->whereClientId(auth()->user()->client->id)
            ->withTrashed()
            ->get();

        if (! $quotes || $quotes->count() == 0) {
            return redirect()
                ->route('client.quotes.index')
                ->with('message', ctrans('texts.no_quotes_available_for_download'));
        }

        if ($quotes->count() == 1) {
            $file = $quotes->first()->service()->getQuotePdf();
            // return response()->download($file, basename($file), ['Cache-Control:' => 'no-cache'])->deleteFileAfterSend(true);
            return response()->streamDownload(function () use ($file) {
                echo Storage::get($file);
            }, basename($file), ['Content-Type' => 'application/pdf']);
        }

        return $this->buildZip($quotes);
    }

    private function buildZip($quotes)
    {
        // create new archive
        $zipFile = new \PhpZip\ZipFile();
        try {
            foreach ($quotes as $quote) {

                //add it to the zip
                $zipFile->addFromString(basename($quote->pdf_file_path()), file_get_contents($quote->pdf_file_path(null, 'url', true)));
            }

            $filename = date('Y-m-d').'_'.str_replace(' ', '_', trans('texts.quotes')).'.zip';
            $filepath = sys_get_temp_dir().'/'.$filename;

            $zipFile->saveAsFile($filepath) // save the archive to a file
                   ->close(); // close archive

           return response()->download($filepath, $filename)->deleteFileAfterSend(true);
        } catch (\PhpZip\Exception\ZipException $e) {
            // handle exception
        } finally {
            $zipFile->close();
        }
    }

    protected function approve(array $ids, $process = false)
    {
        $quotes = Quote::whereIn('id', $ids)
            ->where('client_id', auth()->guard('contact')->user()->client->id)
            ->where('company_id', auth()->guard('contact')->user()->client->company_id)
            ->whereIn('status_id', [Quote::STATUS_DRAFT, Quote::STATUS_SENT])
            ->withTrashed()
            ->get();

        if (! $quotes || $quotes->count() == 0) {
            return redirect()
                ->route('client.quotes.index')
                ->with('message', ctrans('texts.quotes_with_status_sent_can_be_approved'));
        }

        if ($process) {
            foreach ($quotes as $quote) {
                $quote->service()->approve(auth()->user())->save();
                // event(new QuoteWasApproved(auth()->guard('contact')->user(), $quote, $quote->company, Ninja::eventVars()));

                if (request()->has('signature') && ! is_null(request()->signature) && ! empty(request()->signature)) {
                    InjectSignature::dispatch($quote, request()->signature);
                }
            }

            if (count($ids) == 1) {

            //forward client to the invoice if it exists
                if ($quote->invoice()->exists()) {
                    return redirect()->route('client.invoice.show', $quote->invoice->hashed_id);
                }

                return redirect()->route('client.quote.show', $quotes->first()->hashed_id);
            }

            return redirect()
                ->route('client.quotes.index')
                ->withSuccess('Quote(s) approved successfully.');
        }

        return $this->render('quotes.approve', [
            'quotes' => $quotes,
        ]);
    }
}
