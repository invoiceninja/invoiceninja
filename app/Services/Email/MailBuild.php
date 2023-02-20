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
use App\Jobs\Entity\CreateRawPdf;
use App\Jobs\Vendor\CreatePurchaseOrderPdf;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Credit;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\Task;
use App\Models\Vendor;
use App\Models\VendorContact;
use App\Utils\HtmlEngine;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;
use App\Utils\VendorHtmlEngine;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
use League\CommonMark\CommonMarkConverter;

class MailBuild
{
    use MakesHash;

    /** @var mixed $settings */
    protected $settings;
    
    /** @var string $template */
    private string $template;
    
    /** @var string $locale */
    private string $locale;
    
    /** @var ?Client $client */
    private ?Client $client;
    
    /** @var ?Vendor $vendor */
    private ?Vendor $vendor;
        
    /** @var mixed $html_engine */
    private mixed $html_engine;
    
    /** @var array $variables */
    private array $variables = [];
   
    /** @var int $max_attachment_size */
    public int $max_attachment_size = 3000000;

    /**
     * __construct
     *
     * @param  MailEntity $mail_entity
     * @return void
     */
    public function __construct(public MailEntity $mail_entity)
    {
    }
    
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
        return new MailMailable($this->mail_entity->mail_object);
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

        if ($this->mail_entity?->invitation) {
            if ($this->mail_entity->invitation?->invoice) {
                $this->mail_entity->mail_object->entity_string = 'invoice';
                $this->mail_entity->mail_object->entity_class = Invoice::class;
            }

            if ($this->mail_entity->invitation?->quote) {
                $this->mail_entity->mail_object->entity_string = 'quote';
                $this->mail_entity->mail_object->entity_class = Quote::class;
            }

            if ($this->mail_entity->invitation?->credit) {
                $this->mail_entity->mail_object->entity_string = 'credit';
                $this->mail_entity->mail_object->entity_class = Credit::class;
            }

            if ($this->mail_entity->invitation?->puchase_order) {
                $this->mail_entity->mail_object->entity_string = 'purchase_order';
                $this->mail_entity->mail_object->entity_class = PurchaseOrder::class;
            }
        }

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
        if ($this->client) {
            $this->locale = $this->client->locale();
            $this->settings = $this->client->getMergedSettings();

            if ($this->mail_entity->invitation) {
                $this->variables = (new HtmlEngine($this->mail_entity->invitation))->makeValues();
            }
        } elseif ($this->vendor) {
            $this->locale = $this->vendor->locale();
            $this->settings = $this->mail_entity->company->settings;

            if ($this->mail_entity->invitation) {
                $this->variables = (new VendorHtmlEngine($this->mail_entity->invitation))->makeValues();
            }
        } else {
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
     * Sets the template
     *
     * @return self
     */
    private function setTemplate(): self
    {
        $this->template = $this->settings->email_style;

        match ($this->settings->email_style) {
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
        $this->mail_entity->mail_object->to = array_merge(
            $this->mail_entity->mail_object->to,
            [new Address($this->mail_entity->invitation->contact->email, $this->mail_entity->invitation->contact->present()->name())]
        );

        return $this;
    }
 
    /**
     * Sets the FROM address
     *
     * @return self
     */
    private function setFrom(): self
    {
        if (Ninja::isHosted() && $this->settings->email_sending_method == 'default') {
            $this->mail_entity->mail_object->from = new Address(config('mail.from.address'), $this->mail_entity->company->owner()->name());
            return $this;
        }

        if ($this->mail_entity->mail_object->from) {
            return $this;
        }

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
        if ($this->mail_entity->mail_object->subject) { //where the user updates the subject from the UI
            return $this;
        } elseif (is_string($this->mail_entity->mail_object->email_template) && strlen($this->settings->{$this->resolveBaseEntityTemplate()}) > 3) {
            $this->mail_entity->mail_object->subject = $this->settings->{$this->resolveBaseEntityTemplate()};
        } else {
            $this->mail_entity->mail_object->subject = EmailTemplateDefaults::getDefaultTemplate($this->resolveBaseEntityTemplate(), $this->locale);
        }

        return $this;
    }
 
    /**
     * Sets the body of the email
     *
     * @return self
     */
    private function setBody(): self
    {
        if ($this->mail_entity->mail_object->body) {
            $this->mail_entity->mail_object->body = $this->mail_entity->mail_object->body;
        } elseif (is_string($this->mail_entity->mail_object->email_template) && strlen($this->settings->{$this->resolveBaseEntityTemplate('body')}) > 3) {
            $this->mail_entity->mail_object->body = $this->settings->{$this->resolveBaseEntityTemplate('body')};
        } else {
            $this->mail_entity->mail_object->body = EmailTemplateDefaults::getDefaultTemplate($this->resolveBaseEntityTemplate('body'), $this->locale);
        }
        
        if ($this->template == 'email.template.custom') {
            $this->mail_entity->mail_object->body = (str_replace('$body', $this->mail_entity->mail_object->body, $this->settings->email_style_custom));
        }

        return $this;
    }
    
    /**
     * Where no template is explicitly passed, we need to infer by the entity type -
     * which is hopefully resolvable.
     *
     * @param  string $type
     * @return string
     */
    private function resolveBaseEntityTemplate(string $type = 'subject'): string
    {
        if ($this->mail_entity->mail_object->email_template) {
            match ($type) {
                'subject' => $template = "email_subject_{$this->mail_entity->mail_object->email_template}",
                'body' => $template =  "email_template_{$this->mail_entity->mail_object->email_template}",
                default => $template = "email_template_invoice",
            };

            return $template;
        }

        //handle statements being emailed
        //handle custom templates these types won't have a resolvable entity_string
        if (!$this->mail_entity->mail_object->entity_string) {
            return 'email_template_invoice';
        }
            
        match ($type) {
            'subject' => $template = "email_subject_{$this->mail_entity->mail_object->entity_string}",
            'body' => $template =  "email_template_{$this->mail_entity->mail_object->entity_string}",
            default => $template = "email_template_invoice",
        };

        return $template;
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
        $this->setContextAttachments();

        if ($this->settings->document_email_attachment && $this->mail_entity->company->account->hasFeature(Account::FEATURE_DOCUMENTS)) {
            $this->attachDocuments($this->mail_entity->company->documents);
        }

        return $this;
    }
    
    private function attachDocuments($documents): self
    {
        foreach ($documents as $document) {
            if ($document->size > $this->max_attachment_size) {
                $this->mail_entity->mail_object->attachment_links = array_merge($this->mail_entity->mail_object->attachment_links, [["<a class='doc_links' href='" . URL::signedRoute('documents.public_download', ['document_hash' => $document->hash]) ."'>". $document->name ."</a>"]]);
            } else {
                $this->mail_entity->mail_object->attachments = array_merge($this->mail_entity->mail_object->attachments, [['file' => base64_encode($document->getFile()), 'name' => $document->name]]);
            }
        }

        return $this;
    }

    /**
     * Depending on context we may need to resolve the
     * attachment dependencies.
     *
     * ie. Resolve the entity.
     * ie. Resolve if we should attach the Entity PDF
     * ie. Create the Entity PDF
     * ie. Inject the PDF
     *
     * @return self
     */
    private function setContextAttachments(): self
    {
        if (!$this->settings->pdf_email_attachment || !$this->mail_entity->company->account->hasFeature(Account::FEATURE_PDF_ATTACHMENT)) {
            return $this;
        }

        if ($this->mail_entity->invitation?->purchase_order) {
            $pdf = (new CreatePurchaseOrderPdf($this->mail_entity->invitation))->rawPdf();
   
            $this->mail_entity->mail_object->attachments = array_merge($this->mail_entity->mail_object->attachments, [['file' => base64_encode($pdf), 'name' => $this->mail_entity->invitation->purchase_order->numberFormatter().'.pdf']]);

            if ($this->vendor->getSetting('document_email_attachment') !== false && $this->mail_entity->company->account->hasFeature(Account::FEATURE_DOCUMENTS)) {
                $this->attachDocuments($this->mail_entity->invitation->purchase_order->documents);
            }

            return $this;
        }

        if (!$this->mail_entity->mail_object->entity_string) {
            return $this;
        }

        $pdf = ((new CreateRawPdf($this->mail_entity->invitation, $this->mail_entity->invitation->company->db))->handle());
         
        $this->mail_entity->mail_object->attachments = array_merge($this->mail_entity->mail_object->attachments, [['file' => base64_encode($pdf), 'name' => $this->mail_entity->invitation->{$this->mail_entity->mail_object->entity_string}->numberFormatter().'.pdf']]);

        if ($this->client->getSetting('document_email_attachment') !== false && $this->mail_entity->company->account->hasFeature(Account::FEATURE_DOCUMENTS)) {
            $this->attachDocuments($this->mail_entity->invitation->{$this->mail_entity->mail_object->entity_string}->documents);
        }

        return $this;

        


        if ($this->settings->ubl_email_attachment && $this->mail_entity->mail_object->entity_string == 'invoice') {
        }
       
        if ($this->mail_entity->mail_object->entity_string == 'invoice') {
            $line_items = $this->mail_entity->invitation->invoice->line_items;

            foreach ($line_items as $item) {
                $expense_ids = [];

                if (property_exists($item, 'expense_id')) {
                    $expense_ids[] = $item->expense_id;
                }

                if (count($expense_ids) > 0) {
                    $expenses = Expense::whereIn('id', $this->transformKeys($expense_ids))
                                       ->where('invoice_documents', 1)
                                       ->cursor()
                                       ->each(function ($expense) {
                                           $this->attachDocuments($expense->documents);
                                       });
                }

                $task_ids = [];

                if (property_exists($item, 'task_id')) {
                    $task_ids[] = $item->task_id;
                }

                if (count($task_ids) > 0 && $this->mail_entity->company->invoice_task_documents) {
                    $tasks = Task::whereIn('id', $this->transformKeys($task_ids))
                                       ->cursor()
                                       ->each(function ($task) {
                                           $this->attachDocuments($task->documents);
                                       });
                }
            }
        }


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
        if ($this->mail_entity->mail_object->variables) {
            $this->mail_entity->mail_object->subject = strtr($this->mail_entity->mail_object->subject, $this->mail_entity->mail_object->variables);
            $this->mail_entity->mail_object->body = strtr($this->mail_entity->mail_object->body, $this->mail_entity->mail_object->variables);
        }

        $this->mail_entity->mail_object->subject = strtr($this->mail_entity->mail_object->subject, $this->variables);
        $this->mail_entity->mail_object->body = strtr($this->mail_entity->mail_object->body, $this->variables);

        if ($this->template != 'custom') {
            $this->mail_entity->mail_object->body = $this->parseMarkdownToHtml($this->mail_entity->mail_object->body);
        }

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
            } elseif (Ninja::isSelfHost()) {
                $bccs = (explode(',', str_replace(' ', '', $this->settings->bcc_email)));
            }
        }

        foreach ($bccs as $bcc) {
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
        if ($this->mail_entity->mail_object->invitation_key) {
            $this->mail_entity->mail_object->headers = array_merge($this->mail_entity->mail_object->headers, ['x-invitation-key' => $this->mail_entity->mail_object->invitation_key]);
        } elseif ($this->mail_entity->invitation) {
            $this->mail_entity->mail_object->headers = array_merge($this->mail_entity->mail_object->headers, ['x-invitation-key' => $this->mail_entity->invitation->key]);
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
