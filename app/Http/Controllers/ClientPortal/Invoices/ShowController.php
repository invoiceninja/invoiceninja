<?php

namespace App\Http\Controllers\ClientPortal\Invoices;

use App\Models\Invoice;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Request;

class ShowController extends Controller
{
    /**
     * Show the invoice outside client portal.
     *
     * @param \App\Models\Invoice $invoice
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index(Invoice $invoice)
    {
        $contact = $invoice->client->contacts()->first();

        if (!session("INVOICE_VIEW_{$invoice->hashed_id}")) {
            return redirect()->route('client.show_invoice.password', $invoice->hashed_id);
        }
        
        if (is_null($contact->password) || empty($contact->password)) {
            return redirect("/client/password/reset?email={$contact->email}");
        }

        return $this->render('invoices.view', [
            'root' => 'themes',
            'invoice' => $invoice,
        ]);
    }

    /**
     * Show the form for entering password.
     *
     * @param \App\Models\Invoice $invoice
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function password(Invoice $invoice)
    {
        return $this->render('invoices.password', [
            'root' => 'themes',
            'invoice' => $invoice,
        ]);
    }

    /**
     * Handle the password check.
     *
     * @param \App\Models\Invoice $invoice
`     * @return \Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse
     */
    public function handlePassword(Invoice $invoice)
    {
        $contact = $invoice->client->contacts()->first();
        $check = Hash::check(request()->password, $contact->password);

        if ($check) {
            session()->flash("INVOICE_VIEW_{$invoice->hashed_id}", true);
            session()->put("VIEW_{$invoice->hashed_id}");
            
            return redirect()->route('client.show_invoice', $invoice->hashed_id);
        }

        session()->flash('INVOICE_VIEW_PASSWORD_FAILED', true);
        return back();
    }
}
