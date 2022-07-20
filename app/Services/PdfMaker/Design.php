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

namespace App\Services\PdfMaker;

use App\Models\Credit;
use App\Models\GatewayType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Quote;
use App\Services\PdfMaker\Designs\Utilities\BaseDesign;
use App\Services\PdfMaker\Designs\Utilities\DesignHelpers;
use App\Utils\Number;
use App\Utils\Traits\MakesDates;
use App\Utils\Traits\MakesInvoiceValues;
use DOMDocument;
use Illuminate\Support\Str;

class Design extends BaseDesign
{
    use MakesInvoiceValues, DesignHelpers, MakesDates;

    /** @var App\Models\Invoice || @var App\Models\Quote */
    public $entity;

    /** @var App\Models\Client */
    public $client;

    /** @var App\Models\Vendor */
    public $vendor;

    /** Global state of the design, @var array */
    public $context;

    /** Type of entity => product||task */
    public $type;

    /** Design string */
    public $design;

    /** Construct options */
    public $options;

    /** @var Invoice[] */
    public $invoices;

    /** @var Payment[] */
    public $payments;

    public $settings_object;

    public $company;

    public $client_or_vendor_entity;

    /** @var array */
    public $aging = [];

    const BOLD = 'bold';
    const BUSINESS = 'business';
    const CLEAN = 'clean';
    const CREATIVE = 'creative';
    const ELEGANT = 'elegant';
    const HIPSTER = 'hipster';
    const MODERN = 'modern';
    const PLAIN = 'plain';
    const PLAYFUL = 'playful';
    const CUSTOM = 'custom';

    const DELIVERY_NOTE = 'delivery_note';
    const STATEMENT = 'statement';
    const PURCHASE_ORDER = 'purchase_order';


    public function __construct(string $design = null, array $options = [])
    {
        Str::endsWith('.html', $design) ? $this->design = $design : $this->design = "{$design}.html";

        $this->options = $options;

    }

    public function html(): ?string
    {
        if ($this->design == 'custom.html') {
            return $this->composeFromPartials(
                $this->options['custom_partials']
            );
        }

        $path = $this->options['custom_path'] ?? config('ninja.designs.base_path');

        return file_get_contents(
            $path . $this->design
        );
    }

