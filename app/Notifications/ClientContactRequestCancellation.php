<?php

namespace App\Notifications;

use App\Mail\RecurringCancellationRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;

class ClientContactRequestCancellation extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    
    protected $recurring_invoice;

    protected $client_contact;

    public function __construct($recurring_invoice, $client_contact)
    {
        $this->recurring_invoice = $recurring_invoice;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {

    	return new RecurringCancellationRequest($this->recurring_invoice);
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

    $user_name = $this->user->first_name . " " . $this->user->last_name;
    $email = $this->user->email;
    $ip = $this->user->ip;

    return (new SlackMessage)
                ->success()
                ->to("#devv2")
                ->from("System")
                ->image('https://app.invoiceninja.com/favicon.png')
                ->content("A new account has been created by {$user_name} - {$email} - from IP: {$ip}");
    }

}
