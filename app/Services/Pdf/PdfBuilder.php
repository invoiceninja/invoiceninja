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

namespace App\Services\Pdf;

use DOMDocument;
use App\Models\Quote;
use App\Models\Credit;
use App\Utils\Helpers;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use App\Utils\Traits\MakesDates;
use App\Services\Template\TemplateService;
use League\CommonMark\CommonMarkConverter;

class PdfBuilder
{
    use MakesDates;

    public PdfService $service;

    private CommonMarkConverter $commonmark;

    private float $payment_amount_total = 0;

    private float $unapplied_total = 0;

    private array $empty_elements = [];

    /**
     * an array of sections to be injected into the template
     *
     * @var array
     */
    public array $sections = [];

    /**
     * The DOM Document;
     *
     */
    public DomDocument $document;

    /**
     * @param PdfService $service
     * @return void
     */
    public function __construct(PdfService $service)
    {
        $this->service = $service;

        $this->commonmark = new CommonMarkConverter([
            'allow_unsafe_links' => false,
            // 'renderer' => [
            //     'block_separator' => "\n",
            //     'inner_separator' => "\n",
            //     'soft_break'      => "\n",
            // ],
            // 'html_input' => 'escape',
        ]);
    }

    /**
     * Builds the template sections
     *
     * @return self
     *
     */
    public function build(): self
    {
        $this->getTemplate()
            ->buildSections()
            ->getEmptyElements()
            ->updateElementProperties()
            ->parseTwigElements()
            ->updateVariables()
            ->removeEmptyElements();

        return $this;
    }

