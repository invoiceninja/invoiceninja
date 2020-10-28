<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Controllers\ClientPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClientPortal\ProcessInvoicesInBulkRequest;
use App\Http\Requests\ClientPortal\ShowInvoiceRequest;
use App\Models\Invoice;
use App\Utils\Number;
use App\Utils\TempFile;
use App\Utils\Traits\MakesDates;
use App\Utils\Traits\MakesHash;
use Illuminate\Contracts\View\Factory;
use Illuminate\View\View;
use ZipStream\Option\Archive;
use ZipStream\ZipStream;

class InvoiceController extends Controller
{
    use MakesHash, MakesDates;

    /**
     * Display list of invoices.
     *
     * @return Factory|View
     */
    public function index()
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

        $data = [
            'invoice' => $invoice,
        ];

        if ($request->query('mode') === 'fullscreen') {
            return $this->render('invoices.show.fullscreen', $data);
        }

        return $this->render('invoices.show', $data);
    }

    /**
     * Pay one or more invoices.
     *
     * @param ProcessInvoicesInBulkRequest $request
     * @return mixed
     */
    public function bulk(ProcessInvoicesInBulkRequest $request)
    {
        $transformed_ids = $this->transformKeys($request->invoices);

        if ($request->input('action') == 'payment') {
            return $this->makePayment((array) $transformed_ids);
        } elseif ($request->input('action') == 'download') {
            return $this->downloadInvoicePDF((array) $transformed_ids);
        }

        return redirect()->back();
    }

    private function makePayment(array $ids)
    {
        $invoices = Invoice::whereIn('id', $ids)
                            ->whereClientId(auth()->user()->client->id)
                            ->get();

        $total = $invoices->sum('balance');

        $invoices = $invoices->filter(function ($invoice) {
            return $invoice->isPayable() && $invoice->balance > 0;
        });

        if ($invoices->count() == 0) {
            return back()->with(['warning' => 'No payable invoices selected']);
        }

        $invoices->map(function ($invoice) {
            $invoice->service()->removeUnpaidGatewayFees()->save();
            $invoice->balance = Number::formatValue($invoice->balance, $invoice->client->currency());
            $invoice->partial = Number::formatValue($invoice->partial, $invoice->client->currency());

            return $invoice;
        });

        $formatted_total = Number::formatMoney($total, auth()->user()->client);

        $payment_methods = auth()->user()->client->getPaymentMethods($total);

        $data = [
            'settings' => auth()->user()->client->getMergedSettings(),
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
     * @throws \ZipStream\Exception\FileNotFoundException
     * @throws \ZipStream\Exception\FileNotReadableException
     * @throws \ZipStream\Exception\OverflowException
     */
    private function downloadInvoicePDF(array $ids)
    {
        $invoices = Invoice::whereIn('id', $ids)
                            ->whereClientId(auth()->user()->client->id)
                            ->get();

        //generate pdf's of invoices locally
        if (! $invoices || $invoices->count() == 0) {
            return back()->with(['message' => ctrans('texts.no_items_selected')]);
        }

        //if only 1 pdf, output to buffer for download
        if ($invoices->count() == 1) {
            return response()->streamDownload(function () use ($invoices) {
                echo file_get_contents($invoices->first()->pdf_file_path());
            }, basename($invoices->first()->pdf_file_path()));
            //return response()->download(TempFile::path($invoices->first()->pdf_file_path()), basename($invoices->first()->pdf_file_path()));
        }

        // enable output of HTTP headers
        $options = new Archive();
        $options->setSendHttpHeaders(true);

        // create a new zipstream object
        $zip = new ZipStream(date('Y-m-d').'_'.str_replace(' ', '_', trans('texts.invoices')).'.zip', $options);

        foreach ($invoices as $invoice) {
            $zip->addFileFromPath(basename($invoice->pdf_file_path()), TempFile::path($invoice->pdf_file_path()));
        }

        // finish the zip stream
        $zip->finish();
    }
}
