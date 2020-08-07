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
use App\Services\PdfMaker\Designs\Utilities\BuildTableHeader;
use App\Utils\Traits\MakesInvoiceValues;

class Bold extends BaseDesign
{
    use MakesInvoiceValues, BuildTableHeader;

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
            base_path('resources/views/pdf-designs/bold.html')
        );
    }

    public function elements(array $context, string $type = 'product'): array
    {
        $this->context = $context;
        
        $this->type = $type;

        if ($type !== 'product' || $type !== 'task') {
            throw new \Exception("Type '{$type}' is not allowed. Allowed values are 'product' or 'task'.");
        }

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
        ];
    }

    public function companyDetails()
    {
        $variables = $this->entity->company->settings->pdf_variables->company_details;

        $elements = [];

        foreach ($variables as $variable) {
            $elements[] = ['element' => 'p', 'content' => $variable];
        }

        return $elements;
    }

    public function companyAddress(): array
    {
        $variables = $this->entity->company->settings->pdf_variables->company_address;

        $elements = [];

        foreach ($variables as $variable) {
            $elements[] = ['element' => 'p', 'content' => $variable];
        }

        return $elements;
    }

    public function clientDetails(): array
    {
        $variables = $this->entity->company->settings->pdf_variables->client_details;

        $elements = [];

        foreach ($variables as $variable) {
            $elements[] = ['element' => 'p', 'content' => $variable];
        }

        return $elements;
    }

    public function entityDetails(): array
    {
        $variables = $this->entity->company->settings->pdf_variables->invoice_details;

        $elements = [];

        foreach ($variables as $variable) {
            $elements[] = ['element' => 'tr', 'content' => '', 'elements' => [
                ['element' => 'th', 'content' => $variable . '_label', 'properties' => ['class' => 'text-left pr-4 font-normal']],
                ['element' => 'th', 'content' => $variable, 'properties' => ['class' => 'text-left pr-4 font-normal']],
            ]];
        }

        return $elements;
    }

    public function productTable(): array
    {
        return  [
            ['element' => 'thead', 'content' => '', 'properties' => ['class' => 'text-left rounded-t-lg'], 'elements' => $this->buildTableHeader()],
            ['element' => 'tbody', 'content' => '', 'elements' => $this->buildTableBody()],
            ['element' => 'tfoot', 'content' => '', 'elements' => $this->tableFooter()],
        ];
    }

    public function buildTableHeader(): array
    {
        $this->processTaxColumns();

        $elements = [];

        foreach ($this->context['product-table-columns'] as $column) {
            $elements[] = ['element' => 'th', 'content' => $column . '_label', 'properties' => ['class' => 'text-xl px-4 py-2']];
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
            $element = ['element' => 'tr', 'content' => '', 'elements' => []];

            foreach ($this->context['product-table-columns'] as $key => $cell) {
                $element['elements'][] = ['element' => 'td', 'content' => $row[$cell], 'properties' => ['class' => 'px-4 py-4']];
            }

            $elements[] = $element;
        }

        return $elements;
    }

    public function tableFooter()
    {
        return [
            ['element' => 'tr', 'content' => '', 'elements' => [
                ['element' => 'td', 'content' => '$entity.public_notes', 'properties' => ['class' => 'border-l-4 border-white px-4 text-right', 'colspan' => $this->calculateColspan(3)]],
                ['element' => 'td', 'content' => '$subtotal_label', 'properties' => ['class' => 'px-4 py-4 text-right', 'colspan' => '2']],
                ['element' => 'td', 'content' => '$subtotal', 'properties' => ['class' => 'px-4 py-2 text-right']],
            ]],
            ['element' => 'tr', 'content' => '', 'elements' => [
                ['element' => 'td', 'content' => '$discount_label', 'properties' => ['class' => 'border-l-4 border-white px-4 text-right', 'colspan' => $this->calculateColspan(1)]],
                ['element' => 'td', 'content' => '$discount', 'properties' => ['class' => 'px-4 py-2 text-right']],
            ]],
            ['element' => 'tr', 'content' => '', 'elements' => [
                ['element' => 'td', 'content' => '$partial_due_label', 'properties' => ['class' => 'border-l-4 border-white px-4 text-right', 'colspan' => $this->calculateColspan(1)]],
                ['element' => 'td', 'content' => '$partial_due', 'properties' => ['class' => 'px-4 py-2 text-right']],
            ]],
            ['element' => 'tr', 'content' => '', 'elements' => [
                ['element' => 'td', 'content' => '$outstanding_label', 'properties' => ['class' => 'border-l-4 border-white px-4 text-right', 'colspan' => $this->calculateColspan(1)]],
                ['element' => 'td', 'content' => '$outstanding', 'properties' => ['class' => 'px-4 py-2 text-right']],
            ]],
            ['element' => 'tr', 'content' => '', 'elements' => [
                ['element' => 'td', 'content' => '$invoice_total_label', 'properties' => ['class' => 'border-l-4 border-white px-4 text-right', 'colspan' => $this->calculateColspan(1)]],
                ['element' => 'td', 'content' => '$invoice_total', 'properties' => ['class' => 'px-4 py-2 text-right']],
            ]],
            ['element' => 'tr', 'content' => '', 'properties' => ['class' => 'mt-8 px-4 py-2'], 'elements' => [
                ['element' => 'td', 'content' => '$balance_due_label', 'properties' => ['class' => 'border-l-4 border-white px-4 text-right text-xl text-teal-600 font-semibold', 'colspan' => $this->calculateColspan(1)]],
                ['element' => 'td', 'content' => '$balance_due', 'properties' => ['class' => 'px-4 py-2 text-right']],
            ]],
        ];
    }
}
