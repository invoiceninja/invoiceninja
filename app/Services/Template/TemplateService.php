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

use App\Models\Task;
use App\Models\Quote;
use App\Models\Credit;
use App\Models\Design;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Utils\HtmlEngine;
use League\Fractal\Manager;
use App\Models\ClientContact;
use App\Models\PurchaseOrder;
use App\Utils\VendorHtmlEngine;
use App\Utils\PaymentHtmlEngine;
use Illuminate\Support\Collection;
use Twig\Extra\Intl\IntlExtension;
use App\Transformers\TaskTransformer;
use App\Transformers\QuoteTransformer;
use App\Transformers\CreditTransformer;
use App\Transformers\InvoiceTransformer;
use App\Transformers\PaymentTransformer;
use App\Transformers\ProjectTransformer;
use App\Transformers\PurchaseOrderTransformer;
use League\Fractal\Serializer\ArraySerializer;
use League\Fractal\Serializer\JsonApiSerializer;

class TemplateService
{
 
    private \DomDocument $document;

    private string $compiled_html = '';

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
    public function build(array $data): self
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
nlog($data);
        $contents = $this->document->getElementsByTagName('ninja');

        foreach ($contents as $content) {
                                        
            $template = $content->ownerDocument->saveHTML($content);

            $loader = new \Twig\Loader\FilesystemLoader(storage_path());
            $twig = new \Twig\Environment($loader);

            $string_extension = new \Twig\Extension\StringLoaderExtension();
            $twig->addExtension($string_extension);
            $twig->addExtension(new IntlExtension());

            $template = $twig->createTemplate(html_entity_decode($template));
            $template = $template->render($data);

            nlog($template);

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
     * @param array $data
     * @return self
     */
    private function parseVariables(array $data): self
    {
        $variables = $this->resolveHtmlEngine($data);

        $html = $this->getHtml();

        foreach($variables as $key => $variable) {
            $html = strtr($html, $variable['labels']);
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
        return collect($data)->map(function ($value, $key) {
            
            $processed = [];

            match ($key) {
                'invoices' => $processed = (new HtmlEngine($value->first()->invitations()->first()))->generateLabelsAndValues(),
                'quotes' => $processed = (new HtmlEngine($value->first()->invitations()->first()))->generateLabelsAndValues(),
                'credits' => $processed = (new HtmlEngine($value->first()->invitations()->first()))->generateLabelsAndValues(),
                'payments' => $processed = (new PaymentHtmlEngine($value->first(), $value->first()->client->contacts()->first()))->generateLabelsAndValues(),
                'tasks' => $processed = [],
                'projects' => $processed = [],
                'purchase_orders' => $processed = (new VendorHtmlEngine($value->first()->invitations()->first()))->generateLabelsAndValues(),
            };

            return $processed;

        })->toArray();

    }

    private function preProcessDataBlocks($data): array
    {
        return collect($data)->map(function ($value, $key){

            $processed = [];

            match ($key) {
                'invoices' => $processed = $this->processInvoices($value),
                'quotes' => $processed = $this->processQuotes($value),
                'credits' => $processed = $this->processCredits($value),
                'payments' => $processed = $this->processPayments($value),
                'tasks' => $processed = $this->processTasks($value),
                'projects' => $processed = $this->processProjects($value),
                'purchase_orders' => $processed = $this->processPurchaseOrders($value),
            };

            return $processed;

        })->toArray();
    }

    private function processInvoices($invoices): array
    {
        $it = new InvoiceTransformer();
        $it->setDefaultIncludes(['client','payments']);
        $manager = new Manager();
        $manager->parseIncludes(['client','payments','payments.type']);
        $resource = new \League\Fractal\Resource\Collection($invoices, $it, null);
        $invoices = $manager->createData($resource)->toArray();

        // nlog($invoices);

        foreach($invoices['data'] as $key => $invoice)
        {

            $invoices['data'][$key]['client'] = $invoice['client']['data'] ?? [];
            $invoices['data'][$key]['client']['contacts'] = $invoice['client']['data']['contacts']['data'] ?? [];
            $invoices['data'][$key]['payments'] = $invoice['payments']['data'] ?? [];

            if($invoice['payments']['data'] ?? false) {
                foreach($invoice['payments']['data'] as $keyx => $payment) {
                    $invoices['data'][$key]['payments'][$keyx]['paymentables']= $payment['paymentables']['data'] ?? [];
                }
            }

        }

        return $invoices['data'];
    }

    private function processQuotes($quotes): array
    {
        $it = new QuoteTransformer();
        $it->setDefaultIncludes(['client']);
        $manager = new Manager();
        $manager->setSerializer(new ArraySerializer());
        $resource = new \League\Fractal\Resource\Collection($quotes, $it, Quote::class);
        $i = $manager->createData($resource)->toArray();

        $i['client']['contacts'] = $i['client']['contacts'][ClientContact::class];
        return $i[Quote::class];

    }

    private function processCredits($credits): array
    {
        $it = new CreditTransformer();
        $it->setDefaultIncludes(['client']);
        $manager = new Manager();
        $manager->setSerializer(new ArraySerializer());
        $resource = new \League\Fractal\Resource\Collection($credits, $it, Credit::class);
        $i = $manager->createData($resource)->toArray();
        return $i[Credit::class];

    }

    private function processPayments($payments): array
    {
        $it = new PaymentTransformer();
        $it->setDefaultIncludes(['client','invoices','paymentables']);
        $manager = new Manager();
        $manager->setSerializer(new ArraySerializer());
        $resource = new \League\Fractal\Resource\Collection($payments, $it, Payment::class);
        $i = $manager->createData($resource)->toArray();
        return $i[Payment::class];

    }

    private function processTasks($tasks): array
    {
        $it = new TaskTransformer();
        $it->setDefaultIncludes(['client','tasks','project','invoice']);
        $manager = new Manager();
        $manager->setSerializer(new ArraySerializer());
        $resource = new \League\Fractal\Resource\Collection($tasks, $it, Task::class);
        $i = $manager->createData($resource)->toArray();
        return $i[Task::class];

    }

    private function processProjects($projects): array
    {

        $it = new ProjectTransformer();
        $it->setDefaultIncludes(['client','tasks']);
        $manager = new Manager();
        $manager->setSerializer(new ArraySerializer());
        $resource = new \League\Fractal\Resource\Collection($projects, $it, Project::class);
        $i = $manager->createData($resource)->toArray();
        return $i[Project::class];

    }

    private function processPurchaseOrders($purchase_orders): array
    {
        
        $it = new PurchaseOrderTransformer();
        $it->setDefaultIncludes(['vendor','expense']);
        $manager = new Manager();
        $manager->setSerializer(new ArraySerializer());
        $resource = new \League\Fractal\Resource\Collection($purchase_orders, $it, PurchaseOrder::class);
        $i = $manager->createData($resource)->toArray();
        return $i[PurchaseOrder::class];

    }
}