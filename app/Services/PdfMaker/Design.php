<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Services\PdfMaker;

use App\Models\Quote;
use App\Services\PdfMaker\Designs\Utilities\BaseDesign;
use App\Services\PdfMaker\Designs\Utilities\DesignHelpers;
use App\Utils\Number;
use App\Utils\Traits\MakesInvoiceValues;
use DOMDocument;
use Illuminate\Support\Str;

class Design extends BaseDesign
{
    use MakesInvoiceValues, DesignHelpers;

    /** @var App\Models\Invoice || @var App\Models\Quote */
    public $entity;

    /** @var App\Models\Client */
    public $client;

    /** Global state of the design, @var array */
    public $context;

    /** Type of entity => product||task */
    public $type;

    /** Design string */
    public $design;

    /** Construct options */
    public $options;

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

        $path = isset($this->options['custom_path'])
            ? $this->options['custom_path']
            : config('ninja.designs.base_path');

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
            'entity-details' => [
                'id' => 'entity-details',
                'elements' => $this->entityDetails(),
            ],
            'product-table' => [
                'id' => 'product-table',
                'elements' => $this->productTable(),
            ],
            'task-table' => [
                'id' => 'task-table',
                'elements' => $this->taskTable(),
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
        ];
    }

    public function companyDetails()
    {
        $variables = $this->context['pdf_variables']['company_details'];

        $elements = [];

        foreach ($variables as $variable) {
            $elements[] = ['element' => 'p', 'content' => $variable, 'show_empty' => false];
        }

        return $elements;
    }

    public function companyAddress(): array
    {
        $variables = $this->context['pdf_variables']['company_address'];

        $elements = [];

        foreach ($variables as $variable) {
            $elements[] = ['element' => 'p', 'content' => $variable, 'show_empty' => false];
        }

        return $elements;
    }

    public function clientDetails(): array
    {
        $variables = $this->context['pdf_variables']['client_details'];

        $elements = [];

        foreach ($variables as $variable) {
            $elements[] = ['element' => 'p', 'content' => $variable, 'show_empty' => false];
        }

        return $elements;
    }

    public function entityDetails(): array
    {
        $variables = $this->context['pdf_variables']['invoice_details'];

        if ($this->entity instanceof Quote) {
            $variables = $this->context['pdf_variables']['quote_details'];
        }

        $elements = [];

        foreach ($variables as $variable) {
            $_variable = explode('.', $variable)[1];
            $_customs = ['custom1', 'custom2', 'custom3', 'custom4'];

            if (in_array($_variable, $_customs)) {
                $elements[] = ['element' => 'tr', 'elements' => [
                    ['element' => 'th', 'content' => $variable . '_label'],
                    ['element' => 'th', 'content' => $variable],
                ]];
            } else {
                $elements[] = ['element' => 'tr', 'properties' => ['hidden' => $this->entityVariableCheck($variable)], 'elements' => [
                    ['element' => 'th', 'content' => $variable . '_label'],
                    ['element' => 'th', 'content' => $variable],
                ]];
            }
        }

        return $elements;
    }

    /**
     * Parent method for building products table.
     * 
     * @return array 
     */
    public function productTable(): array
    {
        $product_items = collect($this->entity->line_items)->filter(function ($item) {
            return $item->type_id == 1;
        });

        if ($product_items->count() == 0) {
            return [];
        }

        return  [
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
            return $item->type_id = 2;
        });

        if ($task_items->count() == 0) {
            return [];
        }

        return [
            ['element' => 'thead', 'elements' => $this->buildTableHeader('task')],
            ['element' => 'tbody', 'elements' => $this->buildTableBody('$task')],
        ];
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

        $elements = [];

        foreach ($this->context['pdf_variables']["{$type}_columns"] as $column) {
            $elements[] = ['element' => 'th', 'content' => $column . '_label'];
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

        if (count($items) == 0) {
            return [];
        }

        //info($this->context);

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
                        $element['elements'][] = ['element' => 'td', 'content' => $row['$task.cost']];
                    } else if ($cell == '$task.hours') {
                        $element['elements'][] = ['element' => 'td', 'content' => $row['$task.quantity']];
                    } else {
                        $element['elements'][] = ['element' => 'td', 'content' => $row[$cell]];
                    }
                }
            }

            $elements[] = $element;
        }

        return $elements;
    }

    public function tableTotals(): array
    {
        $variables = $this->context['pdf_variables']['total_columns'];

        $elements = [
            ['element' => 'div', 'elements' => [
                ['element' => 'span', 'content' => '$entity.public_notes', 'properties' => ['data-element' => 'total-table-public-notes-label']],
            ]],
        ];

        foreach (['discount', 'custom_surcharge1', 'custom_surcharge2', 'custom_surcharge3', 'custom_surcharge4'] as $property) {
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

                foreach ($taxes as $tax) {
                    $elements[] = ['element' => 'div', 'elements' => [
                        ['element' => 'span', 'content' => 'This is placeholder for the 3rd fraction of element.', 'properties' => ['style' => 'opacity: 0%']], // Placeholder for fraction of element (3fr)
                        ['element' => 'span', 'content', 'content' => $tax['name']],
                        ['element' => 'span', 'content', 'content' => Number::formatMoney($tax['total'], $this->context['client'])],
                    ]];
                }
            } elseif ($variable == '$line_taxes') {
                $taxes = $this->entity->calc()->getTaxMap();

                if (!$taxes) {
                    continue;
                }

                foreach ($taxes as $tax) {
                    $elements[] = ['element' => 'div', 'elements' => [
                        ['element' => 'span', 'content' => 'This is placeholder for the 3rd fraction of element.', 'properties' => ['style' => 'opacity: 0%']], // Placeholder for fraction of element (3fr)
                        ['element' => 'span', 'content', 'content' => $tax['name']],
                        ['element' => 'span', 'content', 'content' => Number::formatMoney($tax['total'], $this->context['client'])],
                    ]];
                }
            } else {
                $elements[] = ['element' => 'div', 'elements' => [
                    ['element' => 'span', 'content' => 'This is placeholder for the 3rd fraction of element.', 'properties' => ['style' => 'opacity: 0%']], // Placeholder for fraction of element (3fr)
                    ['element' => 'span', 'content' => $variable . '_label'],
                    ['element' => 'span', 'content' => $variable],
                ]];
            }
        }

        return $elements;
    }
}
