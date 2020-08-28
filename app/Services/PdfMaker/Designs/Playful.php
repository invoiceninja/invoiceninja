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

namespace App\Services\PdfMaker\Designs;

use App\Services\PdfMaker\Designs\Utilities\BaseDesign;
use App\Services\PdfMaker\Designs\Utilities\DesignHelpers;
use App\Utils\Traits\MakesInvoiceValues;

class Playful extends BaseDesign
{
    use MakesInvoiceValues, DesignHelpers;

    /** Global list of table elements, @var array */
    public $elements;

    /** @var App\Models\Client */
    public $client;

    /** @var App\Models\Invoice || @var App\Models\Quote */
    public $entity;

    /** Global state of the design, @var array */
    public $context;

    /** Type of entity => product||task */
    public $type;

    public function html()
    {
        return file_get_contents(
            base_path('resources/views/pdf-designs/playful.html')
        );
    }

    public function elements(array $context, string $type = 'product'): array
    {
        $this->context = $context;

        $this->type = $type;

        $this->setup();

        return [
            'entity-details' => [
                'id' => 'entity-details',
                'elements' => $this->entityDetails(),
            ],
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

    public function entityDetails(): array
    {
        $variables = $this->context['pdf_variables']['invoice_details'];

        if ($this->entity instanceof \App\Models\Quote) {
            $variables = $this->context['pdf_variables']['quote_details'];
        }

        $elements = [];

        foreach ($variables as $variable) {
            $elements[] = ['element' => 'tr', 'properties' => ['hidden' => $this->entityVariableCheck($variable)],  'content' => '', 'elements' => [
                ['element' => 'th', 'content' => $variable . '_label', 'properties' => ['class' => 'text-left pr-4 font-normal']],
                ['element' => 'th', 'content' => $variable, 'properties' => ['class' => 'text-left pr-4 font-medium']],
            ]];
        }

        return $elements;
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

    public function productTable(): array
    {
        return  [
            ['element' => 'thead', 'content' => '', 'properties' => ['class' => 'text-left bg-teal-600'], 'elements' => $this->buildTableHeader()],
            ['element' => 'tbody', 'content' => '', 'elements' => $this->buildTableBody()],
            ['element' => 'tfoot', 'content' => '', 'elements' => $this->tableFooter()],
        ];
    }

    public function buildTableHeader(): array
    {
        $this->processTaxColumns();

        $elements = [];

        foreach ($this->context['pdf_variables']['product_columns'] as $column) {
            $elements[] = ['element' => 'th', 'content' => $column . '_label', 'properties' => ['class' => 'font-semibold text-white px-4 py-3']];
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
            $element = ['element' => 'tr', 'properties' => ['class' => 'border-b-2 border-teal-600'], 'content' => '', 'elements' => []];

            foreach ($this->context['pdf_variables']['product_columns'] as $key => $cell) {
                $element['elements'][] = ['element' => 'td', 'content' => $row[$cell], 'properties' => ['class' => 'px-4 py-4']];
            }

            $elements[] = $element;
        }

        return $elements;
    }

    public function tableFooter()
    {
        $variables = $this->entity->company->settings->pdf_variables->total_columns;

        $elements = [
            ['element' => 'tr', 'content' => '', 'elements' => [
                ['element' => 'td', 'content' => '$entity.public_notes', 'properties' => ['class' => 'border-l-4 border-white px-4 text-right', 'colspan' => '100%']],
            ]],
        ];

        foreach ($variables as $variable) {
            $elements[] = ['element' => 'tr', 'properties' => ['hidden' => $this->toggleHiddenProperty($this->entity->calc()->getTotalDiscount())], 'content' => '', 'elements' => [
                ['element' => 'td', 'content' => $variable . '_label', 'properties' => ['class' => 'border-l-4 border-white px-4 text-right', 'colspan' => $this->calculateColspan(1)]],
                ['element' => 'td', 'content' => $variable, 'properties' => ['class' => 'px-4 py-2 text-right']],
            ]];
        }

        return $elements;
    }
}
