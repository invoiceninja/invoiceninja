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

namespace App\Services\Subscription;

use App\Models\Client;
use App\Libraries\MultiDB;
use App\Models\Subscription;
use App\Models\RecurringInvoice;
use App\Services\AbstractService;
use App\Factory\RecurringInvoiceFactory;
use App\Repositories\SubscriptionRepository;

class InvoiceToRecurring extends AbstractService
{
    protected \App\Services\Subscription\SubscriptionStatus $status;

    public function __construct(protected int $client_id, public Subscription $subscription, public array $bundle = [])
    {
    }


    public function run(): RecurringInvoice
    {

        MultiDB::setDb($this->subscription->company->db);

        $client = Client::withTrashed()->find($this->client_id);

        $subscription_repo = new SubscriptionRepository();

        $line_items = count($this->bundle) > 1 ? $subscription_repo->generateBundleLineItems($this->bundle, true, false) : $subscription_repo->generateLineItems($this->subscription, true, false);

        $recurring_invoice = RecurringInvoiceFactory::create($this->subscription->company_id, $this->subscription->user_id);
        $recurring_invoice->client_id = $this->client_id;
        $recurring_invoice->line_items = $line_items;
        $recurring_invoice->subscription_id = $this->subscription->id;
        $recurring_invoice->frequency_id = $this->subscription->frequency_id ?: RecurringInvoice::FREQUENCY_MONTHLY;
        $recurring_invoice->date = now();
        $recurring_invoice->remaining_cycles = -1;
        $recurring_invoice->auto_bill = $client->getSetting('auto_bill');
        $recurring_invoice->auto_bill_enabled =  $this->setAutoBillFlag($recurring_invoice->auto_bill);
        $recurring_invoice->due_date_days = 'terms';
        $recurring_invoice->next_send_date = now()->format('Y-m-d');
        $recurring_invoice->next_send_date_client = now()->format('Y-m-d');
        $recurring_invoice->next_send_date =  $recurring_invoice->nextSendDate();
        $recurring_invoice->next_send_date_client = $recurring_invoice->nextSendDateClient();

        return $recurring_invoice;

    }

    private function setAutoBillFlag($auto_bill): bool
    {
        if ($auto_bill == 'always' || $auto_bill == 'optout') {
            return true;
        }

        return false;
    }
}
