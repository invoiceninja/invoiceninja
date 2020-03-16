<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Controllers\ClientPortal;

use App\Filters\InvoiceFilters;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClientPortal\ShowInvoiceRequest;
use App\Jobs\Entity\ActionEntity;
use App\Models\Invoice;
use App\Utils\Number;
use App\Utils\Traits\MakesDates;
use App\Utils\Traits\MakesHash;
use Yajra\DataTables\Html\Builder;
use ZipStream\Option\Archive;
use ZipStream\ZipStream;

/**
 * Class InvoiceController
 * @package App\Http\Controllers\ClientPortal\InvoiceController
 */

class InvoiceController extends Controller
{
    use MakesHash;
    use MakesDates;

    /**
     * Show the list of Invoices
     *
     * @param \App\Filters\InvoiceFilters $filters The filters
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \Exception
     */
    public function index(InvoiceFilters $filters, Builder $builder)
    {
        $invoices = auth()->user()->client->company->invoices()->paginate(10);

        return $this->render('invoices.index', ['invoices' => $invoices]);
    }

    private function buildClientButtons($invoice)
    {
        $buttons = '<div>';

        if ($invoice->isPayable()) {
            $buttons .= "<button type=\"button\" class=\"btn btn-sm btn-info\" onclick=\"payInvoice('".$invoice->hashed_id."')\"><i class=\"glyphicon glyphicon-edit\"></i>".ctrans('texts.pay_now')."</button>";
            //    $buttons .= '<a href="/client/invoices/'. $invoice->hashed_id .'" class="btn btn-sm btn-info"><i class="glyphicon glyphicon-edit"></i>'.ctrans('texts.pay_now').'</a>';
        }

        $buttons .= '<a href="/client/invoices/'. $invoice->hashed_id .'" class="btn btn-sm btn-primary"><i class="glyphicon glyphicon-edit"></i>'.ctrans('texts.view').'</a>';

        $buttons .="</div>";

        return $buttons;
    }
    /**
     * Display the specified resource.
     *
     * @param      \App\Models\Invoice $invoice  The invoice
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function show(ShowInvoiceRequest $request, Invoice $invoice)
    {
        $data = [
            'invoice' => $invoice,
        ];

        return $this->render('invoices.show', $data);
    }

    /**
     * Pay one or more invoices
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\View\View
     */
    public function bulk()
    {
        $transformed_ids = $this->transformKeys(explode(",", request()->input('hashed_ids')));

        if (request()->input('action') == 'payment') {
            return $this->makePayment($transformed_ids);
        } elseif (request()->input('action') == 'download') {
            return $this->downloadInvoicePDF($transformed_ids);
        }
    }


    private function makePayment(array $ids)
    {
        $invoices = Invoice::whereIn('id', $ids)
                            ->whereClientId(auth()->user()->client->id)
                            ->get();

        $total = $invoices->sum('balance');

        $invoices = $invoices->filter(function ($invoice) {
            return $invoice->isPayable();
        });

        if ($invoices->count() == 0) {
            return back()->with(['warning' => 'No payable invoices selected']);
        }

        $invoices->map(function ($invoice) {
            $invoice->balance = Number::formatMoney($invoice->balance, $invoice->client);
            $invoice->due_date = $this->formatDate($invoice->due_date, $invoice->client->date_format());
            return $invoice;
        });

        $formatted_total = Number::formatMoney($total, auth()->user()->client);

        $payment_methods = auth()->user()->client->getPaymentMethods($total);

        $data = [
            'settings' => auth()->user()->client->getMergedSettings(),
            'invoices' => $invoices,
            'formatted_total' => $formatted_total,
            'payment_methods' => $payment_methods,
            'hashed_ids' => $invoices->pluck('hashed_ids'),
            'total' =>  $total,
        ];

        return $this->render('invoices.payment', $data);
    }

    private function downloadInvoicePDF(array $ids)
    {
        $invoices = Invoice::whereIn('id', $ids)
                            ->whereClientId(auth()->user()->client->id)
                            ->get();

        //generate pdf's of invoices locally
        if (!$invoices || $invoices->count() == 0) {
            return;
        }

        //if only 1 pdf, output to buffer for download
        if ($invoices->count() == 1) {
            return response()->download(public_path($invoices->first()->pdf_file_path()));
        }


        # enable output of HTTP headers
        $options = new Archive();
        $options->setSendHttpHeaders(true);

        # create a new zipstream object
        $zip = new ZipStream(date('Y-m-d') . '_' . str_replace(' ', '_', trans('texts.invoices')).".zip", $options);

        foreach ($invoices as $invoice) {
            $zip->addFileFromPath(basename($invoice->pdf_file_path()), public_path($invoice->pdf_file_path()));
        }

        # finish the zip stream
        $zip->finish();
    }
}
