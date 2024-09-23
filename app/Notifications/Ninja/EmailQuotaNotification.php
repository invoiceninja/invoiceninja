<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Notifications\Ninja;

use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;

class EmailQuotaNotification extends Notification
{
    /**
     * Create a new notification instance.
     *
     * @return void
     */
    protected $account;

    public function __construct($account)
    {
        $this->account = $account;
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
        $content = "Email quota exceeded by Account {$this->account->key} \n";

        $owner = $this->account->companies()->first()->owner() ?? $this->account->users()->orderBy('id', 'asc')->first();
        $owner_name = $owner->present()->name() ?? 'No Owner Found';
        $owner_email = $owner->email ?? 'No Owner Email Found';

        $content .= "Owner {$owner_name} | {$owner_email}";

        return (new SlackMessage())
                ->success()
                ->from(ctrans('texts.notification_bot'))
                ->image('https://app.invoiceninja.com/favicon.png')
                ->content($content);
    }
}
