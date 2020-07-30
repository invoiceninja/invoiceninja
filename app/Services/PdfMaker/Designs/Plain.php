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

use App\Utils\Traits\MakesInvoiceValues;

class Plain
{
    use MakesInvoiceValues;

    public $elements;

    public $client;

    public $invoice;

    public $context;

    public function html(): ?string
    {
        return file_get_contents(
            base_path('resources/views/pdf-designs/plain.html')
        );
    }

    public function setup(): void
    {
        $this->client = $this->context['client'];

        $this->invoice = $this->context['invoice'];

        /** @todo: Wrap the elements with correct checks & exceptions. */
    }

    public function elements(array $context): array
    {
        $this->context = $context;
        $this->setup();

        return [
            'company-address' => [
                'id' => 'company-address',
                'elements' => [
                    ['element' => 'p', 'content' => '$company.address1'],
                ],
            ],
            $this->productTable(),
        ];
    }

    public function productTable()
    {
        return  [
            'id' => 'product-table',
            'elements' => [
                ['element' => 'thead', 'content' => '', 'properties' => ['class' => 'text-left bg-gray-200'], 'elements' => $this->buildTableHeader()],
                ['element' => 'tbody', 'content' => '', 'elements' => $this->buildTableBody()],
                ['element' => 'tfoot', 'content' => '', 'elements' => [
                    ['element' => 'tr', 'content' => '', 'elements' => [
                        ['element' => 'td', 'content' => '$entity.public_notes', 'properties' => ['class' => 'border-l-4 border-white px-4 py-4', 'colspan' => '2']],
                        ['element' => 'td', 'content' => '$subtotal_label', 'properties' => ['class' => 'px-4 py-4 text-right', 'colspan' => '2']],
                        ['element' => 'td', 'content' => '$subtotal', 'properties' => ['class' => 'px-4 py-2 text-right']],
                    ]],

                    ['element' => 'tr', 'content' => '', 'elements' => [
                        ['element' => 'td', 'content' => '$discount_label', 'properties' => ['class' => 'border-l-4 border-white px-4 text-right', 'colspan' => '4']],
                        ['element' => 'td', 'content' => '$discount', 'properties' => ['class' => 'px-4 py-2 text-right']],
                    ]],
                    ['element' => 'tr', 'content' => '', 'properties' => ['class' => 'mt-8 px-4 py-2 bg-gray-300'], 'elements' => [
                        ['element' => 'td', 'content' => '$balance_due_label', 'properties' => ['class' => 'border-l-4 border-white px-4 text-right', 'colspan' => '4']],
                        ['element' => 'td', 'content' => '$balance_due', 'properties' => ['class' => 'px-4 py-2 text-right']],
                    ]],
                ]],
            ],
        ];
    }

    public function buildTableHeader(): array
    {
        $elements = [];

        foreach ($this->context['product-table-columns'] as $column) {
            $elements[] = ['element' => 'th', 'content' => $column . '_label', 'properties' => ['class' => 'px-4 py-2']];
        }

        return $elements;
    }

    public function buildTableBody(): array
    {
        $elements = [];

        $items = $this->transformLineItems($this->invoice->line_items);

        if (count($items) == 0) {
            return [];
        }

        foreach ($items as $row) {
            $element = ['element' => 'tr', 'content' => '', 'elements' => []];

            foreach ($row as $child) {
                $element['elements'][] = ['element' => 'td', 'content' => $child, 'properties' => ['class' => 'border-t-2 border-b border-gray-200 px-4 py-4']];
            }

            $elements[] = $element;
        }

        return $elements;
    }
}
