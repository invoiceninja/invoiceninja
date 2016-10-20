<?php namespace App\Listeners;

use Utils;
use App\Models\EntityModel;
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

/**
 * Class SubscriptionListener
 */
class SubscriptionListener
{
    /**
     * @param ClientWasCreated $event
     */
    public function createdClient(ClientWasCreated $event)
    {
        $transformer = new ClientTransformer($event->client->account);
        $this->checkSubscriptions(EVENT_CREATE_CLIENT, $event->client, $transformer);
    }

    /**
     * @param QuoteWasCreated $event
     */
    public function createdQuote(QuoteWasCreated $event)
    {
        $transformer = new InvoiceTransformer($event->quote->account);
        $this->checkSubscriptions(EVENT_CREATE_QUOTE, $event->quote, $transformer, ENTITY_CLIENT);
    }

    /**
     * @param PaymentWasCreated $event
     */
    public function createdPayment(PaymentWasCreated $event)
    {
        $transformer = new PaymentTransformer($event->payment->account);
        $this->checkSubscriptions(EVENT_CREATE_PAYMENT, $event->payment, $transformer, [ENTITY_CLIENT, ENTITY_INVOICE]);
    }

    /**
     * @param InvoiceWasCreated $event
     */
    public function createdInvoice(InvoiceWasCreated $event)
    {
        $transformer = new InvoiceTransformer($event->invoice->account);
        $this->checkSubscriptions(EVENT_CREATE_INVOICE, $event->invoice, $transformer, ENTITY_CLIENT);
    }

    /**
     * @param CreditWasCreated $event
     */
    public function createdCredit(CreditWasCreated $event)
    {

    }

    /**
     * @param VendorWasCreated $event
     */
    public function createdVendor(VendorWasCreated $event)
    {

    }

    /**
     * @param ExpenseWasCreated $event
     */
    public function createdExpense(ExpenseWasCreated $event)
    {

    }

    /**
     * @param $eventId
     * @param $entity
     * @param $transformer
     * @param string $include
     */
    private function checkSubscriptions($eventId, $entity, $transformer, $include = '')
    {
        if ( ! EntityModel::$notifySubscriptions) {
            return;
        }

        $subscription = $entity->account->getSubscription($eventId);

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
