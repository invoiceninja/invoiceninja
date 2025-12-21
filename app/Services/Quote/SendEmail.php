<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Quote;

use App\Utils\Ninja;
use App\Models\Quote;
use App\Models\Webhook;
use App\Models\ClientContact;
use App\Services\Email\Email;
use App\Jobs\Entity\EmailEntity;
use App\Services\Email\EmailObject;
use App\Events\General\EntityWasEmailed;

class SendEmail
{
    public function __construct(public Quote $quote, public ?string $reminder_template = null, protected ?ClientContact $contact = null)
    {
    }

    /**
     * Builds the correct template to send.
     * @return void
     */
    public function run()
    {

        $this->reminder_template = $this->resolveTemplateString($this->reminder_template);

        $this->quote->service()->markSent()->save();

        $this->quote->invitations->each(function ($invitation) {
            if (! $invitation->contact->trashed() && $invitation->contact->email && !$invitation->contact->is_locked) {

                //@refactor 2024-11-10
                $mo = new EmailObject();
                $mo->entity_id = $invitation->quote_id;
                $mo->template = $this->reminder_template; //full template name in use
                $mo->email_template_body = $this->reminder_template;
                $mo->email_template_subject = str_replace("template", "subject", $this->reminder_template);

                $mo->entity_class = get_class($invitation->quote);
                $mo->invitation_id = $invitation->id;
                $mo->client_id = $invitation->contact->client_id ?? null;
                $mo->vendor_id = $invitation->contact->vendor_id ?? null;

                Email::dispatch($mo, $invitation->company);

                $this->quote->entityEmailEvent($invitation, $this->reminder_template, $this->reminder_template);

            }
        });

        event(new EntityWasEmailed($this->quote->invitations->first(), $this->quote->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null), 'quote'));

        $this->quote->sendEvent(Webhook::EVENT_SENT_QUOTE, "client");

    }

    private function resolveTemplateString(string $template): string
    {
        return match ($template) {
            'quote' => 'email_template_quote',
            'reminder1' => 'email_quote_template_reminder1',
            'custom1' => 'email_template_custom1',
            'custom2' => 'email_template_custom2',
            'custom3' => 'email_template_custom3',
            default => "email_template_quote",
        };
    }
}
