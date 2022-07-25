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

//@deprecated
class NewAccountCreated extends Notification
{
    // use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    protected $user;

    protected $company;

    public $is_system;

    public function __construct($user, $company, $is_system = false)
    {
        $this->user = $user;
        $this->company = $company;
        $this->is_system = $is_system;
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
        $this->user->setCompany($this->company);

        $user_name = $this->user->first_name.' '.$this->user->last_name;
        $email = $this->user->email;
        $ip = $this->user->ip;

        return (new SlackMessage)
                ->success()
                ->from(ctrans('texts.notification_bot'))
                ->image('https://app.invoiceninja.com/favicon.png')
                ->content("A new account has been created by {$user_name} - {$email} - from IP: {$ip}");
    }
}
