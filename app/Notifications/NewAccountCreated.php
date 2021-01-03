<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NewAccountCreated extends Notification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
        return ['slack', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return MailMessage
     */
    public function toMail($notifiable)
    {
        $user_name = $this->user->first_name.' '.$this->user->last_name;
        $email = $this->user->email;
        $ip = $this->user->ip;

        $data = [
            'title' => ctrans('texts.new_signup'),
            'message' => ctrans('texts.new_signup_text', ['user' => $user_name, 'email' => $email, 'ip' => $ip]),
            'url' => config('ninja.web_url'),
            'button' => ctrans('texts.account_login'),
            'signature' => $this->company->settings->email_signature,
            'logo' => $this->company->present()->logo(),
            'settings' => $this->company->settings,
        ];

        return (new MailMessage)
                    ->subject(ctrans('texts.new_signup'))
                    ->withSwiftMessage(function ($message) {
                        $message->getHeaders()->addTextHeader('Tag', $this->company->company_key);
                    })
                    ->markdown('email.admin.generic', $data);
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
