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

use App\Models\User;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;

class UserQualityNotification extends Notification
{
    /**
     * Create a new notification instance.
     *
     * @return void
     */

    protected User $user;

    protected string $account_key;

    public function __construct(User $user, string $account_key)
    {
        $this->user = $user;
        $this->account_key = $account_key;
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
        $content = "User Quality notification {$this->user->present()->name()} \n";
        $content .= "Account: {$this->account_key }\n";

        return (new SlackMessage())
                ->success()
                ->from(ctrans('texts.notification_bot'))
                ->image('https://app.invoiceninja.com/favicon.png')
                ->content($content);
    }
}
