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

namespace App\Utils;

use App\DataMapper\EmailTemplateDefaults;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Invoice;
use App\Models\InvoiceInvitation;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderInvitation;
use App\Models\Quote;
use App\Models\QuoteInvitation;
use App\Models\Vendor;
use App\Models\VendorContact;
use App\Services\PdfMaker\Designs\Utilities\DesignHelpers;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\MakesInvoiceHtml;
use App\Utils\Traits\MakesTemplateData;
use App\Utils\VendorHtmlEngine;
use DB;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use League\CommonMark\CommonMarkConverter;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;

class TemplateEngine
{
    use MakesHash;
    use MakesTemplateData;
    use MakesInvoiceHtml;

    public $body;

    public $subject;

    public $entity;

    public $entity_id;

    public $template;

    private $entity_obj;

    private $settings_entity;

    private $settings;

    private $raw_body;

    private $raw_subject;

    /**
     * @var array
     */
    private $labels_and_values;

    public function __construct($body, $subject, $entity, $entity_id, $template)
    {
        $this->body = $body;

        $this->subject = $subject;

        $this->entity = $entity;

        $this->entity_id = $entity_id;

        $this->template = $template;

        $this->entity_obj = null;

        $this->settings_entity = null;
    }

    public function build()
    {

        return $this->setEntity()
                 ->setSettingsObject()
                 ->setTemplates()
                 ->replaceValues()
                 ->renderTemplate();
    }

    private function setEntity()
    {
        if (strlen($this->entity) > 1 && strlen($this->entity_id) > 1) {
            $class = 'App\Models\\'.ucfirst(Str::camel($this->entity));
            $this->entity_obj = $class::withTrashed()->where('id', $this->decodePrimaryKey($this->entity_id))->company()->first();
        } else {
            $this->mockEntity();
        }

        return $this;
    }

