<?php

namespace App\Http\ViewComposers;

use DB;
use Cache;
use Illuminate\View\View;
use App\Models\Contact;

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
     * @param  View  $view
     *
     * @return void
     */
    public function compose(View $view)
    {
        $contactKey = session('contact_key');

        if ( ! $contactKey) {
            return false;
        }

        $contact = Contact::where('contact_key', '=', $contactKey)
                        ->with('client')
                        ->first();

        if ( ! $contact || $contact->is_deleted) {
            return false;
        }

        $client = $contact->client;

        $hasDocuments = DB::table('invoices')
                            ->where('invoices.client_id', '=', $client->id)
                            ->whereNull('invoices.deleted_at')
                            ->join('documents', 'documents.invoice_id', '=', 'invoices.id')
                            ->count();

        $view->with('hasQuotes', $client->publicQuotes->count());
        $view->with('hasCredits', $client->creditsWithBalance->count());
        $view->with('hasDocuments', $hasDocuments);
    }
}
