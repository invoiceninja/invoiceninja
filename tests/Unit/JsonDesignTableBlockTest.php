<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Unit;

use App\Models\Company;
use App\Models\Invoice;
use Tests\TestCase;
use App\Services\Pdf\PdfService;
use App\Services\Pdf\PdfConfiguration;
use App\Services\Pdf\JsonToSectionsAdapter;

class JsonDesignTableBlockTest extends TestCase
{
    public function testTableBlockKeepsEmptyColumnsWhenSettingDisabled(): void
    {
        $sections = $this->adaptTable(false);

        $headerCells = $this->headerCells($sections);
        $bodyCells = $this->bodyRows($sections)[0]['elements'];

        $this->assertCount(2, $headerCells);
        $this->assertCount(2, $bodyCells);
        $this->assertSame('A-1', $bodyCells[0]['content']);
        $this->assertSame('', $bodyCells[1]['content']);
    }

    public function testTableBlockHidesEmptyColumnsWhenSettingEnabled(): void
    {
        $sections = $this->adaptTable(true);

        $headerCells = $this->headerCells($sections);
        $bodyCells = $this->bodyRows($sections)[0]['elements'];

        $this->assertCount(1, $headerCells);
        $this->assertCount(1, $bodyCells);
        $this->assertSame('Item', $headerCells[0]['content']);
        $this->assertSame('A-1', $bodyCells[0]['content']);
        $this->assertSame('product_table-product_key-td', $bodyCells[0]['properties']['data-ref']);
    }

    private function adaptTable(bool $hideEmptyColumns): array
    {
        $service = $this->pdfService($hideEmptyColumns);

        return (new JsonToSectionsAdapter([
            'pageSettings' => [],
            'blocks' => [[
                'id' => 'items',
                'type' => 'table',
                'gridPosition' => ['x' => 0, 'y' => 0, 'w' => 12, 'h' => 4],
                'properties' => [
                    'columns' => [
                        ['id' => 'product_key', 'header' => 'Item', 'field' => 'item.product_key'],
                        ['id' => 'empty', 'header' => 'Empty', 'field' => 'item.empty_value'],
                    ],
                ],
            ]],
        ], $service))->toSections();
    }

    private function pdfService(bool $hideEmptyColumns): PdfService
    {
        $service = (new \ReflectionClass(PdfService::class))->newInstanceWithoutConstructor();

        $company = new Company();
        $company->company_key = 'test-company';
        $service->company = $company;

        $invoice = new Invoice();
        $invoice->line_items = [
            (object) ['type_id' => '1', 'product_key' => 'A-1'],
        ];

        $config = new PdfConfiguration($service);
        $config->entity = $invoice;
        $config->settings = (object) [
            'hide_empty_columns_on_pdf' => $hideEmptyColumns,
        ];

        $service->config = $config;
        $service->html_variables = ['values' => [], 'labels' => []];

        return $service;
    }

    private function headerCells(array $sections): array
    {
        return $sections['items']['elements'][0]['elements'][0]['elements'][0]['elements'];
    }

    private function bodyRows(array $sections): array
    {
        return $sections['items']['elements'][0]['elements'][1]['elements'];
    }
}
