<?php

namespace App\Ninja\Notifications;

use Illuminate\Http\Request;

/**
 * Class PushService
 * @package App\Ninja\Notifications
 */


/**
 * $account->devices Definition
 *
 * @param string token
 * @param bool notify_sent
 * @param bool notify_paid
 * @param bool notify_approved
 * @param bool notify_viewed
 */

class PushService
{
    protected $pushFactory;

    public function __construct(PushFactory $pushFactory)
    {
        $this->pushFactory = $pushFactory;
    }

    /**
     * @param $invoice - Invoice object
     * @param $type - Type of notification, ie. Quote APPROVED, Invoice PAID, Invoice/Quote SENT, Invoice/Quote VIEWED
     */

    public function sendNotification($invoice, $type)
    {
        //check user has registered for push notifications
        if(!$this->checkDeviceExists($invoice->account))
            return;

        //Harvest an array of devices that are registered for this notification type
        $devices = json_decode($invoice->account->devices, TRUE);

        foreach($devices as $device)
        {
            if($device["notify_{$type}"] == TRUE)
                $this->pushMessage($invoice, $device['token'], $device["notify_{$type}"]);
        }


    }


    private function pushMessage($invoice, $token, $type)
    {
        $this->pushFactory->message($token, $this->messageType($invoice, $type));
    }


    /**
     * checkDeviceExists function
     *
     * Returns a boolean if this account has devices registered for PUSH notifications
     *
     * @param $account
     * @return bool
     */
    private function checkDeviceExists($account)
    {
        $devices = json_decode($account->devices, TRUE);

        if(count($devices) >= 1)
            return TRUE;
        else
            return FALSE;
    }

    private function messageType($invoice, $type)
    {
        switch($type)
        {
            case 'notify_sent':
                return $this->entitySentMessage($invoice);
                break;

            case 'notify_paid':
                return $this->invoicePaidMessage($invoice);
                break;

            case 'notify_approved':
                return $this->quoteApprovedMessage($invoice);
                break;

            case 'notify_viewed':
                return $this->entityViewedMessage($invoice);
                break;
        }
    }

    private function entitySentMessage($invoice)
    {
        if($invoice->is_quote)
            return 'Quote #{$invoice->invoice_number} sent!';
        else
            return 'Invoice #{$invoice->invoice_number} sent!';

    }

    private function invoicePaidMessage($invoice)
    {
        return 'Invoice #{$invoice->invoice_number} paid!';
    }

    private function quoteApprovedMessage($invoice)
    {
        return 'Quote #{$invoice->invoice_number} approved!';
    }

    private function entityViewedMessage($invoice)
    {
        if($invoice->is_quote)
            return 'Quote #{$invoice->invoice_number} viewed!';
        else
            return 'Invoice #{$invoice->invoice_number} viewed!';

    }



}