<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Email;

use App\Utils\Ninja;
use App\Models\Client;
use App\Models\Vendor;
use App\Models\Account;
use App\Utils\HtmlEngine;
use App\Models\ClientContact;
use App\Models\VendorContact;
use App\Utils\VendorHtmlEngine;
use Illuminate\Support\Facades\App;
use App\Services\Email\MailMailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Contracts\Mail\Mailable;
use App\DataMapper\EmailTemplateDefaults;
use League\CommonMark\CommonMarkConverter;

class MailBuild
{
    
    /**
     * settings
     *
     * @var mixed
     */
    protected $settings;
    
    /** @var mixed $template */
    private string $template;
    
    /** @var mixed $locale */
    private string $locale;
    
    /** @var mixed $client */
    private ?Client $client;
    
    /** @var mixed $vendor */
    private ?Vendor $vendor;
        
    /** @var mixed $html_engine */
    private mixed $html_engine;
    
    /** @var mixed $variables */
    private array $variables = [];
    /**
     * __construct
     *
     * @param  mixed $mail_entity
     * @return void
     */
    public function __construct(public MailEntity $mail_entity){}
    
    /**
     * Builds the mailable
     *
     * @return self
     */
    public function run(): self
    {
        //resolve settings, if client existing - use merged - else default to company
        $this->resolveEntities()
             ->setLocale()
             ->setMetaData()
             ->setFrom()
             ->setTo()
             ->setTemplate()
             ->setSubject()
             ->setBody()
             ->setReplyTo()
             ->setBcc()
             ->setAttachments()
             ->setVariables();

        return $this;
    }
    
    /**
     * Returns the mailable to the mailer
     *
     * @return Mailable
     */
    public function getMailable(): Mailable
    {
        return new MailMailable($this->mail_entity->mail_object); //todo current depends on EmailObject
    }

    /**
     * Resolve any class entities
     *
     * @return self
     */
    private function resolveEntities(): self
    {

        $client_contact = $this->mail_entity?->invitation?->client_contact_id ? ClientContact::withTrashed()->find($this->mail_entity->invitation->client_contact_id) : null;
        
        $this->client = $client_contact?->client;

        $vendor_contact = $this->mail_entity?->invitation?->vendor_contact_id ? VendorContact::withTrashed()->find($this->mail_entity->invitation->vendor_contact_id) : null;
            
        $this->vendor = $vendor_contact?->vendor;

        return $this;
    }

    /**
     * Sets the meta data for the Email object
     *
     * @return self
     */
    private function setMetaData(): self
    {

        $this->mail_entity->mail_object->company_key = $this->mail_entity->company->company_key;

        $this->mail_entity->mail_object->logo = $this->mail_entity->company->present()->logo();

        $this->mail_entity->mail_object->signature = $this->mail_entity->mail_object->signature ?: $this->settings->email_signature;

        $this->mail_entity->mail_object->whitelabel = $this->mail_entity->company->account->isPaid() ? true : false;
 
        $this->mail_entity->mail_object->company = $this->mail_entity->company;

        return $this;

    }

