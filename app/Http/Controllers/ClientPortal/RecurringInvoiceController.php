<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Controllers\ClientPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClientPortal\ShowRecurringInvoiceRequest;
use App\Models\RecurringInvoice;
use App\Notifications\ClientContactRequestCancellation;
use App\Notifications\ClientContactResetPassword;
use App\Utils\Number;
use App\Utils\Traits\MakesDates;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Yajra\DataTables\Facades\DataTables;
use Yajra\DataTables\Html\Builder;

/**
 * Class InvoiceController
 * @package App\Http\Controllers\ClientPortal\InvoiceController
 */

class RecurringInvoiceController extends Controller
{

    use MakesHash;
    use MakesDates;
    /**
     * Show the list of Invoices
     *
     * @param      \App\Filters\InvoiceFilters  $filters  The filters
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Builder $builder)
    {
        $invoices = RecurringInvoice::whereClientId(auth()->user()->client->id)
                                    ->whereIn('status_id', [RecurringInvoice::STATUS_PENDING, RecurringInvoice::STATUS_ACTIVE, RecurringInvoice::STATUS_COMPLETED])
                                    ->orderBy('status_id', 'asc')
                                    ->with('client')
                                    ->get();

        if (request()->ajax()) {

            return DataTables::of($invoices)->addColumn('action', function ($invoice) {
                    return '<a href="/client/recurring_invoices/'. $invoice->hashed_id .'" class="btn btn-xs btn-primary"><i class="glyphicon glyphicon-edit"></i>'.ctrans('texts.view').'</a>';
                })->addColumn('frequency_id', function ($invoice) {
                    return RecurringInvoice::frequencyForKey($invoice->frequency_id);
                })
                ->editColumn('status_id', function ($invoice){
                    return RecurringInvoice::badgeForStatus($invoice->status);
                })
                ->editColumn('start_date', function ($invoice){
                    return $this->formatDate($invoice->invoice_date, $invoice->client->date_format());
                })
                ->editColumn('next_send_date', function ($invoice){
                    return $this->formatDate($invoice->next_send_date, $invoice->client->date_format());
                })
                ->editColumn('amount', function ($invoice){
                    return Number::formatMoney($invoice->amount, $invoice->client);
                })
                ->rawColumns(['action', 'status_id'])
                ->make(true);
        
        }

        $data['html'] = $builder;
      
        return view('portal.default.recurring_invoices.index', $data);

    }

    /**
     * Display the specified resource.
     *
     * @param      \App\Models\Invoice $invoice  The invoice
     *
     * @return \Illuminate\Http\Response
     */
    public function show(ShowRecurringInvoiceRequest $request, RecurringInvoice $recurring_invoice)
    {

        $data = [
            'invoice' => $recurring_invoice->load('invoices'),
        ];
        
        return view('portal.default.recurring_invoices.show', $data);

    }


    public function requestCancellation(Request $request, RecurringInvoice $recurring_invoice)
    {

        $data = [
            'invoice' => $recurring_invoice
        ];

        //todo double check the user is able to request a cancellation
        //can add locale specific by chaining ->locale();
        $recurring_invoice->user->notify(new ClientContactRequestCancellation($recurring_invoice, auth()->user()));

        return view('portal.default.recurring_invoices.request_cancellation', $data);

    }

}
