<?php

namespace App\Ninja\Notifications;

use Illuminate\Http\Request;

/**
 * Class PushService
 * @package App\Ninja\Notifications
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
     * @param $type - Type of notification, ie. Quote APPROVED, Invoice PAID, Invoice SENT, Invoice VIEWED
     */

    public function sendNotification($invoice, $type)
    {
        //check user has registered for push notifications
        if(!$this->checkDeviceExists($invoice->account))
            return;

        //Harvest an array of devices that are registered for this notification type

        //loop and send

    }

    private function checkDeviceExists($account)
    {
        $devices = json_decode($account->devices);

        
    }
}