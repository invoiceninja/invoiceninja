<?php

namespace App\Http\ViewComposers;

use DB;
use App\Models\Contact;
use Illuminate\View\View;

/**
 * ClientPortalHeaderComposer.php.
 *
 * @copyright See LICENSE file that was distributed with this source code.
 */
class ClientPortalHeaderComposer
{
    /**
     * Bind data to the view.
     *
     * @param View $view
     *
     * @return void
     */
    public function compose(View $view)
    {
        $contactKey = session('contact_key');

        if (! $contactKey) {
            return false;
        }

        $contact = Contact::where('contact_key', '=', $contactKey)
                        ->with('client')
                        ->first();

        if (! $contact || $contact->is_deleted) {
            return false;
        }

        $client = $contact->client;
        $account = $contact->account;

        $hasDocuments = DB::table('invoices')
                            ->where('invoices.client_id', '=', $client->id)
                            ->whereNull('invoices.deleted_at')
                            ->join('documents', 'documents.invoice_id', '=', 'invoices.id')
                            ->count();

        $hasPaymentMethods = false;
        if ($account->getTokenGatewayId() && ! $account->enable_client_portal_dashboard) {
            $hasPaymentMethods = DB::table('payment_methods')
                                    ->where('contacts.client_id', '=', $client->id)
                                    ->whereNull('payment_methods.deleted_at')
                                    ->join('contacts', 'contacts.id', '=', 'payment_methods.contact_id')
                                    ->count();
        }

        $view->with('hasQuotes', $client->publicQuotes->count());
        $view->with('hasCredits', $client->creditsWithBalance->count());
        $view->with('hasDocuments', $hasDocuments);
        $view->with('hasPaymentMethods', $hasPaymentMethods);
    }
}