    /**
     * Sets the locale
     * Sets the settings object depending on context
     * Sets the HTML variables depending on context
     *
     * @return self
     */
    private function setLocale(): self
    {

        if($this->client){
    
            $this->locale = $this->client->locale();
            $this->settings = $this->client->getMergedSettings();

            if($this->mail_entity->invitation)
                $this->variables = (new HtmlEngine($this->mail_entity->invitation))->makeValues();

        }
        elseif($this->vendor){

            $this->locale = $this->vendor->locale();
            $this->settings = $this->mail_entity->company->settings;

            if($this->mail_entity->invitation)
                $this->variables = (new VendorHtmlEngine($this->mail_entity->invitation))->makeValues();


        }
        else{
            $this->locale = $this->mail_entity->company->locale();
            $this->settings = $this->mail_entity->company->settings;
        }
        
        $this->mail_entity->mail_object->settings = $this->settings;


        App::setLocale($this->locale);
        App::forgetInstance('translator');
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->settings));

        return $this;
    }

    /**
     * Sets the template
     *
     * @return self
     */
    private function setTemplate(): self
    {
        $this->template = $this->settings->email_style;

         match($this->settings->email_style){
            'light' => $this->template = 'email.template.client',
            'dark' => $this->template = 'email.template.client',
            'custom' => $this->template = 'email.template.custom',
            default => $this->template = 'email.template.client',
         };

         $this->mail_entity->mail_object->html_template = $this->template;

        return $this;
    }
    
    /**
     * setTo
     *
     * @return self
     */
    private function setTo(): self
    {
        $this->mail_entity->mail_object->to = [new Address($this->mail_entity->invitation->contact->email, $this->mail_entity->invitation->contact->present()->name())];
    
        return $this;
    }
 
    /**
     * Sets the FROM address
     *
     * @return self
     */
    private function setFrom(): self
    { 

        if(Ninja::isHosted() && $this->settings->email_sending_method == 'default'){
            $this->mail_entity->mail_object->from = new Address(config('mail.from.address'), $this->mail_entity->company->owner()->name());
            return $this;
        }

        if($this->mail_entity->mail_object->from)
            return $this;

        $this->mail_entity->mail_object->from = new Address($this->mail_entity->company->owner()->email, $this->mail_entity->company->owner()->name());

        return $this;

    }
    
    /**
     * Sets the subject of the email
     *
     * @return self
     */
    private function setSubject(): self
    {

        if ($this->mail_entity->mail_object->subject) //where the user updates the subject from the UI                
            return $this;
        elseif(is_string($this->mail_entity->mail_object->email_template_subject) && strlen($this->settings->{$this->mail_entity->mail_object->email_template_subject}) > 3)
            $this->mail_entity->mail_object->subject = $this->settings->{$this->mail_entity->mail_object->email_template_subject};
        else
            $this->mail_entity->mail_object->subject = EmailTemplateDefaults::getDefaultTemplate($this->mail_entity->mail_object->email_template_subject, $this->locale);

        return $this;

    }
 
    /**
     * Sets the body of the email
     *
     * @return self
     */
    private function setBody(): self
    {

        if($this->mail_entity->mail_object->body){
            $this->mail_entity->mail_object->body = $this->mail_entity->mail_object->body;
        }
        elseif(is_string($this->mail_entity->mail_object->email_template_body) && strlen($this->settings->{$this->mail_entity->mail_object->email_template_body}) > 3){
            $this->mail_entity->mail_object->body = $this->settings->{$this->mail_entity->mail_object->email_template_body};
        }
        else{
            $this->mail_entity->mail_object->body = EmailTemplateDefaults::getDefaultTemplate($this->mail_entity->mail_object->email_template_body, $this->locale);
        }
        
        if($this->template == 'email.template.custom'){
            $this->mail_entity->mail_object->body = (str_replace('$body', $this->mail_entity->mail_object->body, $this->settings->email_style_custom)); 
        }

        return $this;

    }

    /** 
     * Sets the attachments for the email
     *
     * Note that we base64 encode these, as they 
     * sometimes may not survive serialization.
     *
     * We decode these in the Mailable later
     * 
     * @return self
     */    
    private function setAttachments(): self
    {
        $attachments = [];

        if ($this->settings->document_email_attachment && $this->mail_entity->company->account->hasFeature(Account::FEATURE_DOCUMENTS)) {

            foreach ($this->mail_entity->company->documents as $document) {

                $attachments[] = ['file' => base64_encode($document->getFile()), 'name' => $document->name];
                
            }

        }

        $this->mail_entity->mail_object->attachments = array_merge($this->mail_entity->mail_object->attachments, $attachments);

        return $this;

    }



    /**
     * Sets the reply to of the email
     * 
     * @return self
     */
    private function setReplyTo(): self
    {

        $reply_to_email = str_contains($this->settings->reply_to_email, "@") ? $this->settings->reply_to_email : $this->mail_entity->company->owner()->email;

        $reply_to_name = strlen($this->settings->reply_to_name) > 3 ? $this->settings->reply_to_name : $this->mail_entity->company->owner()->present()->name();

        $this->mail_entity->mail_object->reply_to = array_merge($this->mail_entity->mail_object->reply_to, [new Address($reply_to_email, $reply_to_name)]);

        return $this;
    }

    /**
     * Replaces the template placeholders 
     * with variable values.
     * 
     * @return self
     */
    public function setVariables(): self
    {

        
        if($this->mail_entity->mail_object->variables){
            $this->mail_entity->mail_object->subject = strtr($this->mail_entity->mail_object->subject, $this->mail_entity->mail_object->variables);
            $this->mail_entity->mail_object->body = strtr($this->mail_entity->mail_object->body, $this->mail_entity->mail_object->variables);
        }

        $this->mail_entity->mail_object->subject = strtr($this->mail_entity->mail_object->subject, $this->variables);
        $this->mail_entity->mail_object->body = strtr($this->mail_entity->mail_object->body, $this->variables);

        if($this->template != 'custom') 
            $this->mail_entity->mail_object->body = $this->parseMarkdownToHtml($this->mail_entity->mail_object->body);

        return $this;
    }

    /**
     * Sets the BCC of the email
     * 
     * @return self
     */
    private function setBcc(): self
    {
        $bccs = [];
        $bcc_array = [];

        if (strlen($this->settings->bcc_email) > 1) {

            if (Ninja::isHosted() && $this->mail_entity->company->account->isPaid()) {
                $bccs = array_slice(explode(',', str_replace(' ', '', $this->settings->bcc_email)), 0, 2);
            } elseif(Ninja::isSelfHost()) {
                $bccs = (explode(',', str_replace(' ', '', $this->settings->bcc_email)));
            }
        }

        foreach($bccs as $bcc)
        {
            $bcc_array[] = new Address($bcc);
        }
        
        $this->mail_entity->mail_object->bcc = array_merge($this->mail_entity->mail_object->bcc, $bcc_array);

        return $this;
    }

    /**
     * Sets the CC of the email
     * @todo at some point....
     */
    private function buildCc()
    {
        return [
        
        ];
    }


    /**
     * Sets the headers for the email
     * 
     * @return self
     */
    private function setHeaders(): self
    {
        if($this->mail_entity->mail_object->invitation_key)
            $this->mail_entity->mail_object->headers = array_merge($this->mail_entity->mail_object->headers, ['x-invitation-key' => $this->mail_entity->mail_object->invitation_key]);
        elseif($this->mail_entity->invitation)
            $this->mail_entity->mail_object->headers = array_merge($this->mail_entity->mail_object->headers, ['x-invitation-key' => $this->mail_entity->invitation->key]);

        return $this;
    }

    /**
     * Converts any markdown to HTML in the email
     * 
     * @param  string $markdown The body to convert
     * @return string           The parsed markdown response
     */
    private function parseMarkdownToHtml(string $markdown): ?string
    {
        $converter = new CommonMarkConverter([
            'allow_unsafe_links' => false,
        ]);

        return $converter->convert($markdown);
    }




}
