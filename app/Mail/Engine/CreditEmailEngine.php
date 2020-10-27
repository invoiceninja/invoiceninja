<?php
/**
 * Credit Ninja (https://creditninja.com).
 *
 * @link https://github.com/creditninja/creditninja source repository
 *
 * @copyright Copyright (c) 2020. Credit Ninja LLC (https://creditninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Mail\Engine;

use App\Utils\Number;

class CreditEmailEngine extends BaseEmailEngine 
{
	public $invitation;

	public $client;

	public $credit;

	public $contact;

    public $reminder_template;

    public function __construct($invitation, $reminder_template)
    {
    	$this->invitation = $invitation;
        $this->reminder_template = $reminder_template;
        $this->client = $invitation->contact->client;
        $this->credit = $invitation->credit;
        $this->contact = $invitation->contact;
    }

    public function build()
    {

        $body_template = $this->client->getSetting('email_template_'.$this->reminder_template);

        /* Use default translations if a custom message has not been set*/
        if (iconv_strlen($body_template) == 0) {
            $body_template = trans(
                'texts.credit_message',
                [
                    'credit' => $this->credit->number,
                    'company' => $this->credit->company->present()->name(),
                    'amount' => Number::formatMoney($this->credit->balance, $this->client),
                ],
                null,
                $this->client->locale()
            );
        }

        $subject_template = $this->client->getSetting('email_subject_'.$this->reminder_template);

        if (iconv_strlen($subject_template) == 0) {

            $subject_template = trans(
                'texts.credit_subject',
                [
                    'number' => $this->credit->number,
                    'account' => $this->credit->company->present()->name(),
                ],
                null,
                $this->client->locale()
            );
        
        }

        $this->setTemplate($this->client->getSetting('email_style'))
            ->setContact($this->contact)
            ->setVariables($this->credit->makeValues($this->contact))//move make values into the htmlengine
            ->setSubject($subject_template)
            ->setBody($body_template)
            ->setFooter("<a href='{$this->invitation->getLink()}'>".ctrans('texts.view_credit').'</a>')
            ->setViewLink($this->invitation->getLink())
            ->setViewText(ctrans('texts.view_credit'));

        if ($this->client->getSetting('pdf_email_attachment') !== false) {
            $this->setAttachments($invitation->pdf_file_path());
        }

        return $this;

    }

}

