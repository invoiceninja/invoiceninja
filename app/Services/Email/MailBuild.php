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

use App\Models\Client;
use App\Models\Vendor;
use App\Utils\Ninja;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Mail\Mailable as MailMailable;
use Illuminate\Support\Facades\App;

/**
 * Class assumption is that we will be emailing an entity that has an associated Invitation
 */
class MailBuild
{

    /**
     * The settings object for this email
     * @var CompanySettings $settings
     */
    protected $settings;

    /**
     * The HTML / Template to use for this email
     * @var string $template
     */
    private string $template;

    /**
     * The locale to use for
     * translations for this email
     */
    private string $locale;

    private ?Client $client;

    private ?Vendor $vendor;

    public function __construct(public MailEntity $mail_entity)
    {

    }

    public function run(): Mailable
    {
        //resolve settings, if client existing - use merged - else default to company
        $this->settings = $this->mail_entity->company->settings;
        $this->resolveEntities();
    }

    private function resolveEntities(): self
    {

        $this->client = $this->mail_entity->mail_object->client_id ? Client::find($this->mail_entity->mail_object->client_id) : null;
        
        $this->vendor = $this->mail_entity->mail_object->vendor_id ? Vendor::find($this->mail_entity->mail_object->vendor_id) : null;
            
        $this->locale = $this->mail_entity->company->locale();

        return $this;
    }


    /**
     * Sets the meta data for the Email object
     */
    private function setMetaData(): self
    {

        $this->mail_entity->mail_object->company_key = $this->mail_entity->company->company_key;

        $this->mail_entity->mail_object->logo = $this->mail_entity->company->present()->logo();

        $this->mail_entity->mail_object->signature = $this->mail_entity->mail_object->signature ?: $this->settings->email_signature;

        $this->mail_entity->mail_object->whitelabel = $this->mail_entity->company->account->isPaid() ? true : false;
 
        return $this;

    }

