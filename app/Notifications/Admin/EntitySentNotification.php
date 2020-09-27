<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Notifications\Admin;

use App\Utils\Number;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EntitySentNotification extends Notification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    protected $invitation;

    protected $entity;

    protected $entity_name;

    protected $settings;

    public $is_system;

    public $method;

    protected $contact;

    protected $company;

    public function __construct($invitation, $entity_name, $is_system = false, $settings = null)
    {
        $this->invitation = $invitation;
        $this->entity_name = $entity_name;
        $this->entity = $invitation->{$entity_name};
        $this->contact = $invitation->contact;
        $this->company = $invitation->company;
        $this->settings = $this->entity->client->getMergedSettings();
        $this->is_system = $is_system;
        $this->method = null;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return $this->method ?: [];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        //@TODO THESE ARE @DEPRECATED NOW we are now using app/Mail/Admin/*
        $amount = Number::formatMoney($this->entity->amount, $this->entity->client);
        $subject = ctrans(
            "texts.notification_{$this->entity_name}_sent_subject",
            [
                    'client' => $this->contact->present()->name(),
                    'invoice' => $this->entity->number,
                ]
        );

        $data = [
            'title' => $subject,
            'message' => ctrans(
                "texts.notification_{$this->entity_name}_sent",
                [
                    'amount' => $amount,
                    'client' => $this->contact->present()->name(),
                    'invoice' => $this->entity->number,
                ]
            ),
            'url' => $this->invitation->getAdminLink(),
            'button' => ctrans("texts.view_{$this->entity_name}"),
            'signature' => $this->settings->email_signature,
            'logo' => $this->company->present()->logo(),
            'settings' => $this->settings,
        ];

        return (new MailMessage)
                    ->subject($subject)
                    ->markdown('email.admin.generic', $data)
                    ->withSwiftMessage(function ($message) {
                        $message->getHeaders()->addTextHeader('Tag', $this->company->company_key);
                    });
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
        $logo = $this->invitation->company->present()->logo();
        $amount = Number::formatMoney($this->entity->amount, $this->entity->client);

        return (new SlackMessage)
                    ->from(ctrans('texts.notification_bot'))
                    ->success()
                    ->image('https://app.invoiceninja.com/favicon-v2.png')
                    ->content(trans(
                        "texts.notification_{$this->entity_name}_sent_subject",
                        [
                        'amount' => $amount,
                        'client' => $this->contact->present()->name(),
                        'invoice' => $this->entity->number,
                    ]
                    ))
                    ->attachment(function ($attachment) use ($amount) {
                        $attachment->title(ctrans('texts.invoice_number_placeholder', ['invoice' => $this->entity->number]), $this->invitation->getAdminLink())
                                   ->fields([
                                        ctrans('texts.client') => $this->contact->present()->name(),
                                        ctrans('texts.amount') => $amount,
                                    ]);
                    });
    }
}