    private function setSettingsObject()
    {
        if($this->entity == 'purchaseOrder'){
            $this->settings_entity = auth()->user()->company();
            $this->settings = $this->settings_entity->settings;
        }
        elseif ($this->entity_obj->client()->exists()) {
            $this->settings_entity = $this->entity_obj->client;
            $this->settings = $this->settings_entity->getMergedSettings();
        } else {
            $this->settings_entity = auth()->user()->company();
            $this->settings = $this->settings_entity->settings;
        }

        App::forgetInstance('translator');
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->settings));

        return $this;
    }

    /* If the body / subject are not populated we need to get the defaults */
    private function setTemplates()
    {
        if (strlen($this->subject) == 0 && strlen($this->template) > 1) {
            $subject_template = str_replace('template', 'subject', $this->template);

            if (strlen($this->settings_entity->getSetting($subject_template)) > 1) {
                $this->subject = $this->settings_entity->getSetting($subject_template);
            } else {
                $this->subject = EmailTemplateDefaults::getDefaultTemplate($subject_template, $this->settings_entity->locale());
            }
        }

        if (strlen($this->body) == 0 && strlen($this->template) > 1) {
            if (strlen($this->settings_entity->getSetting($this->template)) > 1) {
                $this->body = $this->settings_entity->getSetting($this->template);
            } else {
                $this->body = EmailTemplateDefaults::getDefaultTemplate($this->template, $this->settings_entity->locale());
            }
        }

        return $this;
    }

    private function replaceValues()
    {
        $this->raw_body = $this->body;
        $this->raw_subject = $this->subject;

        if($this->entity == 'purchaseOrder'){
            $this->fakerValues();
        }
        elseif ($this->entity_obj->client()->exists()) {
            $this->entityValues($this->entity_obj->client->primary_contact()->first());
        } 
        else {
            $this->fakerValues();
        }

        return $this;
    }

    private function fakerValues()
    {
        $labels = $this->makeFakerLabels();
        $values = $this->makeFakerValues();

        $this->body = strtr($this->body, $labels);
        $this->body = strtr($this->body, $values);

        $this->subject = strtr($this->subject, $labels);
        $this->subject = strtr($this->subject, $values);

        $converter = new CommonMarkConverter([
            'allow_unsafe_links' => false,
        ]);

        $this->body = $converter->convert($this->body)->getContent();

    }

    private function entityValues($contact)
    {
        if($this->entity == 'purchaseOrder')
            $this->labels_and_values = (new VendorHtmlEngine($this->entity_obj->invitations->first()))->generateLabelsAndValues();
        else
            $this->labels_and_values = (new HtmlEngine($this->entity_obj->invitations->first()))->generateLabelsAndValues();

        $this->body = strtr($this->body, $this->labels_and_values['labels']);
        $this->body = strtr($this->body, $this->labels_and_values['values']);

        $this->subject = strtr($this->subject, $this->labels_and_values['labels']);
        $this->subject = strtr($this->subject, $this->labels_and_values['values']);

        $email_style = $this->settings_entity->getSetting('email_style');

        if ($email_style !== 'custom') {
            $this->body = DesignHelpers::parseMarkdownToHtml($this->body);
        }
    }

    private function renderTemplate()
    {
        /* wrapper */
        $email_style = $this->settings_entity->getSetting('email_style');

        $data['title'] = '';
        $data['body'] = '$body';
        $data['footer'] = '';
        $data['logo'] = auth()->user()->company()->present()->logo();

        if($this->entity_obj->client()->exists())
            $data = array_merge($data, Helpers::sharedEmailVariables($this->entity_obj->client));
        else{

            $data['signature'] = $this->settings->email_signature;
            $data['settings'] = $this->settings;
            $data['whitelabel'] = $this->entity_obj ? $this->entity_obj->company->account->isPaid() : true;
            $data['company'] = $this->entity_obj ? $this->entity_obj->company : '';
            $data['settings'] = $this->settings;

        }


        if ($email_style == 'custom') {
            $wrapper = $this->settings_entity->getSetting('email_style_custom');

            // In order to parse variables such as $signature in the body,
            // we need to replace strings with the values from HTMLEngine.

            $wrapper = strtr($wrapper, $this->labels_and_values['values']);

            /*If no custom design exists, send back a blank!*/
            if (strlen($wrapper) > 1) {
                $wrapper = $this->renderView($wrapper, $data);
            } else {
                $wrapper = '';
            }
        } elseif ($email_style == 'plain') {
            $wrapper = view($this->getTemplatePath($email_style), $data)->render();
            $injection = '';
            $wrapper = str_replace('<head>', $injection, $wrapper);
        } else {
            $wrapper = view($this->getTemplatePath('client'), $data)->render();
            $injection = '';
            $wrapper = str_replace('<head>', $injection, $wrapper);
        }

        $data = [
            'subject' => $this->subject,
            'body' => $this->body,
            'wrapper' => $wrapper,
            'raw_body' => $this->raw_body,
            'raw_subject' => $this->raw_subject,
        ];

        $this->tearDown();

        return $data;
    }

    private function mockEntity()
    {
        if(!$this->entity && $this->template && str_contains($this->template, 'purchase_order'))
            $this->entity = 'purchaseOrder';

        DB::connection(config('database.default'))->beginTransaction();

        $vendor = false;

        $client = Client::factory()->create([
            'user_id' => auth()->user()->id,
            'company_id' => auth()->user()->company()->id,
        ]);

        $contact = ClientContact::factory()->create([
            'user_id' => auth()->user()->id,
            'company_id' => auth()->user()->company()->id,
            'client_id' => $client->id,
            'is_primary' => 1,
            'send_email' => true,
        ]);

        if (! $this->entity || $this->entity == 'invoice') {
            $this->entity_obj = Invoice::factory()->create([
                'user_id' => auth()->user()->id,
                'company_id' => auth()->user()->company()->id,
                'client_id' => $client->id,
            ]);

            $invitation = InvoiceInvitation::factory()->create([
                'user_id' => auth()->user()->id,
                'company_id' => auth()->user()->company()->id,
                'invoice_id' => $this->entity_obj->id,
                'client_contact_id' => $contact->id,
            ]);
        }

        if ($this->entity == 'quote') {
            $this->entity_obj = Quote::factory()->create([
                'user_id' => auth()->user()->id,
                'company_id' => auth()->user()->company()->id,
                'client_id' => $client->id,
            ]);

            $invitation = QuoteInvitation::factory()->create([
                'user_id' => auth()->user()->id,
                'company_id' => auth()->user()->company()->id,
                'quote_id' => $this->entity_obj->id,
                'client_contact_id' => $contact->id,
            ]);
        }



        if($this->entity == 'purchaseOrder')
        {

            $vendor = Vendor::factory()->create([
                    'user_id' => auth()->user()->id,
                    'company_id' => auth()->user()->company()->id,
                ]);

            $contact = VendorContact::factory()->create([
                    'user_id' => auth()->user()->id,
                    'company_id' => auth()->user()->company()->id,
                    'vendor_id' => $vendor->id,
                    'is_primary' => 1,
                    'send_email' => true,
                ]);


            $this->entity_obj = PurchaseOrder::factory()->create([
                        'user_id' => auth()->user()->id,
                        'company_id' => auth()->user()->company()->id,
                        'vendor_id' => $vendor->id,
                    ]);

            $invitation = PurchaseOrderInvitation::factory()->create([
                        'user_id' => auth()->user()->id,
                        'company_id' => auth()->user()->company()->id,
                        'purchase_order_id' => $this->entity_obj->id,
                        'vendor_contact_id' => $contact->id,
            ]);

        }

        if($vendor)
        {

            $this->entity_obj->setRelation('invitations', $invitation);
            $this->entity_obj->setRelation('vendor', $vendor);
            $this->entity_obj->setRelation('company', auth()->user()->company());
            $this->entity_obj->load('vendor');
            $vendor->setRelation('company', auth()->user()->company());
            $vendor->load('company');

        }
        else 
        {
            $this->entity_obj->setRelation('invitations', $invitation);
            $this->entity_obj->setRelation('client', $client);
            $this->entity_obj->setRelation('company', auth()->user()->company());
            $this->entity_obj->load('client');
            $client->setRelation('company', auth()->user()->company());
            $client->load('company');
        }
    }

    private function tearDown()
    {
        DB::connection(config('database.default'))->rollBack();
    }
}
