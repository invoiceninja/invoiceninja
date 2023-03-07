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

use App\DataMapper\EmailTemplateDefaults;
use App\Models\Account;
use App\Utils\Ninja;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Facades\App;
use League\CommonMark\CommonMarkConverter;

class EmailDefaults
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

    /**
     * @param Email $email job class
     * @param EmailObject  $email_object the email object class
     */
    public function __construct(protected Email $email)
    {
    }
 
    /**
     * Entry point for generating
     * the defaults for the email object
     *
     * @return EmailObject $email_object The email object
     */
    public function run()
    {
        $this->settings = $this->email->email_object->settings;

        $this->setLocale() //
             ->setFrom()
             ->setTo()
             ->setTemplate()
             ->setBody()
             ->setSubject()
             ->setReplyTo()
             ->setBcc()
             ->setAttachments()
             ->setVariables();
        
        return $this->email->email_object;
    }

    /**
     * Sets the locale
     */
    private function setLocale(): self
    {
        if ($this->email->email_object->client) {
            $this->locale = $this->email->email_object->client->locale();
        } elseif ($this->email->email_object->vendor) {
            $this->locale = $this->email->email_object->vendor->locale();
        } else {
            $this->locale = $this->email->company->locale();
        }

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
        $this->template = $this->email->email_object->settings->email_style;

        match ($this->email->email_object->settings->email_style) {
            'light' => $this->template = 'email.template.client',
            'dark' => $this->template = 'email.template.client',
            'custom' => $this->template = 'email.template.custom',
            default => $this->template = 'email.template.client',
        };

        $this->email->email_object->html_template = $this->template;

        return $this;
    }

    /**
     * Sets the FROM address
     */
    private function setFrom(): self
    {
        if (Ninja::isHosted() && $this->email->email_object->settings->email_sending_method == 'default') {
            $this->email->email_object->from = new Address(config('mail.from.address'), $this->email->company->owner()->name());
            return $this;
        }

        if ($this->email->email_object->from) {
            return $this;
        }

        $this->email->email_object->from = new Address($this->email->company->owner()->email, $this->email->company->owner()->name());

        return $this;
    }

    /**
     * Sets the To address
     */
    private function setTo(): self
    {

        if ($this->email->email_object->to) {
            return $this;
        }

        $this->email->email_object->to = [new Address($this->email->email_object->contact->email, $this->email->email_object->contact->present()->name())];

        return $this;
    }

    /**
     * Sets the body of the email
     */
    private function setBody(): self
    {
        if ($this->email->email_object->body) {
            $this->email->email_object->body = $this->email->email_object->body;
        } elseif (isset($this->email->email_object->email_template_body) && strlen($this->email->email_object->settings->{$this->email->email_object->email_template_body}) > 3) {
            $this->email->email_object->body = $this->email->email_object->settings->{$this->email->email_object->email_template_body};
        } else {
            $this->email->email_object->body = EmailTemplateDefaults::getDefaultTemplate($this->email->email_object->email_template_body, $this->locale);
        }
        
        if ($this->template == 'email.template.custom') {
            $this->email->email_object->body = (str_replace('$body', $this->email->email_object->body, $this->email->email_object->settings->email_style_custom));
        }

        return $this;
    }

    /**
     * Sets the subject of the email
     */
    private function setSubject(): self
    {
        if ($this->email->email_object->subject) { //where the user updates the subject from the UI
            return $this;
        } elseif (isset($this->email->email_object->email_template_subject) && strlen($this->email->email_object->settings->{$this->email->email_object->email_template_subject}) > 3) {
            $this->email->email_object->subject = $this->email->email_object->settings->{$this->email->email_object->email_template_subject};
        } else {
            $this->email->email_object->subject = EmailTemplateDefaults::getDefaultTemplate($this->email->email_object->email_template_subject, $this->locale);
        }

        return $this;
    }

    /**
     * Sets the reply to of the email
     */
    private function setReplyTo(): self
    {
        $reply_to_email = str_contains($this->email->email_object->settings->reply_to_email, "@") ? $this->email->email_object->settings->reply_to_email : $this->email->company->owner()->email;

        $reply_to_name = strlen($this->email->email_object->settings->reply_to_name) > 3 ? $this->email->email_object->settings->reply_to_name : $this->email->company->owner()->present()->name();

        $this->email->email_object->reply_to = array_merge($this->email->email_object->reply_to, [new Address($reply_to_email, $reply_to_name)]);

        return $this;
    }

    /**
     * Replaces the template placeholders
     * with variable values.
     */
    public function setVariables(): self
    {
        $this->email->email_object->body = strtr($this->email->email_object->body, $this->email->email_object->variables);
        
        $this->email->email_object->subject = strtr($this->email->email_object->subject, $this->email->email_object->variables);

        if ($this->template != 'custom') {
            $this->email->email_object->body = $this->parseMarkdownToHtml($this->email->email_object->body);
        }

        return $this;
    }

    /**
     * Sets the BCC of the email
     */
    private function setBcc(): self
    {
        $bccs = [];
        $bcc_array = [];

        if (strlen($this->email->email_object->settings->bcc_email) > 1) {
            if (Ninja::isHosted() && $this->email->company->account->isPaid()) {
                $bccs = array_slice(explode(',', str_replace(' ', '', $this->email->email_object->settings->bcc_email)), 0, 2);
            } elseif (Ninja::isSelfHost()) {
                $bccs = (explode(',', str_replace(' ', '', $this->email->email_object->settings->bcc_email)));
            }
        }

        foreach ($bccs as $bcc) {
            $bcc_array[] = new Address($bcc);
        }
        
        $this->email->email_object->bcc = array_merge($this->email->email_object->bcc, $bcc_array);

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

        if ($this->email->email_object->settings->document_email_attachment && $this->email->company->account->hasFeature(Account::FEATURE_DOCUMENTS)) {
            foreach ($this->email->company->documents as $document) {
                $attachments[] = ['file' => base64_encode($document->getFile()), 'name' => $document->name];
            }
        }

        $this->email->email_object->attachments = array_merge($this->email->email_object->attachments, $attachments);

        return $this;
    }

    /**
     * Sets the headers for the email
     */
    private function setHeaders(): self
    {
        if ($this->email->email_object->invitation_key) {
            $this->email->email_object->headers = array_merge($this->email->email_object->headers, ['x-invitation-key' => $this->email->email_object->invitation_key]);
        }

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
