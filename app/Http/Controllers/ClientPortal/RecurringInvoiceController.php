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

use Illuminate\Http\Request;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\MakesDates;
use App\Models\RecurringInvoice;
use App\Http\Controllers\Controller;
use App\Notifications\ClientContactRequestCancellation;
use App\Http\Requests\ClientPortal\ShowRecurringInvoiceRequest;

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
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index()
    {
        return $this->render('recurring_invoices.index');
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
