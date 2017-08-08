<?php

namespace App\Ninja\Notifications;

use Davibennun\LaravelPushNotification\Facades\PushNotification;
use Illuminate\Support\Facades\Log;

/**
 * Class PushFactory.
 */
class PushFactory
{
    /**
     * PushFactory constructor.
     */
    public function __construct()
    {
    }

    /**
     * customMessage function.
     *
     * Send a message with a nested custom payload to perform additional trickery within application
     *
     *
     * @param $token
     * @param $message
     * @param $messageArray
     * @param string $device - Type of device the message is being pushed to.
     *
     * @return void
     */
    public function customMessage($token, $message, $messageArray, $device)
    {
        $customMessage = PushNotification::Message($message, $messageArray);

        $this->message($token, $customMessage, $device);
    }

    /**
     * message function.
     *
     * Send a plain text only message to a single device.
     *
     *
     * @param $token - device token
     * @param $message - user specific message
     * @param mixed $device
     *
     * @return void
     */
    public function message($token, $message, $device)
    {
        try {
            PushNotification::app($device)
                ->to($token)
                ->send($message);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
    }

    /**
     * getFeedback function.
     *
     * Returns an array of expired/invalid tokens to be removed from iOS PUSH notifications.
     *
     * We need to run this once ~ 24hrs
     *
     *
     * @param string $token   - A valid token (can be any valid token)
     * @param string $message - Nil value for message
     * @param string $device  - Type of device the message is being pushed to.
     *
     * @return array
     */
    public function getFeedback($token, $message, $device)
    {
        $feedback = PushNotification::app($device)
            ->to($token)
            ->send($message);

        return $feedback->getFeedback();
    }
}
