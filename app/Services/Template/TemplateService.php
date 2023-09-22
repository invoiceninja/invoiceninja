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

namespace App\Services\Template;

use App\Models\Design;
use App\Utils\VendorHtmlEngine;
use App\Utils\PaymentHtmlEngine;
use Illuminate\Database\Eloquent\Collection;

class TemplateService
{
 
    private \DomDocument $document;

    private string $compiled_html = '';

    private array $standard_excludes = [
            'id',
            'client_id',
            'assigned_user_id',
            'project_id',
            'vendor_id',
            'design_id',
            'company_id',
            'recurring_id',
            'subscription_id'
    ];

    private array $purchase_excludes = [
            'id',
            'vendor_id',
            'assigned_user_id',
            'project_id',
            'vendor_id',
            'design_id',
            'company_id',
            'recurring_id',
            'subscription_id'
    ];
    
    public function __construct(public Design $template)
    {
        $this->template = $template;
        $this->init();
    }
    
    /**
     * Boot Dom Document
     *
     * @return self
     */
    private function init(): self
    {
        $this->document = new \DOMDocument();
        $this->document->validateOnParse = true;

        return $this;
    }
        
    /**
     * Iterate through all of the
     * ninja nodes
     *
     * @param array $data - the payload to be passed into the template
     * @return self
     */
    private function build(array $data): self
    {
        $this->compose()
             ->parseNinjaBlocks($data)
             ->parseVariables($data);        

        return $this;
    }
    
    public function getHtml(): string
    {
        return $this->compiled_html;
    }
    /**
     * Parses all Ninja tags in the document
     *
     * @param  array $data
     * 
     * @return self
     */
    private function parseNinjaBlocks(array $data): self
    {
        $data = $this->preProcessDataBlocks($data);
        $replacements = [];

        $contents = $this->document->getElementsByTagName('ninja');

        foreach ($contents as $content) {
                                        
            $template = $content->ownerDocument->saveHTML($content);

            $loader = new \Twig\Loader\FilesystemLoader(storage_path());
            $twig = new \Twig\Environment($loader);

            $string_extension = new \Twig\Extension\StringLoaderExtension();
            $twig->addExtension($string_extension);
                                    
            $template = $twig->createTemplate(html_entity_decode($template));
            $template = $template->render($data);

            $f = $this->document->createDocumentFragment();
            $f->appendXML($template);
            $replacements[] = $f;

        }

        foreach($contents as $key => $content) {
            $content->parentNode->replaceChild($replacements[$key], $content);
        }

        $this->save();

        return $this;

    }
    
    /**
     * Parses all variables in the document
     *
     * @return self
     */
    private function parseVariables(): self
    {
        $variables = $this->resolveHtmlEngine();

        foreach($variables as $key => $variable) {
            $html = strtr($this->getHtml(), $variable['labels']);
            $html = strtr($html, $variable['values']);
        }

        @$this->document->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $this->save();

        return $this;
    }
    
    /**
     * Saves the document and updates the compiled string.
     *
     * @return self
     */
    private function save(): self
    {
        $this->compiled_html = str_replace('%24', '$', $this->document->saveHTML());

        return $this;
    }

    /**
     * compose
     *
     * @return self
     */
    private function compose(): self
    {
        $html = '';
        $html .= $this->template->design->includes;
        $html .= $this->template->design->header;
        $html .= $this->template->design->body;
        $html .= $this->template->design->footer;

        @$this->document->loadHTML($html);

        return $this;

    }
    
    /**
     * Resolves the labels and values needed to replace the string
     * holders in the template.
     *
     * @return array
     */
    private function resolveHtmlEngine(array $data): array
    {
        return collect($data)->map(function ($key, $value) {

            $processed[$key] = [];

            match ($key) {
                'invoices' => $processed[$key] = (new HtmlEngine($value->first()->invitations()->first()))->generateLabelsAndValues(),
                'quotes' => $processed[$key] = (new HtmlEngine($value->first()->invitations()->first()))->generateLabelsAndValues(),
                'credits' => $processed[$key] = (new HtmlEngine($value->first()->invitations()->first()))->generateLabelsAndValues(),
                'payments' => $processed[$key] = (new PaymentHtmlEngine($value->first(), $value->first()->client->contacts()->first()))->generateLabelsAndValues(),
                'tasks' => $processed[$key] = [],
                'projects' => $processed[$key] = [],
                'purchase_orders' => $processed[$key] = (new VendorHtmlEngine($value->first()->invitations()->first()))->generateLabelsAndValues(),
            };

            return $processed;

        })->toArray();

    }

    private function preProcessDataBlocks($data): array
    {
        return collect($data)->map(function ($key, $value){

            $processed[$key] = [];

            match ($key) {
                'invoices' => $processed[$key] = $this->processInvoices($value),
                'quotes' => $processed[$key] = $this->processQuotes($value),
                'credits' => $processed[$key] = $this->processCredits($value),
                'payments' => $processed[$key] = $this->processPayments($value),
                'tasks' => $processed[$key] = $this->processTasks($value),
                'projects' => $processed[$key] = $this->processProjects($value),
                'purchase_orders' => $processed[$key] = $this->processPurchaseOrders($value),
            };

            return $processed;

        })->toArray();
    }

    private function processInvoices($invoices): Collection
    {
        return $invoices->makeHidden($this->standard_excludes);
    }

    private function processQuotes($quotes): Collection
    {
        return $quotes->makeHidden($this->standard_excludes);
        // return $quotes->map(function ($quote){
        // })->toArray();
    }

    private function processCredits($credits): Collection
    {
        return $credits->makeHidden($this->standard_excludes);
        // return $credits->map(function ($credit){
        // })->toArray();
    }

    private function processPayments($payments): Collection
    {
        return $payments->makeHidden([
            'id',
            'user_id',
            'assigned_user_id',
            'client_id',
            'company_id',
            'project_id',
            'vendor_id',
            'client_contact_id',
            'invitation_id',
            'company_gateway_id',
            'transaction_id',
        ]);
        // return $payments->map(function ($payment){
        // })->toArray();
    }

    private function processTasks($tasks): Collection
    {
        return $task->makeHidden([
            'id',
            'user_id',
            'assigned_user_id',
            'client_id',
            'company_id',
            'project_id',
            'invoice_id'
        ]);
        // return $tasks->map(function ($task){
        // })->toArray();
    }

    private function processProjects($projects): Collection
    {
        return $projects->makeHidden([
            'id',
            'client_id',
            'company_id',
            'user_id',
            'assigned_user_id',
            ]);

        // return $projects->map(function ($project){
        // })->toArray();
    }

    private function processPurchaseOrders($purchase_orders): array
    {
        return $projects->makeHidden($this->purchase_excludes);

        // return $purchase_orders->map(function ($purchase_order){
        // })->toArray();
    }
}