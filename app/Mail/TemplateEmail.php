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

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\User;
use App\Utils\HtmlEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TemplateEmail extends Mailable
{

    private $build_email;

    private $client;

    private $contact;

    private $company;

    private $invitation;

    public function __construct($build_email, ClientContact $contact, $invitation = null)
    {
        $this->build_email = $build_email;

        $this->contact = $contact;

        $this->client = $contact->client;

        $this->company = $contact->company;

        $this->invitation = $invitation;
    }

    public function build()
    {
        $template_name = 'email.template.'.$this->build_email->getTemplate();

        if($this->build_email->getTemplate() == 'custom') {
            $this->build_email->setBody(str_replace('$body', $this->build_email->getBody(), $this->client->getSetting('email_style_custom')));
        }

        $settings = $this->client->getMergedSettings();

        $company = $this->client->company;

        if($this->invitation)
        {
            $html_variables = (new HtmlEngine($this->invitation))->makeValues();
            $signature = str_replace(array_keys($html_variables), array_values($html_variables), $settings->email_signature);
        }
        else
            $signature = $settings->email_signature;
        
        $this->from(config('mail.from.address'), $this->company->present()->name());
        
        if (strlen($settings->bcc_email) > 1) 
            $this->bcc($settings->bcc_email, $settings->bcc_email);

        $this->subject($this->build_email->getSubject())
            ->text('email.template.plain', [
                'body' => $this->build_email->getBody(),
                'footer' => $this->build_email->getFooter(),
                'whitelabel' => $this->client->user->account->isPaid() ? true : false,
                'settings' => $settings,
            ])
            ->view($template_name, [
                'greeting' => ctrans('texts.email_salutation', ['name' => $this->contact->present()->name()]),
                'body' => $this->build_email->getBody(),
                'footer' => $this->build_email->getFooter(),
                'view_link' => $this->build_email->getViewLink(),
                'view_text' => $this->build_email->getViewText(),
                'title' => '',
                'signature' => $signature,
                'settings' => $settings,
                'company' => $company,
                'whitelabel' => $this->client->user->account->isPaid() ? true : false,
            ])
            ->withSwiftMessage(function ($message) use($company){
                $message->getHeaders()->addTextHeader('Tag', $company->company_key);
                $message->invitation = $this->invitation;
            });

        //conditionally attach files
        if ($settings->pdf_email_attachment !== false && ! empty($this->build_email->getAttachments())) {

            //hosted | plan check here
            foreach ($this->build_email->getAttachments() as $file) {

                if(is_string($file))
                    $this->attach($file);
                elseif(is_array($file))
                    $this->attach($file['path'], ['as' => $file['name'], 'mime' => $file['mime']]);

            }
        }

        return $this;
    }
}
