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

//@deprecated
class EntitySentNotification extends Notification
{
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
        return [];
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
                            'client' => $this->contact->client->present()->name(),
                            'invoice' => $this->entity->number,
                        ]
                    ))
                    ->attachment(function ($attachment) use ($amount) {
                        $attachment->title(ctrans('texts.invoice_number_placeholder', ['invoice' => $this->entity->number]), $this->invitation->getAdminLink())
                                   ->fields([
                                       ctrans('texts.client') => $this->contact->client->present()->name(),
                                       ctrans('texts.amount') => $amount,
                                   ]);
                    });
    }
}
