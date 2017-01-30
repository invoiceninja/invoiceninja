<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Invoice;
use App\Ninja\Notifications\PushFactory;

/**
 * Class PushService.
 */
class PushService
{
    /**
     * @var PushFactory
     */
    protected $pushFactory;

    /**
     * @param PushFactory $pushFactory
     */
    public function __construct(PushFactory $pushFactory)
    {
        $this->pushFactory = $pushFactory;
    }

    /**
     * @param Invoice $invoice
     * @param $type
     */
    public function sendNotification(Invoice $invoice, $type)
    {
        //check user has registered for push notifications
        if (! $this->checkDeviceExists($invoice->account)) {
            return;
        }

        //Harvest an array of devices that are registered for this notification type
        $devices = json_decode($invoice->account->devices, true);

        foreach ($devices as $device) {
            if (($device["notify_{$type}"] == true) && ($device['device'] == 'ios') && IOS_DEVICE) {
                $this->pushMessage($invoice, $device['token'], $type, IOS_DEVICE);
            } elseif (($device["notify_{$type}"] == true) && ($device['device'] == 'fcm') && ANDROID_DEVICE) {
                $this->pushMessage($invoice, $device['token'], $type, ANDROID_DEVICE);
            }
        }
    }

    /**
     * pushMessage function.
     *
     * method to dispatch iOS notifications
     *
     * @param Invoice $invoice
     * @param $token
     * @param $type
     * @param mixed $device
     */
    private function pushMessage(Invoice $invoice, $token, $type, $device)
    {
        $this->pushFactory->message($token, $this->messageType($invoice, $type), $device);
    }

    /**
     * checkDeviceExists function.
     *
     * Returns a boolean if this account has devices registered for PUSH notifications
     *
     * @param Account $account
     *
     * @return bool
     */
    private function checkDeviceExists(Account $account)
    {
        $devices = json_decode($account->devices, true);

        if (count($devices) >= 1) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * messageType function.
     *
     * method which formats an appropriate message depending on message type
     *
     * @param Invoice $invoice
     * @param $type
     *
     * @return string
     */
    private function messageType(Invoice $invoice, $type)
    {
        switch ($type) {
            case 'sent':
                return $this->entitySentMessage($invoice);
                break;

            case 'paid':
                return $this->invoicePaidMessage($invoice);
                break;

            case 'approved':
                return $this->quoteApprovedMessage($invoice);
                break;

            case 'viewed':
                return $this->entityViewedMessage($invoice);
                break;
        }
    }

    /**
     * @param Invoice $invoice
     *
     * @return string
     */
    private function entitySentMessage(Invoice $invoice)
    {
        if ($invoice->isType(INVOICE_TYPE_QUOTE)) {
            return trans('texts.notification_quote_sent_subject', ['invoice' => $invoice->invoice_number, 'client' => $invoice->client->name]);
        } else {
            return trans('texts.notification_invoice_sent_subject', ['invoice' => $invoice->invoice_number, 'client' => $invoice->client->name]);
        }
    }

    /**
     * @param Invoice $invoice
     *
     * @return string
     */
    private function invoicePaidMessage(Invoice $invoice)
    {
        return trans('texts.notification_invoice_paid_subject', ['invoice' => $invoice->invoice_number, 'client' => $invoice->client->name]);
    }

    /**
     * @param Invoice $invoice
     *
     * @return string
     */
    private function quoteApprovedMessage(Invoice $invoice)
    {
        return trans('texts.notification_quote_approved_subject', ['invoice' => $invoice->invoice_number, 'client' => $invoice->client->name]);
    }

    /**
     * @param Invoice $invoice
     *
     * @return string
     */
    private function entityViewedMessage(Invoice $invoice)
    {
        if ($invoice->isType(INVOICE_TYPE_QUOTE)) {
            return trans('texts.notification_quote_viewed_subject', ['invoice' => $invoice->invoice_number, 'client' => $invoice->client->name]);
        } else {
            return trans('texts.notification_invoice_viewed_subject', ['invoice' => $invoice->invoice_number, 'client' => $invoice->client->name]);
        }
    }
}
