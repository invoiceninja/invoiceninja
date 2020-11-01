<?php
/**
 * Quote Ninja (https://quoteninja.com).
 *
 * @link https://github.com/quoteninja/quoteninja source repository
 *
 * @copyright Copyright (c) 2020. Quote Ninja LLC (https://quoteninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Mail\Engine;

use App\Utils\HtmlEngine;
use App\Utils\Number;

class QuoteEmailEngine extends BaseEmailEngine 
{
	public $invitation;

	public $client;

	public $quote;

	public $contact;

    public $reminder_template;

    public function __construct($invitation, $reminder_template)
    {
    	$this->invitation = $invitation;
        $this->reminder_template = $reminder_template;
        $this->client = $invitation->contact->client;
        $this->quote = $invitation->quote;
        $this->contact = $invitation->contact;
    }

    public function build()
    {

        $body_template = $this->client->getSetting('email_template_'.$this->reminder_template);

        /* Use default translations if a custom message has not been set*/
        if (iconv_strlen($body_template) == 0) {
            $body_template = trans(
                'texts.quote_message',
                [
                    'quote' => $this->quote->number,
                    'company' => $this->quote->company->present()->name(),
                    'amount' => Number::formatMoney($this->quote->balance, $this->client),
                ],
                null,
                $this->client->locale()
            );
        }

        $subject_template = $this->client->getSetting('email_subject_'.$this->reminder_template);

        if (iconv_strlen($subject_template) == 0) {

            $subject_template = trans(
                'texts.quote_subject',
                [
                    'number' => $this->quote->number,
                    'account' => $this->quote->company->present()->name(),
                ],
                null,
                $this->client->locale()
            );
        
        }

        $this->setTemplate($this->client->getSetting('email_style'))
            ->setContact($this->contact)
            ->setVariables((new HtmlEngine($this->invitation))->makeValues())//move make values into the htmlengine
            ->setSubject($subject_template)
            ->setBody($body_template)
            ->setFooter("<a href='{$this->invitation->getLink()}'>".ctrans('texts.view_quote').'</a>')
            ->setViewLink($this->invitation->getLink())
            ->setViewText(ctrans('texts.view_quote'));

        if ($this->client->getSetting('pdf_email_attachment') !== false) {
            $this->setAttachments($invitation->pdf_file_path());
        }

        return $this;

    }

}

