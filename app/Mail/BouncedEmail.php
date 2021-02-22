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

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

// class BouncedEmail extends Mailable implements ShouldQueue
class BouncedEmail extends Mailable 
{
    //use Queueable, SerializesModels;

    public $invitation;

    /**
     * Create a new message instance.
     *
     * @param $invitation
     */
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
        $entity_type = class_basename(lcfirst($this->invitation->getEntityType()));

        $subject = ctrans("texts.notification_{$entity_type}_bounced_subject", ['invoice' => $invoice->number]);
        
        return
            $this->from(config('mail.from.address'), config('mail.from.name'))
                ->text()
                ->subject($subject);

    }
}