    public function elements(array $context, string $type = 'product'): array
    {
        $this->context = $context;

        $this->type = $type;

        $this->setup();

        return [
            'company-details' => [
                'id' => 'company-details',
                'elements' => $this->companyDetails(),
            ],
            'company-address' => [
                'id' => 'company-address',
                'elements' => $this->companyAddress(),
            ],
            'client-details' => [
                'id' => 'client-details',
                'elements' => $this->clientDetails(),
            ],
            'vendor-details' => [
                'id' => 'vendor-details',
                'elements' => $this->vendorDetails(),
            ],
            'entity-details' => [
                'id' => 'entity-details',
                'elements' => $this->entityDetails(),
            ],
            'delivery-note-table' => [
                'id' => 'delivery-note-table',
                'elements' => $this->deliveryNoteTable(),
            ],
            'product-table' => [
                'id' => 'product-table',
                'elements' => $this->productTable(),
            ],
            'task-table' => [
                'id' => 'task-table',
                'elements' => $this->taskTable(),
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
            'statement-payment-table-totals' => [
                'id' => 'statement-payment-table-totals',
                'elements' => $this->statementPaymentTableTotals(),
            ],
            'statement-aging-table' => [
                'id' => 'statement-aging-table',
                'elements' => $this->statementAgingTable(),
            ],
            'table-totals' => [
                'id' => 'table-totals',
                'elements' => $this->tableTotals(),
            ],
            'footer-elements' => [
                'id' => 'footer',
                'elements' => [
                    $this->sharedFooterElements(),
                ],
            ],
            // 'swiss-qr' => [
            //     'id' => 'swiss-qr',
            //     'elements' => $this->swissQrCodeElement(),
            // ]
        ];
    }


    public function swissQrCodeElement() :array
    {
        if($this->type == self::DELIVERY_NOTE)
            return [];

        $elements = [];

        if(strlen($this->company->getSetting('qr_iban')) > 5 && strlen($this->company->getSetting('besr_id')) > 1)
            $elements[] = ['element' => 'qr_code', 'content' => '$swiss_qr', 'show_empty' => false, 'properties' => ['data-ref' => 'swiss-qr-code']];

        return $elements; 
    }

    public function companyDetails(): array
    {
        $variables = $this->context['pdf_variables']['company_details'];

        $elements = [];

        foreach ($variables as $variable) {
            $elements[] = ['element' => 'p', 'content' => $variable, 'show_empty' => false, 'properties' => ['data-ref' => 'company_details-' . substr($variable, 1)]];
        }

        return $elements;
    }

    public function companyAddress(): array
    {
        $variables = $this->context['pdf_variables']['company_address'];

        $elements = [];

        foreach ($variables as $variable) {
            $elements[] = ['element' => 'p', 'content' => $variable, 'show_empty' => false, 'properties' => ['data-ref' => 'company_address-' . substr($variable, 1)]];
        }

        return $elements;
    }

    public function vendorDetails(): array
    {
        $elements = [];

        if(!$this->vendor)
            return $elements;

        $variables = $this->context['pdf_variables']['vendor_details'];

        foreach ($variables as $variable) {
            $elements[] = ['element' => 'p', 'content' => $variable, 'show_empty' => false, 'properties' => ['data-ref' => 'vendor_details-' . substr($variable, 1)]];
        }

        return $elements;
    }

    public function clientDetails(): array
    {
        $elements = [];

        if(!$this->client)
            return $elements;

        if ($this->type == self::DELIVERY_NOTE) {
            $elements = [
                ['element' => 'p', 'content' => ctrans('texts.delivery_note'), 'properties' => ['data-ref' => 'delivery_note-label', 'style' => 'font-weight: bold; text-transform: uppercase']],
                ['element' => 'p', 'content' => $this->client->name, 'show_empty' => false, 'properties' => ['data-ref' => 'delivery_note-client.name']],
                ['element' => 'p', 'content' => $this->client->shipping_address1, 'show_empty' => false, 'properties' => ['data-ref' => 'delivery_note-client.shipping_address1']],
                ['element' => 'p', 'content' => $this->client->shipping_address2, 'show_empty' => false, 'properties' => ['data-ref' => 'delivery_note-client.shipping_address2']],
                ['element' => 'p', 'show_empty' => false, 'elements' => [
                    ['element' => 'span', 'content' => "{$this->client->shipping_city} ", 'properties' => ['ref' => 'delivery_note-client.shipping_city']],
                    ['element' => 'span', 'content' => "{$this->client->shipping_state} ", 'properties' => ['ref' => 'delivery_note-client.shipping_state']],
                    ['element' => 'span', 'content' => "{$this->client->shipping_postal_code} ", 'properties' => ['ref' => 'delivery_note-client.shipping_postal_code']],
                ]],
                ['element' => 'p', 'content' => optional($this->client->shipping_country)->name, 'show_empty' => false],
            ];

            if (!is_null($this->context['contact'])) {
                $elements[] = ['element' => 'p', 'content' => $this->context['contact']->email, 'show_empty' => false, 'properties' => ['data-ref' => 'delivery_note-contact.email']];
            }

            return $elements;
        }

        $variables = $this->context['pdf_variables']['client_details'];

        foreach ($variables as $variable) {
            $elements[] = ['element' => 'p', 'content' => $variable, 'show_empty' => false, 'properties' => ['data-ref' => 'client_details-' . substr($variable, 1)]];
        }

        return $elements;
    }

    public function entityDetails(): array
    {


        if ($this->type === 'statement') {

            $s_date = $this->translateDate(now(), $this->client->date_format(), $this->client->locale());
            
            return [
                ['element' => 'tr', 'properties' => ['data-ref' => 'statement-label'], 'elements' => [
                    ['element' => 'th', 'properties' => [], 'content' => ""],
                    ['element' => 'th', 'properties' => [], 'content' => "<h2>".ctrans('texts.statement')."</h2>"],
                ]],
                ['element' => 'tr', 'properties' => [], 'elements' => [
                    ['element' => 'th', 'properties' => [], 'content' => ctrans('texts.statement_date')],
                    ['element' => 'th', 'properties' => [], 'content' => $s_date ?? ''],
                ]],
                ['element' => 'tr', 'properties' => [], 'elements' => [
                    ['element' => 'th', 'properties' => [], 'content' => '$balance_due_label'],
                    ['element' => 'th', 'properties' => [], 'content' => Number::formatMoney($this->invoices->sum('balance'), $this->client)],
                ]],
            ];
        }

        $variables = $this->context['pdf_variables']['invoice_details'];

        if ($this->entity instanceof Quote) {
            $variables = $this->context['pdf_variables']['quote_details'];
            
            if ($this->entity->partial > 0) {
                $variables[] = '$quote.balance_due';
            }
        }

        if ($this->entity instanceof Credit) {
            $variables = $this->context['pdf_variables']['credit_details'];
        }

        if($this->vendor){

            $variables = $this->context['pdf_variables']['purchase_order_details'];

        }

        $elements = [];

        // We don't want to show account balance or invoice total on PDF.. or any amount with currency.
        if ($this->type == self::DELIVERY_NOTE) {
            $variables = array_filter($variables, function ($m) {
                return !in_array($m, ['$invoice.balance_due', '$invoice.total']);
            });
        }

        foreach ($variables as $variable) {
            $_variable = explode('.', $variable)[1];
            $_customs = ['custom1', 'custom2', 'custom3', 'custom4'];

            /* 2/7/2022 don't show custom values if they are empty */
            $var = str_replace("custom", "custom_value", $_variable);

            if (in_array($_variable, $_customs) && !empty($this->entity->{$var})) {
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

    public function deliveryNoteTable(): array
    {
        if ($this->type !== self::DELIVERY_NOTE) {
            return [];
        }

        $thead = [
            ['element' => 'th', 'content' => '$item_label', 'properties' => ['data-ref' => 'delivery_note-item_label']],
            ['element' => 'th', 'content' => '$description_label', 'properties' => ['data-ref' => 'delivery_note-description_label']],
            ['element' => 'th', 'content' => '$product.quantity_label', 'properties' => ['data-ref' => 'delivery_note-product.quantity_label']],
        ];

        $items = $this->transformLineItems($this->entity->line_items, $this->type);

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

        return [
            ['element' => 'thead', 'elements' => $thead],
            ['element' => 'tbody', 'elements' => $this->buildTableBody(self::DELIVERY_NOTE)],
        ];
    }

    /**
     * Parent method for building products table.
     *
     * @return array
     */
    public function productTable(): array
    {
        $product_items = collect($this->entity->line_items)->filter(function ($item) {
            return $item->type_id == 1 || $item->type_id == 6;
        });

        if (count($product_items) == 0) {
            return [];
        }

        if ($this->type === self::DELIVERY_NOTE || $this->type === self::STATEMENT) {
            return [];
        }

        return [
            ['element' => 'thead', 'elements' => $this->buildTableHeader('product')],
            ['element' => 'tbody', 'elements' => $this->buildTableBody('$product')],
        ];
    }

    /**
     * Parent method for building tasks table.
     *
     * @return array
     */
    public function taskTable(): array
    {
        $task_items = collect($this->entity->line_items)->filter(function ($item) {
            return $item->type_id == 2;
        });

        if (count($task_items) == 0) {
            return [];
        }

        if ($this->type === self::DELIVERY_NOTE || $this->type === self::STATEMENT) {
            return [];
        }

        return [
            ['element' => 'thead', 'elements' => $this->buildTableHeader('task')],
            ['element' => 'tbody', 'elements' => $this->buildTableBody('$task')],
        ];
    }

    /**
     * Parent method for building invoices table within statement.
     *
     * @return array
     */
    public function statementInvoiceTable(): array
    {
        if (is_null($this->invoices) || $this->type !== self::STATEMENT) {
            return [];
        }

        $tbody = [];

        foreach ($this->invoices as $invoice) {
            $element = ['element' => 'tr', 'elements' => []];

            $element['elements'][] = ['element' => 'td', 'content' => $invoice->number];
            $element['elements'][] = ['element' => 'td', 'content' => $this->translateDate($invoice->date, $this->client->date_format(), $this->client->locale()) ?: ' '];
            $element['elements'][] = ['element' => 'td', 'content' => $this->translateDate($invoice->due_date, $this->client->date_format(), $this->client->locale()) ?: ' '];
            $element['elements'][] = ['element' => 'td', 'content' => Number::formatMoney($invoice->amount, $this->client) ?: ' '];
            $element['elements'][] = ['element' => 'td', 'content' => Number::formatMoney($invoice->balance, $this->client) ?: ' '];

            $tbody[] = $element;
        }

        return [
            ['element' => 'thead', 'elements' => $this->buildTableHeader('statement_invoice')],
            ['element' => 'tbody', 'elements' => $tbody],
        ];
    }

    public function statementInvoiceTableTotals(): array
    {
        if ($this->type !== self::STATEMENT) {
            return [];
        }

        $outstanding = $this->invoices->sum('balance');

        return [
            ['element' => 'p', 'content' => '$outstanding_label: ' . Number::formatMoney($outstanding, $this->client)],
        ];
    }

    /**
     * Parent method for building payments table within statement.
     *
     * @return array
     */
    public function statementPaymentTable(): array
    {
        if (is_null($this->payments) && $this->type !== self::STATEMENT) {
            return [];
        }

        if (\array_key_exists('show_payments_table', $this->options) && $this->options['show_payments_table'] === false) {
            return [];
        }

        $tbody = [];

        //24-03-2022 show payments per invoice
        foreach ($this->invoices as $invoice) {
            foreach ($invoice->payments as $payment) {

                if($payment->is_deleted)
                    continue;

                $element = ['element' => 'tr', 'elements' => []];

                $element['elements'][] = ['element' => 'td', 'content' => $invoice->number];
                $element['elements'][] = ['element' => 'td', 'content' => $this->translateDate($payment->date, $this->client->date_format(), $this->client->locale()) ?: '&nbsp;'];
                $element['elements'][] = ['element' => 'td', 'content' => $payment->type ? $payment->type->name : ctrans('texts.manual_entry')];
                $element['elements'][] = ['element' => 'td', 'content' => Number::formatMoney($payment->pivot->amount, $this->client) ?: '&nbsp;'];

                $tbody[] = $element;
                
            }
        }

        return [
            ['element' => 'thead', 'elements' => $this->buildTableHeader('statement_payment')],
            ['element' => 'tbody', 'elements' => $tbody],
        ];
    }

    public function statementPaymentTableTotals(): array
    {
        if (is_null($this->payments) || !$this->payments->first() || $this->type !== self::STATEMENT) {
            return [];
        }

        if (\array_key_exists('show_payments_table', $this->options) && $this->options['show_payments_table'] === false) {
            return [];
        }
        
        $payment = $this->payments->first();

        return [
            ['element' => 'p', 'content' => \sprintf('%s: %s', ctrans('texts.amount_paid'), Number::formatMoney($this->payments->sum('amount'), $this->client))],
        ];
    }

    public function statementAgingTable(): array
    {
        if ($this->type !== self::STATEMENT) {
            return [];
        }

        if (\array_key_exists('show_aging_table', $this->options) && $this->options['show_aging_table'] === false) {
            return [];
        }

        $elements = [
            ['element' => 'thead', 'elements' => []],
            ['element' => 'tbody', 'elements' => [
                ['element' => 'tr', 'elements' => []],
            ]],
        ];

        foreach ($this->aging as $column => $value) {
            $elements[0]['elements'][] = ['element' => 'th', 'content' => $column];
            $elements[1]['elements'][] = ['element' => 'td', 'content' => $value];
        }

        return $elements;
    }

    /**
     * Generate the structure of table headers. (<thead/>)
     *
     * @param string $type "product" or "task"
     * @return array
     */
    public function buildTableHeader(string $type): array
    {
        $this->processTaxColumns($type);
        // $this->processCustomColumns($type);

        $elements = [];

        // Some of column can be aliased. This is simple workaround for these.
        $aliases = [
            '$product.product_key' => '$product.item',
            '$task.product_key' => '$task.service',
            '$task.rate' => '$task.cost',
        ];

        foreach ($this->context['pdf_variables']["{$type}_columns"] as $column) {
            if (array_key_exists($column, $aliases)) {
                $elements[] = ['element' => 'th', 'content' => $aliases[$column] . '_label', 'properties' => ['data-ref' => "{$type}_table-" . substr($aliases[$column], 1) . '-th', 'hidden' => $this->settings_object->getSetting('hide_empty_columns_on_pdf')]];
            } elseif ($column == '$product.discount' && !$this->company->enable_product_discount) {
                $elements[] = ['element' => 'th', 'content' => $column . '_label', 'properties' => ['data-ref' => "{$type}_table-" . substr($column, 1) . '-th', 'style' => 'display: none;']];
            } elseif ($column == '$product.quantity' && !$this->company->enable_product_quantity) {
                $elements[] = ['element' => 'th', 'content' => $column . '_label', 'properties' => ['data-ref' => "{$type}_table-" . substr($column, 1) . '-th', 'style' => 'display: none;']];
            } elseif ($column == '$product.tax_rate1') {
                $elements[] = ['element' => 'th', 'content' => $column . '_label', 'properties' => ['data-ref' => "{$type}_table-product.tax1-th", 'hidden' => $this->settings_object->getSetting('hide_empty_columns_on_pdf')]];
            } elseif ($column == '$product.tax_rate2') {
                $elements[] = ['element' => 'th', 'content' => $column . '_label', 'properties' => ['data-ref' => "{$type}_table-product.tax2-th", 'hidden' => $this->settings_object->getSetting('hide_empty_columns_on_pdf')]];
            } elseif ($column == '$product.tax_rate3') {
                $elements[] = ['element' => 'th', 'content' => $column . '_label', 'properties' => ['data-ref' => "{$type}_table-product.tax3-th", 'hidden' => $this->settings_object->getSetting('hide_empty_columns_on_pdf')]];
            } else {
                $elements[] = ['element' => 'th', 'content' => $column . '_label', 'properties' => ['data-ref' => "{$type}_table-" . substr($column, 1) . '-th', 'hidden' => $this->settings_object->getSetting('hide_empty_columns_on_pdf')]];
            }
        }

        return $elements;
    }

    /**
     * Generate the structure of table body. (<tbody/>)
     *
     * @param string $type "$product" or "$task"
     * @return array
     */
    public function buildTableBody(string $type): array
    {
        $elements = [];

        $items = $this->transformLineItems($this->entity->line_items, $type);

        $this->processNewLines($items);

        if (count($items) == 0) {
            return [];
        }

        if ($type == self::DELIVERY_NOTE) {
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

                $element['elements'][] = ['element' => 'td', 'content' => $row['delivery_note.product_key'], 'properties' => ['data-ref' => 'delivery_note_table.product_key-td']];
                $element['elements'][] = ['element' => 'td', 'content' => $row['delivery_note.notes'], 'properties' => ['data-ref' => 'delivery_note_table.notes-td']];
                $element['elements'][] = ['element' => 'td', 'content' => $row['delivery_note.quantity'], 'properties' => ['data-ref' => 'delivery_note_table.quantity-td']];

                for ($i = 0; $i < count($product_customs); $i++) {
                    if ($product_customs[$i]) {
                        $element['elements'][] = ['element' => 'td', 'content' => $row['delivery_note.delivery_note' . ($i + 1)], 'properties' => ['data-ref' => 'delivery_note_table.product' . ($i + 1) . '-td']];
                    }
                }

                $elements[] = $element;
            }

            return $elements;
        }

        foreach ($items as $row) {
            $element = ['element' => 'tr', 'elements' => []];

            if (
                array_key_exists($type, $this->context) &&
                !empty($this->context[$type]) &&
                !is_null($this->context[$type])
            ) {
                $document = new DOMDocument();
                $document->loadHTML($this->context[$type], LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

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
                $_type = Str::startsWith($type, '$') ? ltrim($type, '$') : $type;

                foreach ($this->context['pdf_variables']["{$_type}_columns"] as $key => $cell) {
                    // We want to keep aliases like these:
                    // $task.cost => $task.rate
                    // $task.quantity => $task.hours

                    if ($cell == '$task.rate') {
                        $element['elements'][] = ['element' => 'td', 'content' => $row['$task.cost'], 'properties' => ['data-ref' => 'task_table-task.cost-td']];
                    } elseif ($cell == '$product.discount' && !$this->company->enable_product_discount) {
                        $element['elements'][] = ['element' => 'td', 'content' => $row['$product.discount'], 'properties' => ['data-ref' => 'product_table-product.discount-td', 'style' => 'display: none;']];
                    } elseif ($cell == '$product.quantity' && !$this->company->enable_product_quantity) {
                        $element['elements'][] = ['element' => 'td', 'content' => $row['$product.quantity'], 'properties' => ['data-ref' => 'product_table-product.quantity-td', 'style' => 'display: none;']];
                    } elseif ($cell == '$task.hours') {
                        $element['elements'][] = ['element' => 'td', 'content' => $row['$task.quantity'], 'properties' => ['data-ref' => 'task_table-task.hours-td']];
                    } elseif ($cell == '$product.tax_rate1') {
                        $element['elements'][] = ['element' => 'td', 'content' => $row[$cell], 'properties' => ['data-ref' => 'product_table-product.tax1-td']];
                    } elseif ($cell == '$product.tax_rate2') {
                        $element['elements'][] = ['element' => 'td', 'content' => $row[$cell], 'properties' => ['data-ref' => 'product_table-product.tax2-td']];
                    } elseif ($cell == '$product.tax_rate3') {
                        $element['elements'][] = ['element' => 'td', 'content' => $row[$cell], 'properties' => ['data-ref' => 'product_table-product.tax3-td']];
                    } else if ($cell == '$product.unit_cost' || $cell == '$task.rate') {
                        $element['elements'][] = ['element' => 'td', 'content' => $row[$cell], 'properties' => ['style' => 'white-space: nowrap;', 'data-ref' => "{$_type}_table-" . substr($cell, 1) . '-td']];
                    } else {
                        $element['elements'][] = ['element' => 'td', 'content' => $row[$cell], 'properties' => ['data-ref' => "{$_type}_table-" . substr($cell, 1) . '-td']];
                    }
                }
            }

            $elements[] = $element;
        }

        return $elements;
    }

    public function tableTotals(): array
    {
        if ($this->type === self::STATEMENT) {
            return [
                ['element' => 'div', 'properties' => ['style' => 'display: flex; flex-direction: column;'], 'elements' => [
                    ['element' => 'div', 'properties' => ['style' => 'margin-top: 1.5rem; display: block; align-items: flex-start; page-break-inside: avoid; visible !important;'], 'elements' => [
                        ['element' => 'img', 'properties' => ['src' => '$invoiceninja.whitelabel', 'style' => 'height: 2.5rem;', 'hidden' => $this->entity->user->account->isPaid() ? 'true' : 'false', 'id' => 'invoiceninja-whitelabel-logo']],
                    ]],
                ]],
            ];
        }

        $_variables = array_key_exists('variables', $this->context)
            ? $this->context['variables']
            : ['values' => ['$entity.public_notes' => $this->entity->public_notes, '$entity.terms' => $this->entity->terms, '$entity_footer' => $this->entity->footer], 'labels' => []];

        $variables = $this->context['pdf_variables']['total_columns'];


        $elements = [
            ['element' => 'div', 'properties' => ['style' => 'display: flex; flex-direction: column;'], 'elements' => [
                ['element' => 'p', 'content' => strtr(str_replace(["labels","values"], ["",""], $_variables['values']['$entity.public_notes']), $_variables), 'properties' => ['data-ref' => 'total_table-public_notes', 'style' => 'text-align: left;']],
                ['element' => 'p', 'content' => '', 'properties' => ['style' => 'text-align: left; display: flex; flex-direction: column; page-break-inside: auto;'], 'elements' => [
                    ['element' => 'span', 'content' => '$entity.terms_label: ', 'properties' => ['hidden' => $this->entityVariableCheck('$entity.terms'), 'data-ref' => 'total_table-terms-label', 'style' => 'font-weight: bold; text-align: left; margin-top: 1rem;']],
                    ['element' => 'span', 'content' => strtr(str_replace("labels", "", $_variables['values']['$entity.terms']), $_variables['labels']), 'properties' => ['data-ref' => 'total_table-terms', 'style' => 'text-align: left;']],
                ]],
                ['element' => 'img', 'properties' => ['style' => 'max-width: 50%; height: auto;', 'src' => '$contact.signature', 'id' => 'contact-signature']],
                ['element' => 'div', 'properties' => ['style' => 'margin-top: 1.5rem; display: flex; align-items: flex-start; page-break-inside: auto;'], 'elements' => [
                    ['element' => 'img', 'properties' => ['src' => '$invoiceninja.whitelabel', 'style' => 'height: 2.5rem;', 'hidden' => $this->entity->user->account->isPaid() ? 'true' : 'false', 'id' => 'invoiceninja-whitelabel-logo']],
                ]],
            ]],
            ['element' => 'div', 'properties' => ['class' => 'totals-table-right-side', 'dir' => '$dir'], 'elements' => []],
        ];


        if ($this->type == self::DELIVERY_NOTE) {
            return $elements;
        }

        if ($this->entity instanceof Quote) {
            // We don't want to show Balanace due on the quotes.
            if (in_array('$outstanding', $variables)) {
                $variables = \array_diff($variables, ['$outstanding']);
            }

            if ($this->entity->partial > 0) {
                $variables[] = '$partial_due';
            }
        }

        if ($this->entity instanceof Credit) {
            // We don't want to show Balanace due on the quotes.
            if (in_array('$paid_to_date', $variables)) {
                $variables = \array_diff($variables, ['$paid_to_date']);
            }

        }

        foreach (['discount'] as $property) {
            $variable = sprintf('%s%s', '$', $property);

            if (
                !is_null($this->entity->{$property}) &&
                !empty($this->entity->{$property}) &&
                $this->entity->{$property} != 0
            ) {
                continue;
            }

            $variables = array_filter($variables, function ($m) use ($variable) {
                return $m != $variable;
            });
        }

        foreach ($variables as $variable) {
            if ($variable == '$total_taxes') {
                $taxes = $this->entity->calc()->getTotalTaxMap();

                if (!$taxes) {
                    continue;
                }

                foreach ($taxes as $i => $tax) {
                    $elements[1]['elements'][] = ['element' => 'div', 'elements' => [
                        ['element' => 'span', 'content', 'content' => $tax['name'], 'properties' => ['data-ref' => 'totals-table-total_tax_' . $i . '-label']],
                        ['element' => 'span', 'content', 'content' => Number::formatMoney($tax['total'], $this->entity instanceof \App\Models\PurchaseOrder ? $this->company : $this->client_or_vendor_entity), 'properties' => ['data-ref' => 'totals-table-total_tax_' . $i]],
                    ]];
                }
            } elseif ($variable == '$line_taxes') {
                $taxes = $this->entity->calc()->getTaxMap();

                if (!$taxes) {
                    continue;
                }

                foreach ($taxes as $i => $tax) {
                    $elements[1]['elements'][] = ['element' => 'div', 'elements' => [
                        ['element' => 'span', 'content', 'content' => $tax['name'], 'properties' => ['data-ref' => 'totals-table-line_tax_' . $i . '-label']],
                        ['element' => 'span', 'content', 'content' => Number::formatMoney($tax['total'], $this->entity instanceof \App\Models\PurchaseOrder ? $this->company : $this->client_or_vendor_entity), 'properties' => ['data-ref' => 'totals-table-line_tax_' . $i]],
                    ]];
                }
            } elseif (Str::startsWith($variable, '$custom_surcharge')) {
                $_variable = ltrim($variable, '$'); // $custom_surcharge1 -> custom_surcharge1

                $visible = intval($this->entity->{$_variable}) != 0;

                $elements[1]['elements'][] = ['element' => 'div', 'elements' => [
                    ['element' => 'span', 'content' => $variable . '_label', 'properties' => ['hidden' => !$visible, 'data-ref' => 'totals_table-' . substr($variable, 1) . '-label']],
                    ['element' => 'span', 'content' => $variable, 'properties' => ['hidden' => !$visible, 'data-ref' => 'totals_table-' . substr($variable, 1)]],
                ]];
            } elseif (Str::startsWith($variable, '$custom')) {
                $field = explode('_', $variable);
                $visible = is_object($this->company->custom_fields) && property_exists($this->company->custom_fields, $field[1]) && !empty($this->company->custom_fields->{$field[1]});

                $elements[1]['elements'][] = ['element' => 'div', 'elements' => [
                    ['element' => 'span', 'content' => $variable . '_label', 'properties' => ['hidden' => !$visible, 'data-ref' => 'totals_table-' . substr($variable, 1) . '-label']],
                    ['element' => 'span', 'content' => $variable, 'properties' => ['hidden' => !$visible, 'data-ref' => 'totals_table-' . substr($variable, 1)]],
                ]];
            } else {
                $elements[1]['elements'][] = ['element' => 'div', 'elements' => [
                    ['element' => 'span', 'content' => $variable . '_label', 'properties' => ['data-ref' => 'totals_table-' . substr($variable, 1) . '-label']],
                    ['element' => 'span', 'content' => $variable, 'properties' => ['data-ref' => 'totals_table-' . substr($variable, 1)]],
                ]];
            }
        }

        $elements[1]['elements'][] = ['element' => 'div', 'elements' => [
            ['element' => 'span', 'content' => '',],
            ['element' => 'span', 'content' => ''],
        ]];

        return $elements;
    }
}
