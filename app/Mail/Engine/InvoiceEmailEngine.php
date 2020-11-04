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

namespace App\Mail\Engine;

use App\Utils\HtmlEngine;
use App\Utils\Number;

class InvoiceEmailEngine extends BaseEmailEngine 
{
	public $invitation;

	public $client;

	public $invoice;

	public $contact;

    public $reminder_template;

    public function __construct($invitation, $reminder_template)
    {
    	$this->invitation = $invitation;
        $this->reminder_template = $reminder_template;
        $this->client = $invitation->contact->client;
        $this->invoice = $invitation->invoice;
        $this->contact = $invitation->contact;
    }

    public function build()
    {

        $body_template = $this->client->getSetting('email_template_'.$this->reminder_template);

        /* Use default translations if a custom message has not been set*/
        if (iconv_strlen($body_template) == 0) {
            $body_template = trans(
                'texts.invoice_message',
                [
                    'invoice' => $this->invoice->number,
                    'company' => $this->invoice->company->present()->name(),
                    'amount' => Number::formatMoney($this->invoice->balance, $this->client),
                ],
                null,
                $this->client->locale()
            );
        }

        $subject_template = $this->client->getSetting('email_subject_'.$this->reminder_template);

        if (iconv_strlen($subject_template) == 0) {

            $subject_template = trans(
                'texts.invoice_subject',
                [
                    'number' => $this->invoice->number,
                    'account' => $this->invoice->company->present()->name(),
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
            ->setFooter("<a href='{$this->invitation->getLink()}'>".ctrans('texts.view_invoice').'</a>')
            ->setViewLink($this->invitation->getLink())
            ->setViewText(ctrans('texts.view_invoice'));

        if ($this->client->getSetting('pdf_email_attachment') !== false) {
            $this->setAttachments($this->invoice->pdf_file_path());
        }

        return $this;

    }

}