    /**
     * removeEmptyElements
     *
     * Removes any empty elements from the DomDocument, this improves the vertical spacing of the PDF
     * This also decodes any encoded HTML elements.
     *
     * @return self
     */
    private function removeEmptyElements(): self
    {

        $elements = [
            'product-table', 'task-table', 'delivery-note-table',
            'statement-invoice-table', 'statement-payment-table', 'statement-aging-table-totals',
            'statement-invoice-table-totals', 'statement-payment-table-totals', 'statement-aging-table',
            'client-details', 'vendor-details', 'swiss-qr', 'shipping-details', 'statement-credit-table', 'statement-credit-table-totals',
        ];

        foreach ($elements as $element) {

            $el = $this->document->getElementById($element);

            if ($el && $el->childElementCount === 0) {
                $el->parentNode->removeChild($el); // This removes the element completely
            }

        }

        // Decode any HTML based elements.
        $xpath = new \DOMXPath($this->document);
        $elements = $xpath->query('//*[@data-state="encoded-html"]');

        foreach ($elements as $element) {

            // Decode the HTML content
            $html = htmlspecialchars_decode($element->textContent, ENT_QUOTES | ENT_HTML5);
            $html = str_ireplace(['<br>','<?xml encoding="UTF-8">'], ['<br/>',''], $html);

            // Create a temporary document to properly parse the HTML
            $temp = new \DOMDocument();

            // Add UTF-8 wrapper and div container
            $wrappedHtml = '<?xml encoding="UTF-8"><div>' . $html . '</div>';

            @$temp->loadHTML($wrappedHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            $imported = $this->document->importNode($temp->getElementsByTagName('div')->item(0), true);

            $element->textContent = '';
            $divContent = $temp->getElementsByTagName('div')->item(0);

            if ($divContent) {

                foreach ($divContent->childNodes as $child) {
                    $imported = $this->document->importNode($child, true);
                    $element->appendChild($imported);
                }
            } else {

                $imported = $this->document->importNode($temp->documentElement, true);
                $element->appendChild($imported);

            }

            unset($temp); //releases memory immediately rather than at the end of the function

        }

        return $this;
    }


    /**
    * Final method to get compiled HTML.
    *
    * @param bool $final Whether this is the final compilation
    * @return string
    */
    public function getCompiledHTML($final = false)
    {

        $html = $this->document->saveHTML();

        return str_replace('%24', '$', $html);
    }

    /**
     * Generate the template
     *
     * @return self
     *
     */
    private function getTemplate(): self
    {
        $document = new DOMDocument();

        $document->validateOnParse = true;

        @$document->loadHTML($this->convertHtmlToEntities($this->service->designer->template));
        // @$document->loadHTML(mb_convert_encoding($this->service->designer->template, 'HTML-ENTITIES', 'UTF-8'));

        $this->document = $document;

        return $this;
    }

    /**
     * Convert HTML string to HTML entities (replacement for deprecated mb_convert_encoding)
     * Maintains exact same functionality as mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8')
     *
     * @param string $html
     * @return string
     */
    private function convertHtmlToEntities(string $html): string
    {
        // Encode all non-ASCII characters (code points 0x80 and above) as numeric HTML entities
        // This matches the exact behavior of mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8')
        return mb_encode_numericentity($html, [0x80, 0x10FFFF, 0, 0xFFFF], 'UTF-8');
    }

    /**
     * parseTwigElements
     *
     * Parses any ninja tags in the template and processes them via TWIG.
     *
     * @return self
     */
    private function parseTwigElements(): self
    {

        $replacements = [];
        // $contents = $this->document->getElementsByTagName('ninja');


        $contents = [];
        $nodeList = $this->document->getElementsByTagName('ninja');
        for ($i = 0; $i < $nodeList->length; $i++) {
            $contents[] = $nodeList->item($i);
        }


        $template_service = new TemplateService();
        $template_service->setCompany($this->service->company);
        $data = $template_service->processData($this->service->options)->getData();

        $twig = $template_service->twig;

        foreach ($contents as $content) {

            $template = $content->ownerDocument->saveHTML($content);

            $template = $twig->createTemplate(html_entity_decode($template));
            $template = $template->render($data);

            $f = $this->document->createDocumentFragment();

            // $template = str_ireplace(['<br>', '<br />'], "<br/>", $template);
            // $f->appendXML($template);

            $decoded_template = str_ireplace("<br>", "<br/>", html_entity_decode($template));
            $f->appendXML('<![CDATA[' . $decoded_template . ']]>');


            $replacements[] = $f;

        }

        foreach ($contents as $key => $content) {
            $content->parentNode->replaceChild($replacements[$key], $content);
        }

        $contents = null;

        return $this;

    }

    /**
     * setDocument
     *
     * @param  mixed $document
     * @return self
     */
    public function setDocument($document): self
    {
        $this->document = $document;

        return $this;
    }

    /**
     * Generates product entity sections
     *
     * @return self
     *
     */
    private function getProductSections(): self
    {
        $this->genericSectionBuilder()
             ->getClientDetails()
             ->getProductAndTaskTables()
             ->getProductEntityDetails()
             ->getProductTotals();

        return $this;
    }

    /**
     * mergeSections
     *
     * Merges the sections into the sections array.
     *
     * @param  array $section
     * @return self
     */
    private function mergeSections(array $section): self
    {
        $this->sections = array_merge($this->sections, $section);

        return $this;
    }

    /**
     * setSections
     *
     * Sets the sections array.
     *
     * @param  mixed $sections
     * @return self
     */
    public function setSections($sections): self
    {
        $this->sections = $sections;

        return $this;
    }

    /**
     * Generates delivery note sections
     *
     * @return self
     *
     */
    private function getDeliveryNoteSections(): self
    {
        $this->genericSectionBuilder()
             ->getProductTotals();

        $this->mergeSections([
            'client-details' => [
                'id' => 'client-details',
                'elements' => $this->clientDeliveryDetails(),
            ],
            'delivery-note-table' => [
                'id' => 'delivery-note-table',
                'elements' => $this->deliveryNoteTable(),
            ],
            'entity-details' => [
                'id' => 'entity-details',
                'elements' => $this->deliveryNoteDetails(),
            ],
        ]);

        return $this;
    }

    /**
     * Generates statement sections
     *
     * @return self
     *
     */
    private function getStatementSections(): self
    {
        $this->genericSectionBuilder();

        $this->mergeSections([
            'client-details' => [
                'id' => 'client-details',
                'elements' => $this->clientDetails(),
            ],
            'vendor-details' => [ //this block pads the grid for client / vendor / entity details
                'id' => 'vendor-details',
                'elements' => [
                    ['element' => 'tr', 'properties' => ['data-ref' => 'statement-labelx'], 'elements' => [
                        ['element' => 'th', 'properties' => [], 'content' => ""],
                        ['element' => 'th', 'properties' => [], 'content' => '<h2></h2>'],
                    ]],
                ],
            ],
            'entity-details' => [
                'id' => 'entity-details',
                'elements' => $this->statementDetails(),
            ],
            'statement-invoice-table' => [
                'id' => 'statement-invoice-table',
                'elements' => $this->statementInvoiceTable(),
            ],
            'statement-invoice-table-totals' => [
                'id' => 'statement-invoice-table-totals',
                'elements' => $this->statementInvoiceTableTotals(),
            ],
            'statement-payment-table' => [
                'id' => 'statement-payment-table',
                'elements' => $this->statementPaymentTable(),
            ],
            'statement-unapplied-payment-table' => [
                'id' => 'statement-unapplied-payment-table',
                'elements' => $this->statementUnappliedPaymentTable(),
            ],
            'statement-unapplied-payment-table-totals' => [
                'id' => 'statement-unapplied-payment-table-totals',
                'elements' => $this->statementUnappliedPaymentTableTotals(),
            ],
            'statement-payment-table-totals' => [
                'id' => 'statement-payment-table-totals',
                'elements' => $this->statementPaymentTableTotals(),
            ],
            'statement-credit-table' => [
                'id' => 'statement-credit-table',
                'elements' => $this->statementCreditTable(),
            ],
            'statement-credit-table-totals' => [
                'id' => 'statement-credit-table-totals',
                'elements' => $this->statementCreditTableTotals(),
            ],
            'statement-aging-table' => [
                'id' => 'statement-aging-table',
                'elements' => $this->statementAgingTable(),
            ],
            'table-totals' => [
                'id' => 'table-totals',
                'elements' => $this->statementTableTotals(),
            ],
        ]);

        return $this;
    }

    /**
     * Parent method for building invoice table totals
     * for statements.
     *
     * @return array
     */
    public function statementInvoiceTableTotals(): array
    {
        $outstanding = $this->service->options['invoices']->sum('balance');

        return [
            ['element' => 'div', 'content' => '$outstanding_label: ' . $this->service->config->formatMoney($outstanding)],
        ];
    }

    /**
     * Parent method for building credits table within for statements.
     *
     * @return array
     */
    public function statementCreditTable(): array
    {
        if (is_null($this->service->options['credits']) || (\array_key_exists('show_credits_table', $this->service->options) && $this->service->options['show_credits_table'] === false)) {
            return [];
        }

        $tbody = [];

        foreach ($this->service->options['credits'] as $credit) {

            $element = ['element' => 'tr', 'elements' => []];

            $element['elements'][] = ['element' => 'td', 'content' => $credit->number];
            $element['elements'][] = ['element' => 'td', 'content' => $this->translateDate($credit->date, $this->service->config->client->date_format(), $this->service->config->locale) ?: ' '];
            $element['elements'][] = ['element' => 'td', 'content' => $this->service->config->formatMoney($credit->amount) ?: ' '];
            $element['elements'][] = ['element' => 'td', 'content' => $this->service->config->formatMoney($credit->balance) ?: ' '];

            $tbody[] = $element;
        }

        return [
            ['element' => 'thead', 'elements' => $this->buildTableHeader('statement_credit')],
            ['element' => 'tbody', 'elements' => $tbody],
        ];

    }

    /**
     * Parent method for building credits table totals for statements.
     *
     * @return array
     */
    public function statementCreditTableTotals(): array
    {
        $outstanding = $this->service->options['credits']->sum('balance');

        if (\array_key_exists('show_credits_table', $this->service->options) && $this->service->options['show_credits_table'] === false) {
            return [];
        }

        return [
            ['element' => 'div', 'content' => '$credit.balance_label: ' . $this->service->config->formatMoney($outstanding)],
        ];
    }


    /**
     * Parent method for building payments table within statement.
     *
     * @return array
     */
    public function statementPaymentTable(): array
    {
        if (is_null($this->service->options['payments']) || (\array_key_exists('show_payments_table', $this->service->options) && $this->service->options['show_payments_table'] === false)) {
            return [];
        }

        $tbody = [];

        $this->payment_amount_total = 0;

        foreach ($this->service->options['invoices'] as $invoice) {
            foreach ($invoice->payments as $payment) {
                if ($payment->is_deleted) {
                    continue;
                }

                $element = ['element' => 'tr', 'elements' => []];

                $element['elements'][] = ['element' => 'td', 'content' => $invoice->number];
                $element['elements'][] = ['element' => 'td', 'content' => $this->translateDate($payment->date, $this->service->config->date_format, $this->service->config->locale) ?: '&nbsp;'];
                $element['elements'][] = ['element' => 'td', 'content' => $payment->translatedType()];
                $element['elements'][] = ['element' => 'td', 'content' => $this->service->config->formatMoney($payment->pivot->amount) ?: '&nbsp;'];

                $tbody[] = $element;

                $this->payment_amount_total += $payment->pivot->amount;

                if ($payment->pivot->refunded > 0) {

                    $refund_date = $payment->date;

                    if ($payment->refund_meta && is_array($payment->refund_meta)) {

                        $refund_array = collect($payment->refund_meta)->first(function ($meta) use ($invoice) {
                            foreach ($meta['invoices'] as $refunded_invoice) {

                                if ($refunded_invoice['invoice_id'] == $invoice->id) {
                                    return true;
                                }

                            }
                        });

                        $refund_date = $refund_array['date'];
                    }

                    $element = ['element' => 'tr', 'elements' => []];
                    $element['elements'][] = ['element' => 'td', 'content' => $invoice->number];
                    $element['elements'][] = ['element' => 'td', 'content' => $this->translateDate($refund_date, $this->service->config->date_format, $this->service->config->locale) ?: '&nbsp;'];
                    $element['elements'][] = ['element' => 'td', 'content' => ctrans('texts.refund')];
                    $element['elements'][] = ['element' => 'td', 'content' => $this->service->config->formatMoney($payment->pivot->refunded) ?: '&nbsp;'];

                    $tbody[] = $element;

                    $this->payment_amount_total -= $payment->pivot->refunded;

                }
            }
        }

        return [
            ['element' => 'thead', 'elements' => $this->buildTableHeader('statement_payment')],
            ['element' => 'tbody', 'elements' => $tbody],
        ];
    }

    /**
     * Generates the payments table totals for statements.
     *
     * @return array
     *
     */
    public function statementPaymentTableTotals(): array
    {
        if (is_null($this->service->options['payments']) || !$this->service->options['payments']->first() || (\array_key_exists('show_payments_table', $this->service->options) && $this->service->options['show_payments_table'] === false)) {
            return [];
        }

        $payment = $this->service->options['payments']->first();

        return [
            ['element' => 'div', 'content' => \sprintf('%s: %s', ctrans('texts.amount_paid'), $this->service->config->formatMoney($this->payment_amount_total))],
        ];
    }

    /**
     * Generates the unapplied payments table totals for statements.
     *
     * @return array
     */
    public function statementUnappliedPaymentTableTotals(): array
    {

        if (is_null($this->service->options['unapplied']) || !$this->service->options['unapplied']->first() || (\array_key_exists('show_payments_table', $this->service->options) && $this->service->options['show_payments_table'] === false)) {
            return [];
        }

        $payment = $this->service->options['unapplied']->first();

        return [
            ['element' => 'div', 'content' => \sprintf('%s: %s', ctrans('texts.payment_balance_on_file'), $this->service->config->formatMoney($this->unapplied_total))],
        ];

    }


    /**
     * Generates the unapplied payments table for statements.
     *
     * @return array
     *
     */
    public function statementUnappliedPaymentTable(): array
    {
        if (is_null($this->service->options['unapplied']) || !$this->service->options['unapplied']->first() || (\array_key_exists('show_payments_table', $this->service->options) && $this->service->options['show_payments_table'] === false)) {
            return [];
        }

        $tbody = [];

        $this->unapplied_total = 0;

        foreach ($this->service->options['unapplied'] as $unapplied_payment) {
            if ($unapplied_payment->is_deleted) {
                continue;
            }

            $element = ['element' => 'tr', 'elements' => []];

            $element['elements'][] = ['element' => 'td', 'content' => $unapplied_payment->number];
            $element['elements'][] = ['element' => 'td', 'content' => $this->translateDate($unapplied_payment->date, $this->service->config->date_format, $this->service->config->locale) ?: '&nbsp;'];
            $element['elements'][] = ['element' => 'td', 'content' => $this->service->config->formatMoney($unapplied_payment->amount) ?: '&nbsp;'];
            $element['elements'][] = ['element' => 'td', 'content' => $this->service->config->formatMoney($unapplied_payment->amount - $unapplied_payment->applied) ?: '&nbsp;'];

            $tbody[] = $element;

            $this->unapplied_total += round($unapplied_payment->amount - $unapplied_payment->applied, 2);

        }

        return [
            ['element' => 'thead', 'elements' => $this->buildTableHeader('statement_unapplied')],
            ['element' => 'tbody', 'elements' => $tbody],
        ];
    }

    /**
     * Generates the aging table for statements.
     *
     * @return array
     *
     */
    public function statementAgingTable(): array
    {
        if (\array_key_exists('show_aging_table', $this->service->options) && $this->service->options['show_aging_table'] === false) {
            return [];
        }

        $elements = [
            ['element' => 'thead', 'elements' => []],
            ['element' => 'tbody', 'elements' => [
                ['element' => 'tr', 'elements' => []],
            ]],
        ];

        foreach ($this->service->options['aging'] as $column => $value) {
            $elements[0]['elements'][] = ['element' => 'th', 'content' => $column];
            $elements[1]['elements'][] = ['element' => 'td', 'content' => $value];
        }

        return $elements;
    }


    /**
     * Generates the statement aging table
     *
     * @return array
     *
     */
    public function statementCreditsTable(): array
    {
        if (\array_key_exists('show_credits_table', $this->service->options) && $this->service->options['show_credits_table'] === false) {
            return [];
        }

        $elements = [
            ['element' => 'thead', 'elements' => []],
            ['element' => 'tbody', 'elements' => [
                ['element' => 'tr', 'elements' => []],
            ]],
        ];

        foreach ($this->service->options['credits'] as $column => $value) {
            $elements[0]['elements'][] = ['element' => 'th', 'content' => $column];
            $elements[1]['elements'][] = ['element' => 'td', 'content' => $value];
        }

        return $elements;
    }

    /**
     * Generates the purchase order sections
     *
     * @return self
     *
     */
    private function getPurchaseOrderSections(): self
    {
        $this->genericSectionBuilder()
             ->getProductAndTaskTables()
             ->getProductTotals();

        $this->mergeSections([
            'vendor-details' => [
                'id' => 'vendor-details',
                'elements' => $this->vendorDetails(),
            ],
            'shipping-details' => [
                'id' => 'shipping-details',
                'elements' => $this->shippingDetails(),
            ],
            'entity-details' => [
                'id' => 'entity-details',
                'elements' => $this->purchaseOrderDetails(),
            ],
        ]);

        return $this;
    }

    /**
     * Generates the generic section which apply
     * across all design templates
     *
     * @return self
     *
     */
    private function genericSectionBuilder(): self
    {
        $this->mergeSections([
            'company-details' => [
                'id' => 'company-details',
                'elements' => $this->companyDetails(),
            ],
            'company-address' => [
                'id' => 'company-address',
                'elements' => $this->companyAddress(),
            ],
            'footer-elements' => [
                'id' => 'footer',
                'elements' => [],
            ],
        ]);

        return $this;
    }

    /**
     * Generates the invoices table for statements
     *
     * @return array
     *
     */
    public function statementInvoiceTable(): array
    {
        $tbody = [];

        $date_format = $this->service->config->client->date_format();

        foreach ($this->service->options['invoices'] as $invoice) {
            $element = ['element' => 'tr', 'elements' => []];

            $element['elements'][] = ['element' => 'td', 'content' => $invoice->number];
            $element['elements'][] = ['element' => 'td', 'content' => $this->translateDate($invoice->date, $date_format, $this->service->config->locale) ?: ' '];
            $element['elements'][] = ['element' => 'td', 'content' => $this->translateDate($invoice->due_date, $date_format, $this->service->config->locale) ?: ' '];
            $element['elements'][] = ['element' => 'td', 'content' => $this->service->config->formatMoney($invoice->amount) ?: ' '];
            $element['elements'][] = ['element' => 'td', 'content' => $this->service->config->formatMoney($invoice->balance) ?: ' '];

            $tbody[] = $element;
        }

        return [
            ['element' => 'thead', 'elements' => $this->buildTableHeader('statement_invoice')],
            ['element' => 'tbody', 'elements' => $tbody],
        ];
    }

    /**
     * Filters the visible elements for a table row and also
     * assigned the left and right radius classes to the first and last cells
     *
     * @param  array $element
     * @return array
     */
    private function parseVisibleElements(array $element): array
    {

        $visible_elements = array_filter($element['elements'], function ($el) {
            if (isset($el['properties']['visi']) && $el['properties']['visi']) {
                return true;
            }
            return false;
        });

        if (!empty($visible_elements)) {
            $first_visible = array_key_first($visible_elements);
            $last_visible = array_key_last($visible_elements);

            // Add class to first visible cell
            if (!isset($element['elements'][$first_visible]['properties']['class'])) { //@phpstan-ignore-line
                $element['elements'][$first_visible]['properties']['class'] = 'left-radius';
            } else {
                $element['elements'][$first_visible]['properties']['class'] .= ' left-radius';
            }

            // Add class to last visible cell
            if (!isset($element['elements'][$last_visible]['properties']['class'])) {
                $element['elements'][$last_visible]['properties']['class'] = 'right-radius';
            } else {
                $element['elements'][$last_visible]['properties']['class'] .= ' right-radius';
            }
        }

        // Then, filter the elements array
        $element['elements'] = array_map(function ($el) {
            if (isset($el['properties']['visi'])) {
                if ($el['properties']['visi'] === false) {
                    $el['properties']['style'] = 'display: none;';
                }
                unset($el['properties']['visi']);
            }
            return $el;
        }, $element['elements']);

        return $element;

    }


    /**
     * Generate the structure of table body. (<tbody/>)
     *
     * @param string $type "$product" or "$task"
     * @return array
     *
     */
    public function buildTableBody(string $type): array
    {

        $elements = [];

        $items = $this->transformLineItems($this->service->config->entity->line_items, $type);

        $this->processNewLines($items);

        if (count($items) == 0) {
            return [];
        }

        $_type = Str::startsWith($type, '$') ? ltrim($type, '$') : $type;

        $column_visibility = $this->getColumnVisibility($this->service->config->entity->line_items, $_type);

        if ($type == PdfService::DELIVERY_NOTE) {
            $product_customs = [false, false, false, false];

            foreach ($items as $row) {
                for ($i = 0; $i < count($product_customs); $i++) {
                    if (!empty($row['delivery_note.delivery_note' . ($i + 1)])) {
                        $product_customs[$i] = true;
                    }
                }
            }

            foreach ($items as $row) {
                $element = ['element' => 'tr', 'elements' => []];

                $element['elements'][] = ['element' => 'td', 'content' => $row['delivery_note.product_key'], 'properties' => ['data-ref' => 'delivery_note_table.product_key-td','visi' => $this->visibilityCheck($column_visibility, 'product_key')]];
                $element['elements'][] = ['element' => 'td', 'content' => $row['delivery_note.notes'], 'properties' => ['data-ref' => 'delivery_note_table.notes-td','visi' => $this->visibilityCheck($column_visibility, 'notes')]];
                $element['elements'][] = ['element' => 'td', 'content' => $row['delivery_note.quantity'], 'properties' => ['data-ref' => 'delivery_note_table.quantity-td','visi' => $this->visibilityCheck($column_visibility, 'quantity')]];

                for ($i = 0; $i < count($product_customs); $i++) {
                    if ($product_customs[$i]) {
                        $element['elements'][] = ['element' => 'td', 'content' => $row['delivery_note.delivery_note' . ($i + 1)], 'properties' => ['data-ref' => 'delivery_note_table.product' . ($i + 1) . '-td','visi' => $this->visibilityCheck($column_visibility, 'product' . ($i + 1))]];
                    }
                }

                $element = $this->parseVisibleElements($element);

                $elements[] = $element;
            }

            return $elements;
        }

        $table_type = "{$_type}_columns";

        //Handle custom quote columns
        if ($_type == 'product' && $this->service->config->entity instanceof Quote && !$this->service->config->settings?->sync_invoice_quote_columns) {
            $table_type = "product_quote_columns";
        }

        foreach ($items as $row) {
            $element = ['element' => 'tr', 'elements' => []];
            //checks if we have custom columns in the options array with key $product/$task - looks like unused functionality
            if (isset($this->service->options[$type]) && !empty($this->service->options[$type])) {

                $document = new DOMDocument();
                $document->loadHTML($this->service->options[$type], LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

                $td = $document->getElementsByTagName('tr')->item(0);

                if ($td) {
                    foreach ($td->childNodes as $child) {
                        if ($child->nodeType !== 1) {
                            continue;
                        }

                        if ($child->tagName !== 'td') {
                            continue;
                        }

                        $element['elements'][] = ['element' => 'td', 'content' => strtr($child->nodeValue, $row)];
                    }
                }
            } else {

                foreach ($this->service->config->pdf_variables[$table_type] as $key => $cell) {
                    // We want to keep aliases like these:
                    // $task.cost => $task.rate
                    // $task.quantity => $task.hours

                    if ($cell == '$task.rate') {
                        $element['elements'][] = ['element' => 'td', 'content' => $row['$task.cost'], 'properties' => ['data-ref' => 'task_table-task.cost-td', 'visi' => $this->visibilityCheck($column_visibility, $cell)]];
                    } elseif ($cell == '$product.discount' && !$this->service->company->enable_product_discount) {
                        $element['elements'][] = ['element' => 'td', 'content' => $row['$product.discount'], 'properties' => ['data-ref' => 'product_table-product.discount-td', 'style' => 'display: none;']];
                    } elseif ($cell == '$task.hours') {
                        $element['elements'][] = ['element' => 'td', 'content' => $row['$task.quantity'], 'properties' => ['data-ref' => 'task_table-task.hours-td', 'visi' => $this->visibilityCheck($column_visibility, $cell)]];
                    } elseif ($cell == '$product.tax_rate1') {
                        $element['elements'][] = ['element' => 'td', 'content' => $row[$cell], 'properties' => ['data-ref' => 'product_table-product.tax1-td', 'visi' => $this->visibilityCheck($column_visibility, $cell)]];
                    } elseif ($cell == '$product.tax_rate2') {
                        $element['elements'][] = ['element' => 'td', 'content' => $row[$cell], 'properties' => ['data-ref' => 'product_table-product.tax2-td', 'visi' => $this->visibilityCheck($column_visibility, $cell)]];
                    } elseif ($cell == '$product.tax_rate3') {
                        $element['elements'][] = ['element' => 'td', 'content' => $row[$cell], 'properties' => ['data-ref' => 'product_table-product.tax3-td', 'visi' => $this->visibilityCheck($column_visibility, $cell)]];
                    } elseif ($cell == '$task.discount' && !$this->service->company->enable_product_discount) {
                        $element['elements'][] = ['element' => 'td', 'content' => $row['$task.discount'], 'properties' => ['data-ref' => 'task_table-task.discount-td', 'style' => 'display: none;']];
                    } elseif ($cell == '$task.tax_rate1') {
                        $element['elements'][] = ['element' => 'td', 'content' => $row[$cell], 'properties' => ['data-ref' => 'task_table-task.tax1-td', 'visi' => $this->visibilityCheck($column_visibility, $cell)]];
                    } elseif ($cell == '$task.tax_rate2') {
                        $element['elements'][] = ['element' => 'td', 'content' => $row[$cell], 'properties' => ['data-ref' => 'task_table-task.tax2-td', 'visi' => $this->visibilityCheck($column_visibility, $cell)]];
                    } elseif ($cell == '$task.tax_rate3') {
                        $element['elements'][] = ['element' => 'td', 'content' => $row[$cell], 'properties' => ['data-ref' => 'task_table-task.tax3-td', 'visi' => $this->visibilityCheck($column_visibility, $cell)]];
                    } elseif ($cell == '$product.unit_cost' || $cell == '$task.rate') {
                        $element['elements'][] = ['element' => 'td', 'content' => $row[$cell], 'properties' => ['style' => 'white-space: nowrap;', 'data-ref' => "{$_type}_table-" . substr($cell, 1) . '-td', 'visi' => $this->visibilityCheck($column_visibility, $cell)]];
                    } else {
                        $element['elements'][] = ['element' => 'td', 'content' => $row[$cell], 'properties' => ['data-ref' => "{$_type}_table-" . substr($cell, 1) . '-td', 'visi' => $this->visibilityCheck($column_visibility, $cell)]];
                    }
                }
            }

            $element = $this->parseVisibleElements($element);

            $elements[] = $element;
        }

        $document = null;

        return $elements;
    }

    /**
     * Formats the line items for display.
     *
     * @param array $items
     * @param string $table_type
     *
     * @return array
     */
    public function transformLineItems($items, $table_type = '$product'): array
    {
        $data = [];

        $locale_info = localeconv();

        foreach ($items as $key => $item) {
            /** @var \App\DataMapper\InvoiceItem $item */

            if ($table_type == '$product' && $item->type_id != 1) {
                if ($item->type_id != 4 && $item->type_id != 6 && $item->type_id != 5) {
                    continue;
                }
            }

            if ($table_type == '$task' && $item->type_id != 2) {
                continue;
            }

            $helpers = new Helpers();
            $_table_type = ltrim($table_type, '$'); // From $product -> product.

            //2025-01-28 not sure how we ever got ->item and ->service....
            $data[$key][$table_type.'.product_key'] = $item->product_key ?? $item->item;
            $data[$key][$table_type.'.item'] = $item->item ?? $item->product_key;
            $data[$key][$table_type.'.service'] = $item->service ?? $item->product_key;

            $currentDateTime = null;
            if (isset($this->service->config->entity->next_send_date)) {
                $currentDateTime = Carbon::parse($this->service->config->entity->next_send_date);
            }

            $data[$key][$table_type.'.notes'] = Helpers::processReservedKeywords($item->notes, $this->service->config->currency_entity, $currentDateTime);
            $data[$key][$table_type.'.description'] = &$data[$key][$table_type.'.notes'];

            $data[$key][$table_type.".{$_table_type}1"] = strlen($item->custom_value1) >= 1 ? $helpers->formatCustomFieldValue($this->service->company->custom_fields, "{$_table_type}1", $item->custom_value1, $this->service->config->currency_entity) : '';
            $data[$key][$table_type.".{$_table_type}2"] = strlen($item->custom_value2) >= 1 ? $helpers->formatCustomFieldValue($this->service->company->custom_fields, "{$_table_type}2", $item->custom_value2, $this->service->config->currency_entity) : '';
            $data[$key][$table_type.".{$_table_type}3"] = strlen($item->custom_value3) >= 1 ? $helpers->formatCustomFieldValue($this->service->company->custom_fields, "{$_table_type}3", $item->custom_value3, $this->service->config->currency_entity) : '';
            $data[$key][$table_type.".{$_table_type}4"] = strlen($item->custom_value4) >= 1 ? $helpers->formatCustomFieldValue($this->service->company->custom_fields, "{$_table_type}4", $item->custom_value4, $this->service->config->currency_entity) : '';

            if ($item->quantity > 0 || $item->cost > 0) {
                $data[$key][$table_type.'.quantity'] = $this->service->config->formatValueNoTrailingZeroes($item->quantity);

                $data[$key][$table_type.'.unit_cost'] = $this->service->config->formatMoneyNoRounding($item->cost);

                $data[$key][$table_type.'.cost'] = $this->service->config->formatMoney($item->cost);

                $data[$key][$table_type.'.line_total'] = $this->service->config->formatMoneyNoRounding($item->line_total);
            } else {
                $data[$key][$table_type.'.quantity'] = '';

                $data[$key][$table_type.'.unit_cost'] = '';

                $data[$key][$table_type.'.cost'] = '';

                $data[$key][$table_type.'.line_total'] = '';
            }

            if (property_exists($item, 'gross_line_total')) {
                $data[$key][$table_type.'.gross_line_total'] = ($item->gross_line_total == 0) ? '' : $this->service->config->formatMoney($item->gross_line_total);
            } else {
                $data[$key][$table_type.'.gross_line_total'] = '';
            }

            if (property_exists($item, 'tax_amount')) {
                $data[$key][$table_type.'.tax_amount'] = ($item->tax_amount == 0) ? '' : $this->service->config->formatMoney($item->tax_amount);
            } else {
                $data[$key][$table_type.'.tax_amount'] = '';
            }

            if (isset($item->discount) && $item->discount > 0) {
                if ($item->is_amount_discount) {
                    $data[$key][$table_type.'.discount'] = $this->service->config->formatMoney($item->discount);
                } else {
                    $data[$key][$table_type.'.discount'] = $this->service->config->formatValueNoTrailingZeroes(floatval($item->discount)).'%';
                }
            } else {
                $data[$key][$table_type.'.discount'] = '';
            }

            if (isset($item->tax_rate1)) {
                $data[$key][$table_type.'.tax_rate1'] = $this->service->config->formatValueNoTrailingZeroes(floatval($item->tax_rate1)).'%';
                $data[$key][$table_type.'.tax1'] = &$data[$key][$table_type.'.tax_rate1'];
            }

            if (isset($item->tax_rate2)) {
                $data[$key][$table_type.'.tax_rate2'] = $this->service->config->formatValueNoTrailingZeroes(floatval($item->tax_rate2)).'%';
                $data[$key][$table_type.'.tax2'] = &$data[$key][$table_type.'.tax_rate2'];
            }

            if (isset($item->tax_rate3)) {
                $data[$key][$table_type.'.tax_rate3'] = $this->service->config->formatValueNoTrailingZeroes(floatval($item->tax_rate3)).'%';
                $data[$key][$table_type.'.tax3'] = &$data[$key][$table_type.'.tax_rate3'];
            }

            $data[$key]['task_id'] = property_exists($item, 'task_id') ? $item->task_id : '';
        }

        return $data;
    }


    /**
     * Filters the visible columns for a table row.
     *
     * @param  array $items
     * @param  string $type_id
     *
     * @return array
     */
    private function getColumnVisibility(array $items, string $type_id): array
    {

        // Convert type_id to numeric
        $type_id = $type_id === 'product' ? '1' : '2';

        // Filter items by type_id
        $filtered_items = collect($items)->filter(function ($item) use ($type_id) {
            return $item->type_id == $type_id ||
                ($type_id == '1' && ($item->type_id == '4' || $item->type_id == '5' || $item->type_id == '6'));
        });

        // Transform the items first
        $transformed_items = $this->transformLineItems(
            $filtered_items->toArray(),
            $type_id === '1' ? '$product' : '$task'
        );

        $columns = [];

        // Initialize all columns as empty
        if (!empty($transformed_items)) {
            $firstRow = reset($transformed_items);
            foreach (array_keys($firstRow) as $column) {
                $columns[$column] = true;
            }
        }

        // Check each column for non-empty values
        foreach ($transformed_items as $row) {
            foreach ($row as $key => $value) {
                if (!empty($value)) {
                    $columns[$key] = false;
                }
            }
        }

        return $columns;

    }

    /**
    * Generate the structure of table headers. (<thead/>)
    *
    * @param string $type "product" or "task"
    * @return array
    *
    */
    public function buildTableHeader(string $type): array
    {

        $elements = [];

        // Some of column can be aliased. This is simple workaround for these.
        $aliases = [
            '$product.product_key' => '$product.item',
            '$task.product_key' => '$task.service',
            '$task.rate' => '$task.cost',
        ];

        $table_type = "{$type}_columns";

        $column_type = $type;

        if ($type == 'product' && $this->service->config->entity instanceof Quote && !$this->service->config->settings?->sync_invoice_quote_columns) {
            $table_type = "product_quote_columns";
            $column_type = 'product_quote';
        }

        $this->processTaxColumns($column_type);

        $column_visibility = $this->getColumnVisibility($this->service->config->entity->line_items, $type);

        foreach ($this->service->config->pdf_variables[$table_type] as $column) {

            if (array_key_exists($column, $aliases)) {
                $elements[] = ['element' => 'th', 'content' => $aliases[$column] . '_label', 'properties' => ['data-ref' => "{$type}_table-" . substr($aliases[$column], 1) . '-th', 'visi' => $this->visibilityCheck($column_visibility, $column)]];
            } elseif ($column == '$product.discount' && !$this->service->company->enable_product_discount) {
                $elements[] = ['element' => 'th', 'content' => $column . '_label', 'properties' => ['data-ref' => "{$type}_table-" . substr($column, 1) . '-th', 'style' => 'display: none;']];
            } elseif ($column == '$product.tax_rate1') {
                $elements[] = ['element' => 'th', 'content' => $column . '_label', 'properties' => ['data-ref' => "{$type}_table-product.tax1-th", 'visi' => $this->visibilityCheck($column_visibility, $column)]];
            } elseif ($column == '$product.tax_rate2') {
                $elements[] = ['element' => 'th', 'content' => $column . '_label', 'properties' => ['data-ref' => "{$type}_table-product.tax2-th", 'visi' => $this->visibilityCheck($column_visibility, $column)]];
            } elseif ($column == '$product.tax_rate3') {
                $elements[] = ['element' => 'th', 'content' => $column . '_label', 'properties' => ['data-ref' => "{$type}_table-product.tax3-th", 'visi' => $this->visibilityCheck($column_visibility, $column)]];
            } elseif ($column == '$task.discount' && !$this->service->company->enable_product_discount) {
                $elements[] = ['element' => 'th', 'content' => $column . '_label', 'properties' => ['data-ref' => "{$type}_table-" . substr($column, 1) . '-th', 'style' => 'display: none;']];
            } elseif ($column == '$task.tax_rate1') {
                $elements[] = ['element' => 'th', 'content' => '$task.tax_name1_label', 'properties' => ['data-ref' => "{$type}_table-task.tax1-th", 'visi' => $this->visibilityCheck($column_visibility, $column)]];
            } elseif ($column == '$task.tax_rate2') {
                $elements[] = ['element' => 'th', 'content' => '$task.tax_name1_label', 'properties' => ['data-ref' => "{$type}_table-task.tax2-th", 'visi' => $this->visibilityCheck($column_visibility, $column)]];
            } elseif ($column == '$task.tax_rate3') {
                $elements[] = ['element' => 'th', 'content' => '$task.tax_name1_label', 'properties' => ['data-ref' => "{$type}_table-task.tax3-th", 'visi' => $this->visibilityCheck($column_visibility, $column)]];
            } else {
                $elements[] = ['element' => 'th', 'content' => $column . '_label', 'properties' => ['data-ref' => "{$type}_table-" . substr($column, 1) . '-th', 'visi' => $this->visibilityCheck($column_visibility, $column)]];
            }
        }

        $visible_elements = array_filter($elements, function ($element) {
            return $element['properties']['visi'] ?? true;
        });

        if (!empty($visible_elements)) {
            $first_visible = array_key_first($visible_elements);
            $last_visible = array_key_last($visible_elements);

            // Add class to first visible element
            if (!isset($elements[$first_visible]['properties']['class'])) {//@phpstan-ignore-line
                $elements[$first_visible]['properties']['class'] = 'left-radius';
            } else {
                $elements[$first_visible]['properties']['class'] .= ' left-radius';
            }

            // Add class to last visible element
            if (!isset($elements[$last_visible]['properties']['class'])) {
                $elements[$last_visible]['properties']['class'] = 'right-radius';
            } else {
                $elements[$last_visible]['properties']['class'] .= ' right-radius';
            }
        }

        $elements = array_map(function ($element) {
            if (isset($element['properties']['visi'])) {
                if ($element['properties']['visi'] === false) {
                    $element['properties']['style'] = 'display: none;';
                }
                unset($element['properties']['visi']);
            }
            return $element;
        }, $elements);

        return $elements;
    }

    /**
     * visibilityCheck
     *
     * @param  array $column_visibility
     * @param  string $column
     * @return bool
     */
    private function visibilityCheck(array $column_visibility, string $column): bool
    {
        if (!$this->service->config->settings->hide_empty_columns_on_pdf) {
            return true;
        }

        if (array_key_exists($column, $column_visibility)) {
            return !$column_visibility[$column];
        }

        return true;
    }

    /**
     * This method will help us decide either we show
     * one "tax rate" column in the table or 3 custom tax rates.
     *
     * Logic below will help us calculate that & inject the result in the
     * global state of the $context (design state).
     *
     * @param string $type "product" or "task"
     * @return void
     */
    public function processTaxColumns(string $type): void
    {
        $column_type = 'product';

        if ($type == 'product') {
            $type_id = 1;
        }

        if ($type == 'task') {
            $column_type = 'task';
            $type_id = 2;
        }

        /** 17-05-2023 need to explicity define product_quote here */
        if ($type == 'product_quote') {
            $type_id = 1;
            $column_type = 'product_quote';
            $type = 'product';
        }

        // At the moment we pass "task" or "product" as type.
        // However, "pdf_variables" contains "$task.tax" or "$product.tax" <-- Notice the dollar sign.
        // This sprintf() will help us convert "task" or "product" into "$task" or "$product" without
        // evaluating the variable.

        if (in_array(sprintf('%s%s.tax', '$', $type), (array) $this->service->config->pdf_variables["{$column_type}_columns"])) {
            $line_items = collect($this->service->config->entity->line_items)->filter(function ($item) use ($type_id) {
                return $item->type_id == $type_id; // = != == bad comparison operator fix 2023-11-12
            });

            $tax1 = $line_items->where('tax_name1', '<>', '')->where('type_id', $type_id)->count();
            $tax2 = $line_items->where('tax_name2', '<>', '')->where('type_id', $type_id)->count();
            $tax3 = $line_items->where('tax_name3', '<>', '')->where('type_id', $type_id)->count();

            $taxes = [];

            if ($tax1 > 0) {
                array_push($taxes, sprintf('%s%s.tax_rate1', '$', $type));
            }

            if ($tax2 > 0) {
                array_push($taxes, sprintf('%s%s.tax_rate2', '$', $type));
            }

            if ($tax3 > 0) {
                array_push($taxes, sprintf('%s%s.tax_rate3', '$', $type));
            }

            $key = array_search(sprintf('%s%s.tax', '$', $type), $this->service->config->pdf_variables["{$column_type}_columns"], true);

            if ($key !== false) {
                array_splice($this->service->config->pdf_variables["{$column_type}_columns"], $key, 1, $taxes);
            }
        }
    }

    /**
     * Generates the totals table for
     * the product type entities
     *
     * @return self
     *
     */
    private function getProductTotals(): self
    {
        $this->mergeSections([
            'table-totals' => [
                'id' => 'table-totals',
                'elements' => $this->getTableTotals(),
            ],
        ]);

        return $this;
    }

    /**
     * Generates the entity details for
     * Credits
     * Quotes
     * Invoices
     *
     * @return self
     *
     */
    private function getProductEntityDetails(): self
    {
        if (in_array($this->service->config->entity_string, ['recurring_invoice', 'invoice'])) {
            $this->mergeSections([
                'entity-details' => [
                    'id' => 'entity-details',
                    'elements' => $this->invoiceDetails(),
                ],
            ]);
        } elseif ($this->service->config->entity_string == 'quote') {
            $this->mergeSections([
                'entity-details' => [
                    'id' => 'entity-details',
                    'elements' => $this->quoteDetails(),
                ],
            ]);
        } elseif ($this->service->config->entity_string == 'credit') {
            $this->mergeSections([
                'entity-details' => [
                    'id' => 'entity-details',
                    'elements' => $this->creditDetails(),
                ],
            ]);
        }

        return $this;
    }

    /**
     * Parent entry point when building sections of the design content
     *
     * @return self
     *
     */
    private function buildSections(): self
    {
        return match ($this->service->document_type) {
            PdfService::PRODUCT => $this->getProductSections(),
            PdfService::DELIVERY_NOTE => $this->getDeliveryNoteSections(),
            PdfService::STATEMENT => $this->getStatementSections(),
            PdfService::PURCHASE_ORDER => $this->getPurchaseOrderSections(),
            default => $this->getProductSections(),
        };
    }

    /**
     * Generates the table totals for statements
     *
     * @return array
     *
     */
    private function statementTableTotals(): array
    {
        return [
            ['element' => 'div', 'properties' => ['style' => 'display: flex; flex-direction: column;'], 'elements' => [
                ['element' => 'div', 'properties' => ['style' => 'display: block; align-items: flex-start; page-break-inside: avoid; visible !important;'], 'elements' => [
                    ['element' => 'img', 'properties' => ['src' => '$invoiceninja.whitelabel', 'style' => 'height: 2.5rem; margin-top: 1.5rem;', 'hidden' => $this->service->company->account->isPaid() ? 'true' : 'false', 'id' => 'invoiceninja-whitelabel-logo']],
                ]],
            ]],
        ];
    }

    /**
     * Performs a variable check to ensure
     * the variable exists
     *
     * @param  string $variable
     * @return bool
     *
     */
    public function entityVariableCheck(string $variable): bool
    {
        // When it comes to invoice balance, we'll always show it.
        if ($variable == '$invoice.total') {
            return false;
        }

        // Some variables don't map 1:1 to table columns. This gives us support for such cases.
        $aliases = [
            '$quote.balance_due' => 'partial',
            '$purchase_order.po_number' => 'number',
            '$purchase_order.total' => 'amount',
            '$purchase_order.due_date' => 'due_date',
            '$purchase_order.balance_due' => 'balance_due',
            '$credit.valid_until' => 'due_date',
        ];

        try {
            $_variable = explode('.', $variable)[1];
        } catch (\Exception $e) {
            throw new \Exception('Company settings seems to be broken. Missing $this->service->config->entity.variable type.');
        }

        if (\in_array($variable, \array_keys($aliases))) {
            $_variable = $aliases[$variable];
        }

        if (is_null($this->service->config->entity->{$_variable}) || empty($this->service->config->entity->{$_variable})) {
            return true;
        }

        return false;
    }

    //First pass done, need a second pass to abstract this content completely.
    /**
     * Builds the table totals for all entities, we'll want to split this
     *
     * @return array
     *
     */
    public function getTableTotals(): array
    {

        $_variables = $this->service->html_variables;

        $variables = $this->service->config->pdf_variables['total_columns'];
        $show_terms_label = $this->entityVariableCheck('$entity.terms') ? 'display: none;' : '';

        $elements = [
            ['element' => 'div', 'properties' => ['style' => 'display: flex; flex-direction: column;'], 'elements' => [
                ['element' => 'div', 'properties' => ['data-ref' => 'total_table-public_notes', 'style' => 'text-align: left;'], 'elements' => [
                    ['element' => 'div', 'content' => strtr(str_replace(["labels", "values"], ["",""], $_variables['values']['$entity.public_notes']), $_variables)]
                ]],
                ['element' => 'div', 'content' => '', 'properties' => ['style' => 'text-align: left; display: flex; flex-direction: column; page-break-inside: auto;'], 'elements' => [
                    ['element' => 'div', 'content' => '$entity.terms_label: ', 'properties' => ['data-ref' => 'total_table-terms-label', 'style' => "font-weight:bold; text-align: left; margin-top: 1rem; {$show_terms_label}"]],
                    ['element' => 'div', 'content' => strtr(str_replace("labels", "", $_variables['values']['$entity.terms']), $_variables['labels']), 'properties' => ['data-ref' => 'total_table-terms', 'style' => 'text-align: left;']],
                ]],
                ['element' => 'img', 'properties' => ['style' => 'max-width: 50%; height: auto;', 'src' => '$contact.signature', 'id' => 'contact-signature']],
                ['element' => 'div', 'properties' => ['style' => 'display: flex; align-items: flex-start; page-break-inside: auto;'], 'elements' => [
                    ['element' => 'img', 'properties' => ['src' => '$invoiceninja.whitelabel', 'style' => 'height: 2.5rem; margin-top: 1.5rem;', 'hidden' => $this->service->company->account->isPaid() ? 'true' : 'false', 'id' => 'invoiceninja-whitelabel-logo']],
                ]],
            ]],
            ['element' => 'div', 'properties' => ['class' => 'totals-table-right-side', 'dir' => '$dir'], 'elements' => []],
        ];


        if ($this->service->document_type == PdfService::DELIVERY_NOTE) {
            return $elements;
        }

        if ($this->service->config->entity instanceof Quote) {
            // We don't want to show Balanace due on the quotes.
            if (in_array('$outstanding', $variables)) {
                $variables = \array_diff($variables, ['$outstanding']);
            }

            if ($this->service->config->entity->partial > 0) {
                $variables[] = '$partial_due';
            }
        }

        if ($this->service->config->entity instanceof Credit) {
            // We don't want to show Balanace due on the quotes.
            if (in_array('$paid_to_date', $variables)) {
                $variables = \array_diff($variables, ['$paid_to_date']);
            }
        }

        foreach (['discount'] as $property) {
            $variable = sprintf('%s%s', '$', $property);

            if (
                !is_null($this->service->config->entity->{$property}) &&
                !empty($this->service->config->entity->{$property}) &&
                $this->service->config->entity->{$property} != 0
            ) {
                continue;
            }

            $variables = array_filter($variables, function ($m) use ($variable) {
                return $m != $variable;
            });
        }

        foreach ($variables as $variable) {
            if ($variable == '$total_taxes') {
                $taxes = $this->service->config->entity->calc()->getTotalTaxMap();

                if (!$taxes) {
                    continue;
                }

                foreach ($taxes as $i => $tax) {
                    $elements[1]['elements'][] = ['element' => 'div', 'elements' => [
                        ['element' => 'p', 'content', 'content' => $tax['name'], 'properties' => ['data-ref' => 'totals-table-total_tax_' . $i . '-label']],
                        ['element' => 'p', 'content', 'content' => $this->service->config->formatMoney($tax['total']), 'properties' => ['data-ref' => 'totals-table-total_tax_' . $i]],
                    ]];
                }
            } elseif ($variable == '$line_taxes') {
                $taxes = $this->service->config->entity->calc()->getTaxMap();

                if (!$taxes) {
                    continue;
                }

                foreach ($taxes as $i => $tax) {
                    $elements[1]['elements'][] = ['element' => 'div', 'elements' => [
                        ['element' => 'p', 'content', 'content' => $tax['name'], 'properties' => ['data-ref' => 'totals-table-line_tax_' . $i . '-label']],
                        ['element' => 'p', 'content', 'content' => $this->service->config->formatMoney($tax['total']), 'properties' => ['data-ref' => 'totals-table-line_tax_' . $i]],
                    ]];
                }
            } elseif (Str::startsWith($variable, '$custom_surcharge')) {
                $_variable = ltrim($variable, '$'); // $custom_surcharge1 -> custom_surcharge1

                $visible = intval(str_replace(['0','.'], '', ($this->service->config->entity->{$_variable} ?? ''))) != 0;

                $elements[1]['elements'][] = ['element' => 'div', 'elements' => [
                    ['element' => 'p', 'content' => $variable . '_label', 'properties' => ['hidden' => !$visible, 'data-ref' => 'totals_table-' . substr($variable, 1) . '-label']],
                    ['element' => 'p', 'content' => $variable, 'properties' => ['hidden' => !$visible, 'data-ref' => 'totals_table-' . substr($variable, 1)]],
                ]];
            } elseif (Str::startsWith($variable, '$custom')) {
                $field = explode('_', $variable);
                $visible = is_object($this->service->company->custom_fields) && property_exists($this->service->company->custom_fields, $field[1]) && !empty($this->service->company->custom_fields->{$field[1]});

                $elements[1]['elements'][] = ['element' => 'div', 'elements' => [
                    ['element' => 'p', 'content' => $variable . '_label', 'properties' => ['hidden' => !$visible, 'data-ref' => 'totals_table-' . substr($variable, 1) . '-label']],
                    ['element' => 'p', 'content' => $variable, 'properties' => ['hidden' => !$visible, 'data-ref' => 'totals_table-' . substr($variable, 1)]],
                ]];
            } else {
                $elements[1]['elements'][] = ['element' => 'div', 'elements' => [
                    ['element' => 'p', 'content' => $variable . '_label', 'properties' => ['data-ref' => 'totals_table-' . substr($variable, 1) . '-label']],
                    ['element' => 'p', 'content' => $variable, 'properties' => ['data-ref' => 'totals_table-' . substr($variable, 1)]],
                ], 'properties' => ['class' => 'totals_table-' . substr($variable, 1)]];
            }
        }

        $elements[1]['elements'][] = ['element' => 'div', 'elements' => [
            ['element' => 'p', 'content' => '',],
            ['element' => 'p', 'content' => ''],
        ]];


        return $elements;
    }

    /**
     * Generates the product and task tables
     *
     * @return self
     *
     */
    public function getProductAndTaskTables(): self
    {
        $this->mergeSections([
            'product-table' => [
                'id' => 'product-table',
                'elements' => $this->productTable(),
            ],
            'task-table' => [
                'id' => 'task-table',
                'elements' => $this->taskTable(),
            ],
        ]);

        return $this;
    }

    /**
     * Generates the client details
     *
     * @return self
     *
     */
    public function getClientDetails(): self
    {
        $this->mergeSections([
            'client-details' => [
                'id' => 'client-details',
                'elements' => $this->clientDetails(),
            ],
            'shipping-details' => [
                'id' => 'shipping-details',
                'elements' => $this->shippingDetails(),
            ],
        ]);

        return $this;
    }

    /**
     * Generates the product table
     *
     * @return array
     */
    public function productTable(): array
    {
        $product_items = collect($this->service->config->entity->line_items)->filter(function ($item) {
            return $item->type_id == 1 || $item->type_id == 6 || $item->type_id == 5 || $item->type_id == 4;
        });

        if (count($product_items) == 0) {
            return [];
        }

        return [
            ['element' => 'thead', 'properties' => [], 'elements' => [
            ['element' => 'tr', 'elements' => $this->buildTableHeader('product')],
            ]],
            ['element' => 'tbody', 'elements' => $this->buildTableBody('$product')],
        ];
    }

    /**
     * Generates the task table
     *
     * @return array
     */
    public function taskTable(): array
    {

        if ($this->service->config->entity instanceof \App\Models\PurchaseOrder) {
            return [];
        }

        $task_items = collect($this->service->config->entity->line_items)->filter(function ($item) {
            return $item->type_id == 2;
        });

        if (count($task_items) == 0) {
            return [];
        }

        return [
            ['element' => 'thead', 'properties' => [], 'elements' => [
                ['element' => 'tr', 'elements' => $this->buildTableHeader('task')],
            ]],
            ['element' => 'tbody', 'elements' => $this->buildTableBody('$task')],
        ];
    }


    /**
     * Generates the statement details
     *
     * @return array
     *
     */
    public function statementDetails(): array
    {

        $s_date = $this->translateDate($this->service->options['start_date'], $this->service->config->date_format, $this->service->config->locale) . " - " . $this->translateDate($this->service->options['end_date'], $this->service->config->date_format, $this->service->config->locale);

        return [
            ['element' => 'tr', 'properties' => ['data-ref' => 'statement-label'], 'elements' => [
                ['element' => 'th', 'properties' => [], 'content' => ""],
                ['element' => 'th', 'properties' => [], 'content' => '<h2>'.ctrans('texts.statement').'</h2>'],
            ]],
            ['element' => 'tr', 'properties' => [], 'elements' => [
                ['element' => 'th', 'properties' => [], 'content' => ctrans('texts.statement_date')],
                ['element' => 'th', 'properties' => [], 'content' => $s_date],
            ]],
            ['element' => 'tr', 'properties' => [], 'elements' => [
                ['element' => 'th', 'properties' => [], 'content' => '$balance_due_label'],
                ['element' => 'th', 'properties' => [], 'content' => $this->service->config->formatMoney($this->service->options['invoices']->sum('balance'))],
            ]],
        ];
    }

    /**
     * Generates the invoice details
     *
     * @return array
     *
     */
    public function invoiceDetails(): array
    {
        $variables = $this->service->config->pdf_variables['invoice_details'];

        return $this->genericDetailsBuilder($variables);
    }

    /**
     * Generates the quote details
     *
     * @return array
     *
     */
    public function quoteDetails(): array
    {
        $variables = $this->service->config->pdf_variables['quote_details'];

        if ($this->service->config->entity->partial > 0) {
            $variables[] = '$quote.balance_due';
        }

        return $this->genericDetailsBuilder($variables);
    }


    /**
     * Generates the credit note details
     *
     * @return array
     *
     */
    public function creditDetails(): array
    {
        $variables = $this->service->config->pdf_variables['credit_details'];

        return $this->genericDetailsBuilder($variables);
    }

    /**
     * Generates the purchase order details
     *
     * @return array
     */
    public function purchaseOrderDetails(): array
    {
        $variables = $this->service->config->pdf_variables['purchase_order_details'];

        return $this->genericDetailsBuilder($variables);
    }

    /**
     * Generates the deliveyr note details
     *
     * @return array
     *
     */
    public function deliveryNoteDetails(): array
    {
        $variables = $this->service->config->pdf_variables['invoice_details'];

        $variables = array_filter($variables, function ($m) {
            return !in_array($m, ['$invoice.balance_due', '$invoice.total']);
        });

        return $this->genericDetailsBuilder($variables);
    }

    /**
     * Generates the custom values for the
     * entity.
     *
     * @param  array $variables
     * @return array
     */
    public function genericDetailsBuilder(array $variables): array
    {
        $elements = [];

        foreach ($variables as $variable) {
            $_variable = explode('.', $variable)[1];
            $_customs = ['custom1', 'custom2', 'custom3', 'custom4'];

            $var = str_replace("custom", "custom_value", $_variable);

            if (in_array($_variable, $_customs) && !empty($this->service->config->entity->{$var})) {
                $elements[] = ['element' => 'tr', 'elements' => [
                    ['element' => 'th', 'content' => $variable . '_label', 'properties' => ['data-ref' => 'entity_details-' . substr($variable, 1) . '_label']],
                    ['element' => 'th', 'content' => $variable, 'properties' => ['data-ref' => 'entity_details-' . substr($variable, 1)]],
                ]];
            } else {
                $elements[] = ['element' => 'tr', 'properties' => ['hidden' => $this->entityVariableCheck($variable)], 'elements' => [
                    ['element' => 'th', 'content' => $variable . '_label', 'properties' => ['data-ref' => 'entity_details-' . substr($variable, 1) . '_label']],
                    ['element' => 'th', 'content' => $variable, 'properties' => ['data-ref' => 'entity_details-' . substr($variable, 1)]],
                ]];
            }
        }

        return $elements;
    }


    /**
     * Generates the client delivery details array
     *
     * We also override some variables here to ensure they are
     * appropriate for the delivery note.
     *
     * @return array
     *
     */
    public function clientDeliveryDetails(): array
    {
        $elements = [];

        if (!$this->service->config->client) {
            return $elements;
        }

        $this->service->html_variables['values']['$show_paid_stamp'] = 'none';
        $this->service->html_variables['values']['$show_shipping_address_block'] = 'none';
        $this->service->html_variables['values']['$show_shipping_address'] = 'none';
        $this->service->html_variables['values']['$show_shipping_address_visibility'] = 'hidden';
        $this->service->html_variables['labels']['$entity_issued_to_label'] = '';
        $this->service->html_variables['labels']['$entity_number_label'] = ctrans('texts.delivery_note');
        $this->service->html_variables['values']['$entity'] = ctrans('texts.delivery_note');
        $this->service->html_variables['labels']['$entity_label'] = ctrans('texts.delivery_note');
        $this->service->html_variables['labels']['$invoice.number_label'] = ctrans('texts.delivery_note');
        $this->service->html_variables['labels']['$payment_due_label'] = '';
        $this->service->html_variables['values']['$payment_due'] = '';
        $this->service->html_variables['labels']['$amount_due_label'] = '';
        $this->service->html_variables['values']['$balance_due'] = '';
        $this->service->html_variables['values']['$amount_due'] = '';
        $this->service->html_variables['labels']['$amount_due_label'] = '';

        $elements = [
                ['element' => 'div', 'content' => $this->service->config->client->name, 'show_empty' => false, 'properties' => ['data-ref' => 'delivery_note-client.name']],
                ['element' => 'div', 'content' => $this->service->html_variables['values']['$client.shipping_address1'], 'show_empty' => false, 'properties' => ['data-ref' => 'delivery_note-client.shipping_address1']],
                ['element' => 'div', 'content' => $this->service->html_variables['values']['$client.shipping_address2'], 'show_empty' => false, 'properties' => ['data-ref' => 'delivery_note-client.shipping_address2']],
                ['element' => 'div', 'content' => "{$this->service->html_variables['values']['$client.shipping_city']} {$this->service->html_variables['values']['$client.shipping_state']} {$this->service->html_variables['values']['$client.shipping_postal_code']}", 'show_empty' => false, 'properties' => ['data-ref' => 'delivery_note-client.city_state_postal']],
                ['element' => 'div', 'content' => optional($this->service->html_variables['values']['$client.shipping_country'])->name, 'show_empty' => false],
            ];


        if (!is_null($this->service->config->contact)) {
            $elements[] = ['element' => 'div', 'content' => $this->service->config->contact->email, 'show_empty' => false, 'properties' => ['data-ref' => 'delivery_note-contact.email']];
        }

        return $elements;
    }

    /**
     * Generates the client details section
     *
     * @return array
     */
    public function clientDetails(): array
    {
        $elements = [];

        if (!$this->service->config->client) {
            return $elements;
        }

        $variables = $this->service->config->pdf_variables['client_details'];

        // 2025-03-03 - Prevent duplicating contact/client name
        if (strlen($this->service->config->client->name ?? '') == 0 && in_array('$client.name', $variables) && in_array('$contact.full_name', $variables)) {
            $variables = array_diff($variables, ['$contact.full_name']);
        }

        foreach ($variables as $variable) {
            $elements[] = ['element' => 'div', 'content' => $variable, 'show_empty' => false, 'properties' => ['data-ref' => 'client_details-' . substr($variable, 1)]];
        }

        return $elements;
    }

    /**
     * Generates the shipping details section
     *
     * @return array
     */
    public function shippingDetails(): array
    {
        $elements = [];

        if (!$this->service->config->client || $this->service->document_type == PdfService::DELIVERY_NOTE) {
            return $elements;
        }

        $elements = [
            ['element' => 'div', 'content' => ctrans('texts.shipping_address'), 'properties' => ['data-ref' => 'shipping_address-label', 'style' => 'font-weight: bold; text-transform: uppercase']],
            ['element' => 'div', 'content' => $this->service->html_variables['values']['$client.shipping_location_name'], 'show_empty' => false, 'properties' => ['data-ref' => 'shipping_address-client.shipping_location_name']],
            ['element' => 'div', 'content' => $this->service->html_variables['values']['$client.shipping_address1'], 'show_empty' => false, 'properties' => ['data-ref' => 'shipping_address-client.shipping_address1']],
            ['element' => 'div', 'content' => $this->service->html_variables['values']['$client.shipping_address2'], 'show_empty' => false, 'properties' => ['data-ref' => 'shipping_address-client.shipping_address2']],
            ['element' => 'div', 'content' => "{$this->service->html_variables['values']['$client.shipping_city']} {$this->service->html_variables['values']['$client.shipping_state']} {$this->service->html_variables['values']['$client.shipping_postal_code']}", 'properties' => ['data-ref' => 'shipping_address-client.city_state_postal']],
            ['element' => 'div', 'content' => $this->service->html_variables['values']['$client.shipping_country'], 'show_empty' => false, 'properties' => ['data-ref' => 'shipping_address-client.shipping_country']],
        ];

        return $elements;
    }


    /**
     * Generates the delivery note table
     *
     * @return array
     */
    public function deliveryNoteTable(): array
    {

        $thead = [
            ['element' => 'th', 'content' => '$item_label', 'properties' => ['data-ref' => 'delivery_note-item_label']],
            ['element' => 'th', 'content' => '$description_label', 'properties' => ['data-ref' => 'delivery_note-description_label']],
            ['element' => 'th', 'content' => '$product.quantity_label', 'properties' => ['data-ref' => 'delivery_note-product.quantity_label']],
        ];

        $items = $this->transformLineItems($this->service->config->entity->line_items, $this->service->document_type);

        $this->processNewLines($items);

        $product_customs = [false, false, false, false];

        foreach ($items as $row) {
            for ($i = 0; $i < count($product_customs); $i++) {
                if (!empty($row['delivery_note.delivery_note' . ($i + 1)])) {
                    $product_customs[$i] = true;
                }
            }
        }

        for ($i = 0; $i < count($product_customs); $i++) {
            if ($product_customs[$i]) {
                array_push($thead, ['element' => 'th', 'content' => '$product.product' . ($i + 1) . '_label', 'properties' => ['data-ref' => 'delivery_note-product.product' . ($i + 1) . '_label']]);
            }
        }

        $first_visible = array_key_first($thead);
        $last_visible = array_key_last($thead);

        // Add class to first visible cell
        if (!isset($thead[$first_visible]['properties']['class'])) { //@phpstan-ignore-line
            $thead[$first_visible]['properties']['class'] = 'left-radius';
        } else {
            $thead[$first_visible]['properties']['class'] .= ' left-radius';
        }

        // Add class to last visible cell
        if (!isset($thead[$last_visible]['properties']['class'])) {
            $thead[$last_visible]['properties']['class'] = 'right-radius';
        } else {
            $thead[$last_visible]['properties']['class'] .= ' right-radius';
        }

        return [
            ['element' => 'thead', 'elements' => $thead],
            ['element' => 'tbody', 'elements' => $this->buildTableBody(PdfService::DELIVERY_NOTE)],
        ];
    }

    /**
     * Passes an array of items by reference
     * and performs a nl2br
     *
     * @param  array $items
     * @return void
     *
     */
    public function processNewLines(array &$items): void
    {
        foreach ($items as $key => $item) {
            foreach ($item as $variable => $value) {
                $item[$variable] = str_replace("\n", '<br/>', $value);
            }

            $items[$key] = $item;
        }
    }

    /**
     * Generates an arary of the company details
     *
     * @return array
     *
     */
    public function companyDetails(): array
    {
        $variables = $this->service->config->pdf_variables['company_details'];

        $elements = [];

        foreach ($variables as $variable) {
            $elements[] = ['element' => 'p', 'content' => $variable, 'show_empty' => false, 'properties' => ['data-ref' => 'company_details-' . substr($variable, 1)]];
        }

        return $elements;
    }

    /**
     *
     * Generates an array of the company address
     *
     * @return array
     *
     */
    public function companyAddress(): array
    {
        $variables = $this->service->config->pdf_variables['company_address'];

        $elements = [];

        foreach ($variables as $variable) {
            $elements[] = ['element' => 'div', 'content' => $variable, 'show_empty' => false, 'properties' => ['data-ref' => 'company_address-' . substr($variable, 1)]];
        }

        return $elements;
    }

    /**
     *
     * Generates an array of vendor details
     *
     * @return array
     *
     */
    public function vendorDetails(): array
    {
        $elements = [];

        $variables = $this->service->config->pdf_variables['vendor_details'];

        foreach ($variables as $variable) {
            $elements[] = ['element' => 'div', 'content' => $variable, 'show_empty' => false, 'properties' => ['data-ref' => 'vendor_details-' . substr($variable, 1)]];
        }

        return $elements;
    }


    ////////////////////////////////////////
    // Dom Traversal
    ///////////////////////////////////////

    public function updateElementProperties(): self
    {
        foreach ($this->sections as $element) {
            if (isset($element['tag'])) {
                $node = $this->document->getElementsByTagName($element['tag'])->item(0);
            } elseif (! is_null($this->document->getElementById($element['id']))) {
                $node = $this->document->getElementById($element['id']);
            } else {
                continue;
            }

            if (isset($element['properties'])) {
                foreach ($element['properties'] as $property => $value) {
                    $this->updateElementProperty($node, $property, $value);
                }
            }

            if (isset($element['elements'])) {
                $this->createElementContent($node, $element['elements']);
            }
        }

        return $this;
    }

    public function updateElementProperty($element, string $attribute, ?string $value)
    {
        // We have exception for "hidden" property.
        // hidden="true" or hidden="false" will both hide the element,
        // that's why we have to create an exception here for this rule.

        if ($attribute == 'hidden' && ($value == false || $value == 'false')) {
            return $element;
        }

        $element->setAttribute($attribute, $value);

        if ($element->getAttribute($attribute) === $value) {
            return $element;
        }

        return $element;
    }

    /**
     * isMarkdown
     *
     * Checks if the given content is most likely markdown
     *
     * @param  string $content
     * @return bool
     */
    private function isMarkdown(string $content): bool
    {
        $content = str_ireplace(['<br>', '<br/>', '<br />'], "\n", $content);

        $markdownPatterns = [
            '/^\s*#{1,6}\s/m',  // Headers
            '/^\s*[-+*]\s/m',   // Lists
            '/\[.*?\]\(.*?\)/', // Links
            '/!\[.*?\]\(.*?\)/', // Images
            '/\*\*.*?\*\*/',   // Bold
            '/\*.*?\*/',       // Italic
            '/__.*?__/',       // Bold
            // '/_.*?_/',         // Italic
            '/(?<!\w)_([^_]+)_(?!\w)/',
            '/`.*?`/',         // Inline code
            '/^\s*>/m',        // Blockquotes
            '/^\s*```/m',      // Code blocks
        ];

        // Check if any pattern matches the text
        foreach ($markdownPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;

    }

    public function createElementContent($element, $children): self
    {
        foreach ($children as $child) {
            if (isset($child['is_empty']) && $child['is_empty'] === true) {
                continue;
            }

            $contains_html = false;

            $child['content'] = $child['content'] ?? '';

            if ($this->service->company->markdown_enabled && $this->isMarkdown($child['content'])) {

                $child['content'] = str_ireplace(['<br>', '<br/>', '<br />'], "\r", $child['content']); //19-05-2025 - pivotting back to this, and allowing html_input for commonmark.
                $child['content'] = $this->commonmark->convert($child['content']); //@phpstan-ignore-line
            }

            $contains_html = str_contains($child['content'], '<') && str_contains($child['content'], '>');

            if ($contains_html) {
                // Encode any HTML elements now so that DOMDocument doesn't throw any errors,
                // Later we can decode specific elements.

                $_child = $this->document->createElement($child['element'], '');
                $_child->setAttribute('data-state', 'encoded-html');
                $_child->nodeValue = htmlspecialchars($child['content']);


            } else {
                $_child = $this->document->createElement($child['element'], htmlspecialchars($child['content']));
            }

            $element->appendChild($_child);

            if (isset($child['properties'])) {
                foreach ($child['properties'] as $property => $value) {
                    $this->updateElementProperty($_child, $property, $value);
                }
            }

            if (isset($child['elements'])) {
                $this->createElementContent($_child, $child['elements']);
            }
        }

        return $this;
    }

    /**
     * updateVariables
     *
     * @return self
     */
    public function updateVariables(): self
    {

        $html = strtr($this->getCompiledHTML(), $this->service->html_variables['labels']);
        $html = strtr($html, $this->service->html_variables['values']);

        @$this->document->loadHTML($this->convertHtmlToEntities($html));
        // @$this->document->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));

        //new block
        // $html = htmlspecialchars_decode($html, ENT_QUOTES | ENT_HTML5);
        // $html = str_ireplace(['<br>','<?xml encoding="UTF-8">'], ['<br/>',''], $html);
        // @$this->document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        //continues
        $this->document->saveHTML();

        return $this;
    }

    // public function updateVariable(string $element, string $variable, string $value)
    // {
    //     $element = $this->document->getElementById($element);

    //     $original = $element->nodeValue;

    //     $element->nodeValue = '';

    //     $replaced = strtr($original, [$variable => $value]);

    //     $element->appendChild(
    //         $this->document->createTextNode($replaced)
    //     );

    //     return $element;
    // }

    public function getEmptyElements(): self
    {
        foreach ($this->sections as $key => $element) {
            if (isset($element['elements'])) {
                $this->sections[$key] = $this->getEmptyChildren($element);
            }
        }

        return $this;
    }

    public function getEmptyChildren(array $element): array
    {
        foreach ($element['elements'] as $key => &$child) {
            if ($this->isChildEmpty($child)) {
                $child['is_empty'] = true;
            }

            if (isset($child['elements'])) {
                $child = $this->getEmptyChildren($child);
            }
        }

        return $element;
    }

    private function isChildEmpty(array $child): bool
    {
        if (!isset($child['content']) && isset($child['show_empty']) && $child['show_empty'] === false) {
            return true;
        }

        if (isset($child['content']) && isset($child['show_empty']) && $child['show_empty'] === false) {
            $value = strtr($child['content'], $this->service->html_variables['values']);
            return empty($value) || $value === '&nbsp;' || $value === ' ';
        }

        return false;
    }
}
