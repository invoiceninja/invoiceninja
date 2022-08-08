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

use App\Events\Invoice\InvoiceWasViewed;
use App\Events\Misc\InvitationWasViewed;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClientPortal\Invoices\ProcessInvoicesInBulkRequest;
use App\Http\Requests\ClientPortal\Invoices\ShowInvoiceRequest;
use App\Http\Requests\ClientPortal\Invoices\ShowInvoicesRequest;
use App\Models\Invoice;
use App\Utils\Ninja;
use App\Utils\Number;
use App\Utils\TempFile;
use App\Utils\Traits\MakesDates;
use App\Utils\Traits\MakesHash;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    use MakesHash, MakesDates;

    /**
     * Display list of invoices.
     *
     * @return Factory|View
     */
    public function index(ShowInvoicesRequest $request)
    {
        return $this->render('invoices.index');
    }

    /**
     * Show specific invoice.
     *
     * @param ShowInvoiceRequest $request
     * @param Invoice $invoice
     *
     * @return Factory|View
     */
    public function show(ShowInvoiceRequest $request, Invoice $invoice)
    {
        set_time_limit(0);

        $invoice->service()->removeUnpaidGatewayFees()->save();

        $invitation = $invoice->invitations()->where('client_contact_id', auth()->guard('contact')->user()->id)->first();

        if ($invitation && auth()->guard('contact') && ! session()->get('is_silent') && ! $invitation->viewed_date) {
            $invitation->markViewed();

            event(new InvitationWasViewed($invoice, $invitation, $invoice->company, Ninja::eventVars()));
            event(new InvoiceWasViewed($invitation, $invitation->company, Ninja::eventVars()));
        }

        $data = [
            'invoice' => $invoice,
            'key' => $invitation ? $invitation->key : false,
        ];

        if ($request->query('mode') === 'fullscreen') {
            return render('invoices.show-fullscreen', $data);
        }

        return $this->render('invoices.show', $data);
    }

    /**
     * Pay one or more invoices.
     *
     * @param ProcessInvoicesInBulkRequest $request
     * @return mixed
     */
    public function catch_bulk()
    {
        return $this->render('invoices.index');
    }

    public function bulk(ProcessInvoicesInBulkRequest $request)
    {
        $transformed_ids = $this->transformKeys($request->invoices);

        if ($request->input('action') == 'payment') {
            return $this->makePayment((array) $transformed_ids);
        } elseif ($request->input('action') == 'download') {
            return $this->downloadInvoices((array) $transformed_ids);
        }

        return redirect()
            ->back()
            ->with('message', ctrans('texts.no_action_provided'));
    }

    public function downloadInvoices($ids)
    {
        $data['invoices'] = Invoice::whereIn('id', $ids)
                            ->whereClientId(auth()->guard('contact')->user()->client->id)
                            ->withTrashed()
                            ->get();

        if (count($data['invoices']) == 0) {
            return back()->with(['message' => ctrans('texts.no_items_selected')]);
        }

        return $this->render('invoices.download', $data);
    }

    public function download(Request $request)
    {
        $transformed_ids = $this->transformKeys($request->invoices);

        return $this->downloadInvoicePDF((array) $transformed_ids);
    }

    /**
     * @param array $ids
     * @return Factory|View|RedirectResponse
     */
    private function makePayment(array $ids)
    {
        $invoices = Invoice::whereIn('id', $ids)
                            ->whereClientId(auth()->guard('contact')->user()->client->id)
                            ->withTrashed()
                            ->get();

        //filter invoices which are payable
        $invoices = $invoices->filter(function ($invoice) {
            return $invoice->isPayable();
        });

        //return early if no invoices.
        if ($invoices->count() == 0) {
            return back()
                ->with('message', ctrans('texts.no_payable_invoices_selected'));
        }

        //iterate and sum the payable amounts either partial or balance
        $total = 0;
        foreach ($invoices as $invoice) {
            if ($invoice->partial > 0) {
                $total += $invoice->partial;
            } else {
                $total += $invoice->balance;
            }
        }

        //format data
        $invoices->map(function ($invoice) {
            $invoice->service()->removeUnpaidGatewayFees();
            $invoice->balance = $invoice->balance > 0 ? Number::formatValue($invoice->balance, $invoice->client->currency()) : 0;
            $invoice->partial = $invoice->partial > 0 ? Number::formatValue($invoice->partial, $invoice->client->currency()) : 0;

            return $invoice;
        });

        //format totals
        $formatted_total = Number::formatMoney($total, auth()->guard('contact')->user()->client);

        $payment_methods = auth()->guard('contact')->user()->client->service()->getPaymentMethods($total);

        //if there is only one payment method -> lets return straight to the payment page

        $data = [
            'settings' => auth()->guard('contact')->user()->client->getMergedSettings(),
            'invoices' => $invoices,
            'formatted_total' => $formatted_total,
            'payment_methods' => $payment_methods,
            'hashed_ids' => $invoices->pluck('hashed_id'),
            'total' =>  $total,
        ];

        return $this->render('invoices.payment', $data);
    }

    /**
     * Helper function to download invoice PDFs.
     *
     * @param array $ids
     *
     * @return void
     */
    private function downloadInvoicePDF(array $ids)
    {
        $invoices = Invoice::whereIn('id', $ids)
                            ->withTrashed()
                            ->whereClientId(auth()->guard('contact')->user()->client->id)
                            ->get();

        //generate pdf's of invoices locally
        if (! $invoices || $invoices->count() == 0) {
            return back()->with(['message' => ctrans('texts.no_items_selected')]);
        }

        //if only 1 pdf, output to buffer for download
        if ($invoices->count() == 1) {
            $invoice = $invoices->first();

            $file = $invoice->service()->getInvoicePdf(auth()->guard('contact')->user());

            // return response()->download(file_get_contents(public_path($file)));

            return response()->streamDownload(function () use ($file) {
                echo Storage::get($file);
            }, basename($file), ['Content-Type' => 'application/pdf']);
        }

        return $this->buildZip($invoices);
    }

    private function buildZip($invoices)
    {
        // create new archive
        $zipFile = new \PhpZip\ZipFile();
        try {
            foreach ($invoices as $invoice) {

                //add it to the zip
                $zipFile->addFromString(basename($invoice->pdf_file_path()), file_get_contents($invoice->pdf_file_path(null, 'url', true)));
            }

            $filename = date('Y-m-d').'_'.str_replace(' ', '_', trans('texts.invoices')).'.zip';
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
}
