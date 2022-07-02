<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Notifications\Ninja;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SpamNotification extends Notification 
{

    /**
     * Create a new notification instance.
     *
     * @return void
     */

    protected array $spam_list;

    public function __construct($spam_list)
    {
        $this->spam_list = $spam_list;
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
     * @return MailMessage
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
        $content = '';

        foreach($this->spam_list as $spam_list)
        {

            if(array_key_exists('companies', $spam_list))
            {
                $content .= " Companies \n";

                foreach($spam_list['companies'] as $company)
                {
                    $content .= "{$company['name']} - c_key={$company['company_key']} - a_key={$company['account_key']} - {$company['owner']} \n";
                }
            }

            if(array_key_exists('templates', $spam_list))
            {
                $content .= " Templates \n";

                foreach($spam_list['templates'] as $company)
                {
                    $content .= "{$company['name']} - c_key={$company['company_key']} - a_key={$company['account_key']} - {$company['owner']} \n";
                }
            }


            if(array_key_exists('users', $spam_list))
            {

                $content .= ' Users \n';

                foreach($spam_list['users'] as $user)
                {
                    $content .= "{$user['email']} - a_key={$user['account_key']} - created={$user['created']} \n";
                }

            }

        }

        return (new SlackMessage)
                ->success()
                ->from(ctrans('texts.notification_bot'))
                ->image('https://app.invoiceninja.com/favicon.png')
                ->content($content);
    }
}
