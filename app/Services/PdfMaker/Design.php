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
            'product-table-footer' => [
                'id' => 'product-table-footer',
                'elements' => $this->tableFooter(),
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
            $elements[] = ['element' => 'p', 'content' => $variable];
        }

        return $elements;
    }

    public function companyAddress(): array
    {
        $variables = $this->context['pdf_variables']['company_address'];

        $elements = [];

        foreach ($variables as $variable) {
            $elements[] = ['element' => 'p', 'content' => $variable];
        }

        return $elements;
    }

    public function clientDetails(): array
    {
        $variables = $this->context['pdf_variables']['client_details'];

        $elements = [];

        foreach ($variables as $variable) {
            $elements[] = ['element' => 'p', 'content' => $variable];
        }

        return $elements;
    }

    public function entityDetails(): array
    {
        $variables = $this->context['pdf_variables']['invoice_details'];

        if ($this->entity instanceof \App\Models\Quote) {
            $variables = $this->context['pdf_variables']['quote_details'];
        }

        $elements = [];

        foreach ($variables as $variable) {
            $_variable = explode('.', $variable)[1];

            if ($_variable == 'custom1' || $_variable == 'custom2') {
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

    public function productTable(): array
    {
        return  [
            ['element' => 'thead', 'elements' => $this->buildTableHeader()],
            ['element' => 'tbody', 'elements' => $this->buildTableBody()],
        ];
    }

    public function buildTableHeader(): array
    {
        $this->processTaxColumns();

        $elements = [];

        foreach ($this->context['pdf_variables']["{$this->type}_columns"] as $column) {
            $elements[] = ['element' => 'th', 'content' => $column . '_label'];
        }

        return $elements;
    }

    public function buildTableBody(): array
    {
        $elements = [];

        $items = $this->transformLineItems($this->entity->line_items);

        if (count($items) == 0) {
            return [];
        }

        foreach ($items as $row) {
            $element = ['element' => 'tr', 'elements' => []];

            if (
                isset($this->context['products']) &&
                !empty($this->context['products']) &&
                !is_null($this->context['products'])
            ) {
                $document = new DOMDocument();
                $document->loadHTML($this->context['products'], LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

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
                foreach ($this->context['pdf_variables']["{$this->type}_columns"] as $key => $cell) {
                    $element['elements'][] = ['element' => 'td', 'content' => $row[$cell]];
                }
            }

            $elements[] = $element;
        }

        return $elements;
    }

    public function tableFooter()
    {
        $variables = $this->context['pdf_variables']['total_columns'];

        $elements = [
            ['element' => 'div', 'elements' => [
                ['element' => 'span', 'content' => '$entity.public_notes', 'properties' => ['data-element' => 'product-table-public-notes-label']],
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
