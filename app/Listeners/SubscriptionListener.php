<?php namespace app\Listeners;
use Auth;
use Utils;
use App\Events\ClientWasCreated;
use App\Events\QuoteWasCreated;
use App\Events\InvoiceWasCreated;
use App\Events\CreditWasCreated;
use App\Events\PaymentWasCreated;
use App\Events\VendorWasCreated;
use App\Events\ExpenseWasCreated;
use App\Ninja\Transformers\InvoiceTransformer;
use App\Ninja\Transformers\ClientTransformer;
use App\Ninja\Transformers\PaymentTransformer;
use League\Fractal\Manager;
use League\Fractal\Resource\Item;
use App\Ninja\Serializers\ArraySerializer;
class SubscriptionListener
{
    public function createdClient(ClientWasCreated $event)
    {
        $transformer = new ClientTransformer($event->client->account);
        $this->checkSubscriptions(ACTIVITY_TYPE_CREATE_CLIENT, $event->client, $transformer);
    }
    public function createdQuote(QuoteWasCreated $event)
    {
        $transformer = new InvoiceTransformer($event->quote->account);
        $this->checkSubscriptions(ACTIVITY_TYPE_CREATE_QUOTE, $event->quote, $transformer, ENTITY_CLIENT);
    }
    public function createdPayment(PaymentWasCreated $event)
    {
        $transformer = new PaymentTransformer($event->payment->account);
        $this->checkSubscriptions(ACTIVITY_TYPE_CREATE_PAYMENT, $event->payment, $transformer, [ENTITY_CLIENT, ENTITY_INVOICE]);
    }
    public function createdInvoice(InvoiceWasCreated $event)
    {
        $transformer = new InvoiceTransformer($event->invoice->account);
        $this->checkSubscriptions(ACTIVITY_TYPE_CREATE_INVOICE, $event->invoice, $transformer, ENTITY_CLIENT);
    }
    public function createdCredit(CreditWasCreated $event)
    {
        //$this->checkSubscriptions(ACTIVITY_TYPE_CREATE_CREDIT, $event->credit);
    }
    public function createdVendor(VendorWasCreated $event)
    {
        //$this->checkSubscriptions(ACTIVITY_TYPE_CREATE_VENDOR, $event->vendor);
    }
    public function createdExpense(ExpenseWasCreated $event)
    {
        //$this->checkSubscriptions(ACTIVITY_TYPE_CREATE_EXPENSE, $event->expense);
    }
    private function checkSubscriptions($activityTypeId, $entity, $transformer, $include = '')
    {
        $subscription = $entity->account->getSubscription($activityTypeId);
        if ($subscription) {
            $manager = new Manager();
            $manager->setSerializer(new ArraySerializer());
            $manager->parseIncludes($include);
            $resource = new Item($entity, $transformer, $entity->getEntityType());
            $data = $manager->createData($resource)->toArray();
            // For legacy Zapier support
            if (isset($data['client_id'])) {
                $data['client_name'] = $entity->client->getDisplayName();
            }
            Utils::notifyZapier($subscription, $data);
        }
    }
}