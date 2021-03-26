<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Repositories;


use App\Factory\InvoiceFactory;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Invoice;
use App\Models\InvoiceInvitation;
use App\Models\Subscription;
use App\Utils\Traits\CleanLineItems;
use Illuminate\Support\Facades\DB;

class SubscriptionRepository extends BaseRepository
{
    use CleanLineItems;

    public function save($data, Subscription $subscription): ?Subscription
    {
        $subscription->fill($data);
            
        $subscription->price = $this->calculatePrice($subscription);

        $subscription->save();

        return $subscription;
    }

    private function calculatePrices($subscription) :array
    {

		DB::beginTransaction();

		$data = [];

        $client = Client::factory()->create([
                'user_id' => $subscription->user_id,
                'company_id' => $subscription->company_id,
                'group_settings_id' => $subscription->group_id,
                'country_id' => $subscription->company->settings->country_id,
            ]);

        $contact = ClientContact::factory()->create([
                'user_id' => $subscription->user_id,
                'company_id' => $subscription->company_id,
                'client_id' => $client->id,
                'is_primary' => 1,
                'send_email' => true,
            ]);

        $invoice = InvoiceFactory::create($subscription->company_id, $subscription->user_id);

        $invitation = InvoiceInvitation::factory()->create([
	                'user_id' => $subscription->user_id,
	                'company_id' => $subscription->company_id,
                    'invoice_id' => $invoice->id,
                    'client_contact_id' => $contact->id,
        ]);

        $invoice->setRelation('invitations', $invitation);
        $invoice->setRelation('client', $client);
        $invoice->setRelation('company', $subscription->company);
        $invoice->load('client');

        $invoice->line_items = $this->generateLineItems($subscription);
        $data['price'] = $invoice->calc()->getTotal();
        
        $invoice->discount = $subscription->promo_discount;
        $invoice->is_amount_discount = $subscription->is_amount_discount;

        $data['promo_price'] = $invoice->calc()->getTotal();

        DB::rollBack();

        return $data;
    }

    private function generateLineItems($subscription)
    {

    	$line_items = [];

    	$line_items = $this->cleanItems($line_items);

    }

}