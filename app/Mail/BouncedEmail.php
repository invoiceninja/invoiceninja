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

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;

class BouncedEmail extends Mailable
{
    public $invitation;

    public function __construct($invitation)
    {
        $this->invitation = $invitation;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        App::setLocale($this->invitation->company->getLocale());

        $entity_type = class_basename(lcfirst($this->invitation->getEntityType()));

        $subject = ctrans("texts.notification_{$entity_type}_bounced_subject", ['invoice' => $this->invitation->invoice->number]);

        return
            $this->from(config('mail.from.address'), config('mail.from.name'))
                ->text('bounced mail')
                ->subject($subject);
    }
}
