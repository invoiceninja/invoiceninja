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
     * Show the list of recurring invoices.
     *
     * @param Builder $builder
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index(Builder $builder)
    {
        $invoices = RecurringInvoice::whereClientId(auth()->user()->client->id)
            ->whereIn('status_id', [RecurringInvoice::STATUS_PENDING, RecurringInvoice::STATUS_ACTIVE, RecurringInvoice::STATUS_COMPLETED])
            ->orderBy('status_id', 'asc')
            ->with('client')
            ->paginate(10);

        return $this->render('recurring_invoices.index', [
            'invoices' => $invoices,
        ]);
    }

    /**
     * Display the recurring invoice.
     *
     * @param ShowRecurringInvoiceRequest $request
     * @param RecurringInvoice $recurring_invoice
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function show(ShowRecurringInvoiceRequest $request, RecurringInvoice $recurring_invoice)
    {
        return $this->render('recurring_invoices.show', [
            'invoice' => $recurring_invoice->load('invoices'),
        ]);
    }

    public function requestCancellation(Request $request, RecurringInvoice $recurring_invoice)
    {
        //todo double check the user is able to request a cancellation
        //can add locale specific by chaining ->locale();
        $recurring_invoice->user->notify(new ClientContactRequestCancellation($recurring_invoice, auth()->user()));

        return $this->render('recurring_invoices.cancellation.index', [
            'invoice' => $recurring_invoice,
        ]);
    }
}
