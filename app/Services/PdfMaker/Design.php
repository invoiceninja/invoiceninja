<?php

/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Services\PdfMaker;

use Illuminate\Support\Str;
use App\Utils\Traits\MakesInvoiceValues;
use App\Services\PdfMaker\Designs\Utilities\BaseDesign;
use App\Services\PdfMaker\Designs\Utilities\DesignHelpers;

class Design extends BaseDesign
{
    use MakesInvoiceValues, DesignHelpers;

    /** @var App\Models\Invoice || @var App\Models\Quote */
    public $entity;

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

    public function __construct(string $design = null, array $options = [])
    {
        Str::endsWith('.html', $design) ? $this->design = $design : $this->design = "{$design}.html";

        $this->options = $options;
    }

    public function html(): ?string
    {
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
            $elements[] = ['element' => 'tr', 'properties' => ['hidden' => $this->entityVariableCheck($variable)], 'elements' => [
                ['element' => 'th', 'content' => $variable . '_label'],
                ['element' => 'th', 'content' => $variable],
            ]];
        }

        return $elements;
    }

    public function productTable(): array
    {
        return  [
            ['element' => 'thead', 'elements' => $this->buildTableHeader()],
            ['element' => 'tbody', 'elements' => $this->buildTableBody()],
            ['element' => 'tfoot', 'elements' => $this->tableFooter()],
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

            foreach ($this->context['pdf_variables']["{$this->type}_columns"] as $key => $cell) {
                $element['elements'][] = ['element' => 'td', 'content' => $row[$cell]];
            }

            $elements[] = $element;
        }

        return $elements;
    }

    public function tableFooter()
    {
        $variables = $this->context['pdf_variables']['total_columns'];

        $elements = [
            ['element' => 'tr', 'elements' => [
                ['element' => 'td', 'content' => '$entity.public_notes', 'properties' => ['colspan' => '100%']],
            ]],
        ];

        foreach ($variables as $variable) {
            if ($variable == '$total_taxes' || $variable == '$line_taxes') {
                continue;
            }

            $elements[] = ['element' => 'tr', 'elements' => [
                ['element' => 'td', 'properties' => ['colspan' => $this->calculateColspan(2)]],
                ['element' => 'td', 'content' => $variable . '_label'],
                ['element' => 'td', 'content' => $variable],
            ]];
        }

        return $elements;
    }
}
