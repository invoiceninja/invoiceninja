<?php namespace app\Listeners;

use Auth;
use Utils;

use App\Events\ClientWasCreated;
use App\Events\QuoteWasCreated;
use App\Events\InvoiceWasCreated;
use App\Events\CreditWasCreated;
use App\Events\PaymentWasCreated;

class SubscriptionListener
{
    public function createdClient(ClientWasCreated $event)
    {
        $this->checkSubscriptions(ACTIVITY_TYPE_CREATE_CLIENT, $event->client);
    }

    public function createdQuote(QuoteWasCreated $event)
    {
        $this->checkSubscriptions(ACTIVITY_TYPE_CREATE_QUOTE, $event->quote);
    }

    public function createdPayment(PaymentWasCreated $event)
    {
        $this->checkSubscriptions(ACTIVITY_TYPE_CREATE_PAYMENT, $event->payment);
    }

    public function createdCredit(CreditWasCreated $event)
    {
        $this->checkSubscriptions(ACTIVITY_TYPE_CREATE_CREDIT, $event->credit);
    }

    public function createdInvoice(InvoiceWasCreated $event)
    {
        $this->checkSubscriptions(ACTIVITY_TYPE_CREATE_INVOICE, $event->invoice);
    }

    private function checkSubscriptions($activityTypeId, $entity)
    {
        $subscription = $entity->account->getSubscription($activityTypeId);

        if ($subscription) {
            Utils::notifyZapier($subscription, $entity);
        }
    }
}
