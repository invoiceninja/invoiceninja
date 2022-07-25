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

class EntityViewedNotification extends Notification
{
    /**
     * Create a new notification instance.
     *
     * @return void
     * @
     */
    protected $invitation;

    protected $entity_name;

    protected $entity;

    protected $company;

    protected $settings;

    public $method;

    protected $contact;

    public function __construct($invitation, $entity_name, $is_system = false, $settings = null)
    {
        $this->entity_name = $entity_name;
        $this->entity = $invitation->{$entity_name};
        $this->contact = $invitation->contact;
        $this->company = $invitation->company;
        $this->settings = $invitation->contact->client->getMergedSettings();
        $this->is_system = $is_system;
        $this->invitation = $invitation;
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
        return [
            //
        ];
    }

    public function toSlack($notifiable)
    {
        $logo = $this->company->present()->logo();
        $amount = Number::formatMoney($this->entity->amount, $this->entity->client);

        return (new SlackMessage)
            ->from(ctrans('texts.notification_bot'))
            ->success()
            ->image('https://app.invoiceninja.com/favicon-v2.png')
            ->content(ctrans(
                "texts.notification_{$this->entity_name}_viewed",
                [
                    'amount' => $amount,
                    'client' => $this->contact->present()->name(),
                    $this->entity_name => $this->entity->number,
                ]
            ))
            ->attachment(function ($attachment) use ($amount) {
                $attachment->title(ctrans('texts.entity_number_placeholder', ['entity' => ucfirst($this->entity_name), 'entity_number' => $this->entity->number]), $this->invitation->getAdminLink())
                           ->fields([
                               ctrans('texts.client') => $this->contact->present()->name(),
                               ctrans('texts.status_viewed') => $this->invitation->viewed_date,
                           ]);
            });
    }

    private function buildDataArray()
    {
        $amount = Number::formatMoney($this->entity->amount, $this->entity->client);

        $data = [
            'title' => $this->buildSubject(),
            'message' => ctrans(
                "texts.notification_{$this->entity_name}_viewed",
                [
                    'amount' => $amount,
                    'client' => $this->contact->present()->name(),
                    $this->entity_name => $this->entity->number,
                ]
            ),
            'url' => $this->invitation->getAdminLink(),
            'button' => ctrans("texts.view_{$this->entity_name}"),
            'signature' => $this->settings->email_signature,
            'logo' => $this->company->present()->logo(),
            'settings' => $this->settings,

        ];

        return $data;
    }

    private function buildSubject()
    {
        $subject = ctrans(
            "texts.notification_{$this->entity_name}_viewed_subject",
            [
                'client' => $this->contact->present()->name(),
                $this->entity_name => $this->entity->number,
            ]
        );

        return $subject;
    }
}
