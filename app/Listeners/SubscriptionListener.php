<?php

namespace App\Listeners;

use App\Events\ClientWasCreated;
use App\Events\ClientWasUpdated;
use App\Events\ClientWasDeleted;
use App\Events\ExpenseWasCreated;
use App\Events\ExpenseWasUpdated;
use App\Events\ExpenseWasDeleted;
use App\Events\QuoteItemsWereCreated;
use App\Events\QuoteItemsWereUpdated;
use App\Events\QuoteWasDeleted;
use App\Events\QuoteInvitationWasApproved;
use App\Events\PaymentWasCreated;
use App\Events\PaymentWasDeleted;
use App\Events\InvoiceItemsWereCreated;
use App\Events\InvoiceItemsWereUpdated;
use App\Events\InvoiceWasDeleted;
use App\Events\VendorWasCreated;
use App\Events\VendorWasUpdated;
use App\Events\VendorWasDeleted;
use App\Events\TaskWasCreated;
use App\Events\TaskWasUpdated;
use App\Events\TaskWasDeleted;
use App\Models\EntityModel;
use App\Ninja\Serializers\ArraySerializer;
use App\Ninja\Transformers\ClientTransformer;
use App\Ninja\Transformers\InvoiceTransformer;
use App\Ninja\Transformers\PaymentTransformer;
use App\Ninja\Transformers\VendorTransformer;
use App\Ninja\Transformers\ExpenseTransformer;
use App\Ninja\Transformers\TaskTransformer;
use League\Fractal\Manager;
use League\Fractal\Resource\Item;
use Utils;

