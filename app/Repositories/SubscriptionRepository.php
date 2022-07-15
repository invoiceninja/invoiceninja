<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Repositories;

use App\DataMapper\ClientSettings;
use App\DataMapper\InvoiceItem;
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

    public int $quantity = 1;

    public function save($data, Subscription $subscription): ?Subscription
    {
        $subscription->fill($data);

        $calculated_prices = $this->calculatePrice($subscription);

        $subscription->price = $calculated_prices['price'];
        $subscription->promo_price = $calculated_prices['promo_price'];

        $subscription->save();

        return $subscription;
    }

    private function calculatePrice($subscription) :array
    {

        // DB::beginTransaction();
        DB::connection(config('database.default'))->beginTransaction();
        $data = [];

        $client = Client::factory()->create([
            'user_id' => $subscription->user_id,
            'company_id' => $subscription->company_id,
            'group_settings_id' => $subscription->group_id,
            'country_id' => $subscription->company->settings->country_id,
            'settings' => ClientSettings::defaults(),
        ]);

        $contact = ClientContact::factory()->create([
            'user_id' => $subscription->user_id,
            'company_id' => $subscription->company_id,
            'client_id' => $client->id,
            'is_primary' => 1,
            'send_email' => true,
        ]);

        $invoice = InvoiceFactory::create($subscription->company_id, $subscription->user_id);
        $invoice->client_id = $client->id;

        $invoice->save();

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

        // DB::rollBack();
        DB::connection(config('database.default'))->rollBack();

        return $data;
    }

    public function generateLineItems($subscription, $is_recurring = false, $is_credit = false)
    {
        $multiplier = $is_credit ? -1 : 1;

        $line_items = [];

        if (! $is_recurring) {
            foreach ($subscription->service()->products() as $product) {
                $line_items[] = (array) $this->makeLineItem($product, $multiplier);
            }
        }

        foreach ($subscription->service()->recurring_products() as $product) {
            $line_items[] = (array) $this->makeLineItem($product, $multiplier);
        }

        $line_items = $this->cleanItems($line_items);

        return $line_items;
    }

    private function makeLineItem($product, $multiplier)
    {
        $item = new InvoiceItem;
        $item->quantity = $this->quantity;
        $item->product_key = $product->product_key;
        $item->notes = $product->notes;
        $item->cost = $product->price * $multiplier;
        $item->tax_rate1 = $product->tax_rate1 ?: 0;
        $item->tax_name1 = $product->tax_name1 ?: '';
        $item->tax_rate2 = $product->tax_rate2 ?: 0;
        $item->tax_name2 = $product->tax_name2 ?: '';
        $item->tax_rate3 = $product->tax_rate3 ?: 0;
        $item->tax_name3 = $product->tax_name3 ?: '';
        $item->custom_value1 = $product->custom_value1 ?: '';
        $item->custom_value2 = $product->custom_value2 ?: '';
        $item->custom_value3 = $product->custom_value3 ?: '';
        $item->custom_value4 = $product->custom_value4 ?: '';

        return $item;
    }
}
