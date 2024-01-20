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

namespace App\Notifications\Ninja;

use App\Models\Account;
use App\Models\Client;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;

class PayPalUnlinkedTransaction extends Notification
{
    /**
     * Create a new notification instance.
     *
     * @return void
     */

    public function __construct(private string $order_id, private string $transaction_reference)
    {
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['slack'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     *
     */
    public function toMail($notifiable)
    {
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }

    public function toSlack($notifiable)
    {
        $content = "PayPal Order Not Found\n";
        $content .= "{$this->order_id}\n";
        $content .= "Transaction ref: {$this->transaction_reference}\n";


        return (new SlackMessage())
                ->success()
                ->from(ctrans('texts.notification_bot'))
                ->image('https://app.invoiceninja.com/favicon.png')
                ->content($content);
    }
}
