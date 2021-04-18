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

namespace App\Utils;

use App\DataMapper\EmailTemplateDefaults;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Invoice;
use App\Models\InvoiceInvitation;
use App\Services\PdfMaker\Designs\Utilities\DesignHelpers;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\MakesInvoiceHtml;
use App\Utils\Traits\MakesTemplateData;
use DB;
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
            $class = 'App\Models\\'.ucfirst($this->entity);
            $this->entity_obj = $class::withTrashed()->where('id', $this->decodePrimaryKey($this->entity_id))->company()->first();
        } else {
            $this->mockEntity();
        }

        return $this;
    }

    private function setSettingsObject()
    {
        if ($this->entity_obj) {
            $this->settings_entity = $this->entity_obj->client;
            $this->settings = $this->settings_entity->getMergedSettings();
        } else {
            $this->settings_entity = auth()->user()->company();
            $this->settings = $this->settings_entity->settings;
        }

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
        $this->raw_subject  = $this->subject;

        if ($this->entity_obj) {
            $this->entityValues($this->entity_obj->client->primary_contact()->first());
        } else {
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

        $this->body = $converter->convertToHtml($this->body);
    }

    private function entityValues($contact)
    {

        $data = (new HtmlEngine($this->entity_obj->invitations->first()))->generateLabelsAndValues();

        $this->body = strtr($this->body, $data['labels']);
        $this->body = strtr($this->body, $data['values']);
//        $this->body = str_replace("\n", "<br>", $this->body);

        $this->subject = strtr($this->subject, $data['labels']);
        $this->subject = strtr($this->subject, $data['values']);

        $this->body = DesignHelpers::parseMarkdownToHtml($this->body);
    }

    private function renderTemplate()
    {
        /* wrapper */
        $email_style = $this->settings_entity->getSetting('email_style');

        $data['title'] = '';
        $data['body'] = '$body';
        $data['footer'] = '';

        $data = array_merge($data, Helpers::sharedEmailVariables($this->entity_obj->client));

        if ($email_style == 'custom') {
            $wrapper = $this->settings_entity->getSetting('email_style_custom');

            /*If no custom design exists, send back a blank!*/
            if (strlen($wrapper) > 1) {
                $wrapper = $this->renderView($wrapper, $data);
            } else {
                $wrapper = '';
            }
        } else {
            $wrapper = view($this->getTemplatePath($email_style), $data)->render();
            $injection = '';
            $wrapper = str_replace('<head>', $injection, $wrapper);
        }

        $data = [
            'subject' => $this->subject,
            'body' => self::wrapElementsIntoTables(strtr($wrapper, ['$body' => '']), $this->body),
            'wrapper' => $wrapper,
            'raw_body' => $this->raw_body,
            'raw_subject' => $this->raw_subject
        ];

        $this->tearDown();

        return $data;
    }

    private function mockEntity()
    {
        DB::beginTransaction();

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

        $this->entity_obj->setRelation('invitations', $invitation);
        $this->entity_obj->setRelation('client', $client);
        $this->entity_obj->setRelation('company', auth()->user()->company());
        $this->entity_obj->load('client');
        $client->setRelation('company', auth()->user()->company());
        $client->load('company');
    }

    private function tearDown()
    {
        DB::rollBack();
    }

    public static function wrapElementsIntoTables(string $wrapper, string $body): ?string
    {
        $documents['wrapper'] = new \DOMDocument();
        $documents['wrapper']->loadHTML($wrapper);

        $documents['master'] = new \DOMDocument();

        $documents['master']->loadHTML(
          view('email.template.master', ['header' => '', 'slot' => ''])->render()
        );

        $styles = $documents['master']->getElementsByTagName('style')->item(0)->nodeValue;

        $documents['wrapper']->saveHTML();

        $documents['body'] = new \DOMDocument();
        $documents['body']->loadHTML(empty($body) ? '<div></div>' : (new CssToInlineStyles())->convert($body, $styles));

        $table_html ='
            <table style="font-family:arial,helvetica,sans-serif;" role="presentation" cellpadding="0" cellspacing="0" width="100%" border="0">
                <tbody>
                    <tr>
                        <td style="overflow-wrap:break-word;word-break:break-word;padding:5px;font-family:arial,helvetica,sans-serif;" align="left">
                            <div style="color: #000000; line-height: 140%; text-align: left; word-wrap: break-word;" id="table-content"></div>
                        </td>
                    </tr>
                </tbody>
            </table>';

        foreach ($documents['body']->getElementsByTagName('body')->item(0)->childNodes as $element) {
            $table = new \DOMDocument();

            $table->loadHTML($table_html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

            $element = $table->importNode($element, true);

            $table->getElementById('table-content')->appendChild($element);

            $node = $documents['wrapper']->importNode($table->documentElement, true);

            $documents['wrapper']->getElementById('content-wrapper')->appendChild($node);
        }

        return $documents['wrapper']->getElementById('content-wrapper')->ownerDocument->saveHTML($documents['wrapper']->getElementById('content-wrapper'));
    }
}