/**
 * Class SubscriptionListener.
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
     * @param ClientWasUpdated $event
     */
    public function updatedClient(ClientWasUpdated $event)
    {
        $transformer = new ClientTransformer($event->client->account);
        $this->checkSubscriptions(EVENT_UPDATE_CLIENT, $event->client, $transformer);
    }

    /**
     * @param ClientWasDeleted $event
     */
    public function deletedClient(ClientWasDeleted $event)
    {
        $transformer = new ClientTransformer($event->client->account);
        $this->checkSubscriptions(EVENT_DELETE_CLIENT, $event->client, $transformer);
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
     * @param PaymentWasDeleted $event
     */
    public function deletedPayment(PaymentWasDeleted $event)
    {
        $transformer = new PaymentTransformer($event->payment->account);
        $this->checkSubscriptions(EVENT_DELETE_PAYMENT, $event->payment, $transformer, [ENTITY_CLIENT, ENTITY_INVOICE]);
    }


    /**
     * @param InvoiceWasCreated $event
     */
    public function createdInvoice(InvoiceItemsWereCreated $event)
    {
        $transformer = new InvoiceTransformer($event->invoice->account);
        $this->checkSubscriptions(EVENT_CREATE_INVOICE, $event->invoice, $transformer, ENTITY_CLIENT);
    }

    /**
     * @param InvoiceWasUpdated $event
     */
    public function updatedInvoice(InvoiceItemsWereUpdated $event)
    {
        $transformer = new InvoiceTransformer($event->invoice->account);
        $this->checkSubscriptions(EVENT_UPDATE_INVOICE, $event->invoice, $transformer, ENTITY_CLIENT);
    }

    /**
     * @param InvoiceWasDeleted $event
     */
    public function deletedInvoice(InvoiceWasDeleted $event)
    {
        $transformer = new InvoiceTransformer($event->invoice->account);
        $this->checkSubscriptions(EVENT_DELETE_INVOICE, $event->invoice, $transformer, ENTITY_CLIENT);
    }


    /**
     * @param QuoteWasCreated $event
     */
    public function createdQuote(QuoteItemsWereCreated $event)
    {
        $transformer = new InvoiceTransformer($event->quote->account);
        $this->checkSubscriptions(EVENT_CREATE_QUOTE, $event->quote, $transformer, ENTITY_CLIENT);
    }

    /**
     * @param QuoteWasUpdated $event
     */
    public function updatedQuote(QuoteItemsWereUpdated $event)
    {
        $transformer = new InvoiceTransformer($event->quote->account);
        $this->checkSubscriptions(EVENT_UPDATE_QUOTE, $event->quote, $transformer, ENTITY_CLIENT);
    }

    /**
     * @param QuoteInvitationWasApproved $event
     */
    public function approvedQuote(QuoteInvitationWasApproved $event)
    {
        $transformer = new InvoiceTransformer($event->quote->account);
        $this->checkSubscriptions(EVENT_APPROVE_QUOTE, $event->quote, $transformer, ENTITY_CLIENT);
    }

    /**
     * @param InvoiceWasDeleted $event
     */
    public function deletedQuote(QuoteWasDeleted $event)
    {
        $transformer = new InvoiceTransformer($event->quote->account);
        $this->checkSubscriptions(EVENT_DELETE_QUOTE, $event->quote, $transformer, ENTITY_CLIENT);
    }


    /**
     * @param VendorWasCreated $event
     */
    public function createdVendor(VendorWasCreated $event)
    {
        $transformer = new VendorTransformer($event->vendor->account);
        $this->checkSubscriptions(EVENT_CREATE_VENDOR, $event->vendor, $transformer);
    }

    /**
     * @param VendorWasUpdated $event
     */
    public function updatedVendor(VendorWasUpdated $event)
    {
        $transformer = new VendorTransformer($event->vendor->account);
        $this->checkSubscriptions(EVENT_UPDATE_VENDOR, $event->vendor, $transformer);
    }

    /**
     * @param VendorWasDeleted $event
     */
    public function deletedVendor(VendorWasDeleted $event)
    {
        $transformer = new VendorTransformer($event->vendor->account);
        $this->checkSubscriptions(EVENT_DELETE_VENDOR, $event->vendor, $transformer);
    }


    /**
     * @param ExpenseWasCreated $event
     */
    public function createdExpense(ExpenseWasCreated $event)
    {
        $transformer = new ExpenseTransformer($event->expense->account);
        $this->checkSubscriptions(EVENT_CREATE_EXPENSE, $event->expense, $transformer);
    }

    /**
     * @param ExpenseWasUpdated $event
     */
    public function updatedExpense(ExpenseWasUpdated $event)
    {
        $transformer = new ExpenseTransformer($event->expense->account);
        $this->checkSubscriptions(EVENT_UPDATE_EXPENSE, $event->expense, $transformer);
    }

    /**
     * @param ExpenseWasDeleted $event
     */
    public function deletedExpense(ExpenseWasDeleted $event)
    {
        $transformer = new ExpenseTransformer($event->expense->account);
        $this->checkSubscriptions(EVENT_DELETE_EXPENSE, $event->expense, $transformer);
    }


    /**
     * @param TaskWasCreated $event
     */
    public function createdTask(TaskWasCreated $event)
    {
        $transformer = new TaskTransformer($event->task->account);
        $this->checkSubscriptions(EVENT_CREATE_TASK, $event->task, $transformer);
    }

    /**
     * @param TaskWasUpdated $event
     */
    public function updatedTask(TaskWasUpdated $event)
    {
        $transformer = new TaskTransformer($event->task->account);
        $this->checkSubscriptions(EVENT_UPDATE_TASK, $event->task, $transformer);
    }

    /**
     * @param TaskWasDeleted $event
     */
    public function deletedTask(TaskWasDeleted $event)
    {
        $transformer = new TaskTransformer($event->task->account);
        $this->checkSubscriptions(EVENT_DELETE_TASK, $event->task, $transformer);
    }


    /**
     * @param $eventId
     * @param $entity
     * @param $transformer
     * @param string $include
     */
    private function checkSubscriptions($eventId, $entity, $transformer, $include = '')
    {
        if (! EntityModel::$notifySubscriptions) {
            return;
        }

        $subscriptions = $entity->account->getSubscriptions($eventId);

        if (! $subscriptions->count()) {
            return;
        }

        // generate JSON data
        $manager = new Manager();
        $manager->setSerializer(new ArraySerializer());
        $manager->parseIncludes($include);

        $resource = new Item($entity, $transformer, $entity->getEntityType());
        $jsonData = $manager->createData($resource)->toArray();

        // For legacy Zapier support
        if (isset($jsonData['client_id']) && $jsonData['client_id'] != 0) {
            $jsonData['client_name'] = $entity->client->getDisplayName();
        }

        foreach ($subscriptions as $subscription) {
            switch ($subscription->format) {
                case SUBSCRIPTION_FORMAT_JSON:
                    $data = $jsonData;
                    break;
                case SUBSCRIPTION_FORMAT_UBL:
                    $data = $ublData;
                    break;
            }
            self::notifySubscription($subscription, $data);
        }
    }

    private static function notifySubscription($subscription, $data)
    {
        $curl = curl_init();
        $jsonEncodedData = json_encode($data);
        $url = $subscription->target_url;

        if (! Utils::isNinja() && $secret = env('SUBSCRIPTION_SECRET')) {
            $url .= '?secret=' . $secret;
        }

        $opts = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => $jsonEncodedData,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Content-Length: '.strlen($jsonEncodedData)],
        ];

        curl_setopt_array($curl, $opts);

        $result = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        if ($status == 410) {
            $subscription->delete();
        }
    }

}
