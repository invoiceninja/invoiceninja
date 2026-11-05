<?php

namespace App\Helpers\Mail;

use Illuminate\Mail\Mailable;

class EmailSettingsHeaders
{
    public static function apply(Mailable $mailable, ?object $settings): void
    {
        $address = $settings?->disposition_notification_to ?? '';

        if (empty($address)) {
            return;
        }

        $mailable->withSymfonyMessage(function ($message) use ($address) {
            $message->getHeaders()->addTextHeader('Disposition-Notification-To', $address);
            $message->getHeaders()->addTextHeader('Return-Receipt-To', $address);
        });
    }
}
