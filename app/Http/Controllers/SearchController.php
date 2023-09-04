<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Client;
use App\Models\ClientContact;
use App\Http\Requests\Search\GenericSearchRequest;
use App\Models\Invoice;

class SearchController extends Controller
{
    public function __invoke(GenericSearchRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        // $search = collect([Client::class, Invoice::class])->each(function )
        return response()->json([
            'clients' => $this->clientMap($user),
            'client_contacts' => $this->clientContactMap($user),
            'invoices' => $this->invoiceMap($user),
        ], 200);

    }

    private function clientMap(User $user) {

        return Client::query()
                     ->company()
                     ->when($user->cannot('view_all') || $user->cannot('view_client'), function ($query) use($user) {
                         $query->where('user_id', $user->id);
                     })
                     ->cursor()
                     ->map(function ($client){
                        return [
                            'name' => $client->present()->name(), 
                            'type' => 'client', 
                            'id' => $client->hashed_id,
                            'path' => "clients/{$client->hashed_id}/edit"
                        ];
                     });
    }

    private function clientContactMap(User $user) {

        return ClientContact::query()
                     ->company()
                     ->with('client')
                     ->when($user->cannot('view_all') || $user->cannot('view_client'), function ($query) use($user) {
                         $query->where('user_id', $user->id);
                     })
                     ->cursor()
                     ->map(function ($contact){
                        return [
                            'name' => $contact->present()->search_display(), 
                            'type' => 'client_contact', 
                            'id' => $contact->client->hashed_id,
                            'path' => "clients/{$contact->client->hashed_id}"
                        ];
                     });
    }

    private function invoiceMap(User $user) {

        return Invoice::query()
                     ->company()
                     ->with('client')
                     ->when($user->cannot('view_all') || $user->cannot('view_invoice'), function ($query) use($user) {
                         $query->where('user_id', $user->id);
                     })
                     ->cursor()
                     ->map(function ($invoice){
                        return [
                            'name' => $invoice->client->present()->name() . ' - ' . $invoice->number, 
                            'type' => 'invoice', 
                            'id' => $invoice->hashed_id,
                            'path' => "clients/{$invoice->hashed_id}/edit"
                        ];
                     });
    }
}
