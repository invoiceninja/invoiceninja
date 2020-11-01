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

namespace App\Notifications;

use App\Jobs\Invoice\CreateUbl;
use App\Models\Invoice;
use App\Utils\Number;
use App\Utils\Traits\MakesInvoiceHtml;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendGenericNotification extends BaseNotification implements ShouldQueue
{
    use Queueable;
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    protected $invitation;

    protected $entity;

    protected $contact;

    protected $entity_string;

    protected $settings;

    public $is_system;

    protected $body;

    protected $subject;

    public function __construct($invitation, $entity_string, $subject, $body)
    {
        $this->entity = $invitation->{$entity_string};
        $this->contact = $invitation->contact;
        $this->settings = $this->entity->client->getMergedSettings();
        $this->subject = $subject;
        $this->body = $body;
        $this->invitation = $invitation;
        $this->entity_string = $entity_string;
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
     * @return MailMessage
     */
    public function toMail($notifiable)
    {
        $mail_message = (new MailMessage)
                        ->withSwiftMessage(function ($message) {
                            $message->getHeaders()->addTextHeader('Tag', $this->invitation->company->company_key);
                        })->markdown($this->getTemplateView(), $this->buildMailMessageData());
        //})->markdown('email.template.plain', $this->buildMailMessageData());

        $mail_message = $this->buildMailMessageSettings($mail_message);

        return $mail_message;
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
        return '';
        // $logo = $this->company->present()->logo();
        // $amount = Number::formatMoney($this->invoice->amount, $this->invoice->client);

        // return (new SlackMessage)
        //         ->success()
        //         ->from(ctrans('texts.notification_bot'))
        //         ->image($logo)
        //         ->content(ctrans(
        //             'texts.notification_invoice_viewed',
        //             [
        //             'amount' => $amount,
        //             'client' => $this->contact->present()->name(),
        //             'invoice' => $this->invoice->number
        //         ]
        //         ));
    }
}