    /**
     * Sets the locale
     */
    private function setLocale(): self
    {

        if($this->mail_entity->mail_object->client_id)
            $this->locale = $this->mail_entity->mail_object->client->locale();
        elseif($this->mail_entity->mail_object->vendor)
            $this->locale = $this->mail_entity->mail_object->vendor->locale();
        else
            $this->locale = $this->mail_entity->company->locale();

        App::setLocale($this->locale);
        App::forgetInstance('translator');
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->settings));

        return $this;
    }

    /**
     * Sets the template
     */
    private function setTemplate(): self
    {
        $this->template = $this->mail_entity->mail_object->settings->email_style;

         match($this->mail_entity->mail_object->settings->email_style){
            'light' => $this->template = 'email.template.client',
            'dark' => $this->template = 'email.template.client',
            'custom' => $this->template = 'email.template.custom',
            default => $this->template = 'email.template.client',
         };

         $this->mail_entity->mail_object->html_template = $this->template;

        return $this;
    }

    /**
     * Sets the FROM address
     */
    private function setFrom(): self
    { 

        if(Ninja::isHosted() && $this->mail_entity->mail_object->settings->email_sending_method == 'default'){
            $this->mail_entity->mail_object->from = new Address(config('mail.from.address'), $this->mail_entity->company->owner()->name());
            return $this;
        }

        if($this->mail_entity->mail_object->from)
            return $this;

        $this->mail_entity->mail_object->from = new Address($this->mail_entity->company->owner()->email, $this->mail_entity->company->owner()->name());

        return $this;

    }

    /** 
     * Sets the body of the email
     */
    private function setBody(): self
    {

        if($this->mail_entity->mail_object->body){
            $this->mail_entity->mail_object->body = $this->mail_entity->mail_object->body;
        }
        elseif(strlen($this->mail_entity->mail_object->settings->{$this->mail_entity->mail_object->email_template_body}) > 3){
            $this->mail_entity->mail_object->body = $this->mail_entity->mail_object->settings->{$this->mail_entity->mail_object->email_template_body};
        }
        else{
            $this->mail_entity->mail_object->body = EmailTemplateDefaults::getDefaultTemplate($this->mail_entity->mail_object->email_template_body, $this->locale);
        }
        
        if($this->template == 'email.template.custom'){
            $this->mail_entity->mail_object->body = (str_replace('$body', $this->mail_entity->mail_object->body, $this->mail_entity->mail_object->settings->email_style_custom)); 
        }

        return $this;

    }

    /**
     * Sets the subject of the email
     */
    private function setSubject(): self
    {

        if ($this->mail_entity->mail_object->subject) //where the user updates the subject from the UI                
            return $this;
        elseif(strlen($this->mail_entity->mail_object->settings->{$this->mail_entity->mail_object->email_template_subject}) > 3)
            $this->mail_entity->mail_object->subject = $this->mail_entity->mail_object->settings->{$this->mail_entity->mail_object->email_template_subject};
        else
            $this->mail_entity->mail_object->subject = EmailTemplateDefaults::getDefaultTemplate($this->mail_entity->mail_object->email_template_subject, $this->locale);

        return $this;

    }

    /**
     * Sets the reply to of the email
     */
    private function setReplyTo(): self
    {

        $reply_to_email = str_contains($this->mail_entity->mail_object->settings->reply_to_email, "@") ? $this->mail_entity->mail_object->settings->reply_to_email : $this->mail_entity->company->owner()->email;

        $reply_to_name = strlen($this->mail_entity->mail_object->settings->reply_to_name) > 3 ? $this->mail_entity->mail_object->settings->reply_to_name : $this->mail_entity->company->owner()->present()->name();

        $this->mail_entity->mail_object->reply_to = array_merge($this->mail_entity->mail_object->reply_to, [new Address($reply_to_email, $reply_to_name)]);

        return $this;
    }

    /**
     * Replaces the template placeholders 
     * with variable values.
     */
    public function setVariables(): self
    {

        $this->mail_entity->mail_object->body = strtr($this->mail_entity->mail_object->body, $this->mail_entity->mail_object->variables);
        
        $this->mail_entity->mail_object->subject = strtr($this->mail_entity->mail_object->subject, $this->mail_entity->mail_object->variables);

        if($this->template != 'custom') 
            $this->mail_entity->mail_object->body = $this->parseMarkdownToHtml($this->mail_entity->mail_object->body);

        return $this;
    }

    /**
     * Sets the BCC of the email
     */
    private function setBcc(): self
    {
        $bccs = [];
        $bcc_array = [];

        if (strlen($this->mail_entity->mail_object->settings->bcc_email) > 1) {

            if (Ninja::isHosted() && $this->mail_entity->company->account->isPaid()) {
                $bccs = array_slice(explode(',', str_replace(' ', '', $this->mail_entity->mail_object->settings->bcc_email)), 0, 2);
            } elseif(Ninja::isSelfHost()) {
                $bccs = (explode(',', str_replace(' ', '', $this->mail_entity->mail_object->settings->bcc_email)));
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
     * Sets the attachments for the email
     *
     * Note that we base64 encode these, as they 
     * sometimes may not survive serialization.
     *
     * We decode these in the Mailable later
     */
    private function setAttachments(): self
    {
        $attachments = [];

        if ($this->mail_entity->mail_object->settings->document_email_attachment && $this->mail_entity->company->account->hasFeature(Account::FEATURE_DOCUMENTS)) {

            foreach ($this->mail_entity->company->documents as $document) {

                $attachments[] = ['file' => base64_encode($document->getFile()), 'name' => $document->name];
                
            }

        }

        $this->mail_entity->mail_object->attachments = array_merge($this->mail_entity->mail_object->attachments, $attachments);

        return $this;

    }

    /**
     * Sets the headers for the email
     */
    private function setHeaders(): self
    {
        if($this->mail_entity->mail_object->invitation_key)
            $this->mail_entity->mail_object->headers = array_merge($this->mail_entity->mail_object->headers, ['x-invitation-key' => $this->mail_entity->mail_object->invitation_key]);

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
