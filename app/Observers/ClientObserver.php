<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Observers;

use App\Jobs\Client\CheckVat;
use App\Jobs\Client\UpdateTaxData;
use App\Jobs\Util\WebhookHandler;
use App\Models\Client;
use App\Models\Webhook;

class ClientObserver
{
    public $afterCommit = true;

    /** Numeric country_ids for EU member states — mirrors BaseRule::$eu_country_codes */
    private array $eu_country_ids = [
        40, 56, 100, 196, 203, 276, 208, 233, 724, 246, 250, 300,
        191, 348, 372, 380, 440, 442, 428, 470, 528, 616, 620, 642,
        752, 705, 703,
    ];

    /**
     * Handle the client "created" event.
     *
     * @param Client $client
     * @return void
     */
    public function created(Client $client)
    {
        /** Fix Tax Data for Clients */
        if ($client->country_id == 840 && $client->company->calculate_taxes && !$client->company->account->isFreeHostedClient()) {
            UpdateTaxData::dispatch($client, $client->company);
        }

        /** Check VAT records for client */
        if (in_array($client->country_id, $this->eu_country_ids) && $client->company->calculate_taxes) {
            CheckVat::dispatch($client, $client->company);
        }

        $subscriptions = Webhook::where('company_id', $client->company_id)
                                    ->where('event_id', Webhook::EVENT_CREATE_CLIENT)
                                    ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch(Webhook::EVENT_CREATE_CLIENT, $client, $client->company)->delay(2);
        }

        // Only push to QuickBooks if:
        // 1. QuickBooks is connected and client sync is enabled
        // 2. We're NOT currently importing from QuickBooks (prevent circular sync)
        if ($client->company->shouldPushToQuickbooks('client')
           && empty(\App\Services\Quickbooks\QuickbooksService::$importing[$client->company_id])) {
            \App\Jobs\Quickbooks\PushToQuickbooks::dispatch(
                'client',
                $client->id,
                $client->company->db,
            );
        }
    }

    /**
     * Handle the client "updated" event.
     *
     * @param Client $client
     * @return void
     */
    public function updated(Client $client)
    {
        
        /** Monitor postal code changes for US based clients for tax calculations */
        if (($client->getOriginal('shipping_postal_code') != $client->shipping_postal_code || $client->getOriginal('postal_code') != $client->postal_code) && $client->country_id == 840 && $client->company->calculate_taxes && !$client->company->account->isFreeHostedClient()) {
            UpdateTaxData::dispatch($client, $client->company);
        }

        /** Monitor vat numbers for EU based clients for tax calculations */
        if ($client->getOriginal('vat_number') != $client->vat_number && in_array($client->country_id, $this->eu_country_ids) && $client->company->calculate_taxes) {
            CheckVat::dispatch($client, $client->company);
        }

        $event = Webhook::EVENT_UPDATE_CLIENT;

        if ($client->getOriginal('deleted_at') && !$client->deleted_at) {
            $event = Webhook::EVENT_RESTORE_CLIENT;
        }

        if ($client->is_deleted) {
            $event = Webhook::EVENT_DELETE_CLIENT;
        }

        $subscriptions = Webhook::where('company_id', $client->company_id)
                                    ->where('event_id', $event)
                                    ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch($event, $client, $client->company, 'client')->delay(2);
        }

        // Only push to QuickBooks if:
        // 1. QuickBooks is connected and client sync is enabled
        // 2. We're NOT currently importing from QuickBooks (prevent circular sync)
        // 3. Only financial fields changed (not balance fields which are auto-calculated)
        if ($client->company->shouldPushToQuickbooks('client')
           && empty(\App\Services\Quickbooks\QuickbooksService::$importing[$client->company_id])
           && !$client->isDirty(['paid_to_date','balance','credit_balance','payment_balance'])) {
            \App\Jobs\Quickbooks\PushToQuickbooks::dispatch(
                'client',
                $client->id,
                $client->company->db,
            );
        }
    }

    /**
     * Handle the client "archived" event.
     *
     * @param Client $client
     * @return void
     */
    public function deleted(Client $client)
    {
        if ($client->is_deleted) {
            return;
        }

        $subscriptions = Webhook::where('company_id', $client->company_id)
                                    ->where('event_id', Webhook::EVENT_ARCHIVE_CLIENT)
                                    ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch(Webhook::EVENT_ARCHIVE_CLIENT, $client, $client->company)->delay(2);
        }
    }

}
