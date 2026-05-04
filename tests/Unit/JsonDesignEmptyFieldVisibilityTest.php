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
use Tests\TestCase;
use App\Services\Pdf\PdfBuilder;
use App\Services\Pdf\PdfService;
use App\Services\Pdf\JsonToSectionsAdapter;

class JsonDesignEmptyFieldVisibilityTest extends TestCase
{
    public function testInfoFieldsHideByVariableValueNotPrefixText(): void
    {
        $sections = $this->sections([[
            'id' => 'client-info',
            'type' => 'client-info',
            'gridPosition' => ['x' => 0, 'y' => 0, 'w' => 6, 'h' => 3],
            'properties' => [
                'fieldConfigs' => [
                    ['prefix' => 'Phone: ', 'variable' => '$client.phone'],
                    ['prefix' => 'Name: ', 'variable' => '$client.name'],
                    ['prefix' => 'Missing: ', 'variable' => '$client.missing'],
                ],
            ],
        ]], [
            '$client.phone' => '',
            '$client.name' => 'Acme Corp',
        ]);

        $fields = $sections['client-info']['elements'];

        $this->assertTrue($fields[0]['is_empty'] ?? false);
        $this->assertArrayNotHasKey('is_empty', $fields[1]);
        $this->assertTrue($fields[2]['is_empty'] ?? false);
    }

    public function testInvoiceDetailRowsHideTheirLabelsWhenValueIsEmpty(): void
    {
        $sections = $this->sections([[
            'id' => 'details',
            'type' => 'invoice-details',
            'gridPosition' => ['x' => 0, 'y' => 0, 'w' => 6, 'h' => 3],
            'properties' => [
                'items' => [
                    ['label' => 'PO Number', 'variable' => '$invoice.po_number'],
                    ['label' => 'Invoice #', 'variable' => '$invoice.number'],
                ],
            ],
        ]], [
            '$invoice.po_number' => '',
            '$invoice.number' => 'INV-001',
        ]);

        $rows = $sections['details']['elements'][0]['elements'];

        $this->assertTrue($rows[0]['is_empty'] ?? false);
        $this->assertArrayNotHasKey('is_empty', $rows[1]);
    }

    public function testTotalRowsHideWhenEmptyButKeepZeroValues(): void
    {
        $sections = $this->sections([[
            'id' => 'totals',
            'type' => 'total',
            'gridPosition' => ['x' => 0, 'y' => 0, 'w' => 6, 'h' => 3],
            'properties' => [
                'items' => [
                    ['label' => 'Discount', 'field' => '$invoice.discount'],
                    ['label' => 'Total', 'field' => '$invoice.total', 'isTotal' => true],
                ],
            ],
        ]], [
            '$invoice.discount' => '',
            '$invoice.total' => '0',
        ]);

        $rows = $sections['totals']['elements'][0]['elements'][0]['elements'];

        $this->assertTrue($rows[0]['is_empty'] ?? false);
        $this->assertArrayNotHasKey('is_empty', $rows[1]);
    }

    private function sections(array $blocks, array $values): array
    {
        $service = $this->pdfService($values);
        $sections = (new JsonToSectionsAdapter([
            'pageSettings' => [],
            'blocks' => $blocks,
        ], $service))->toSections();

        $builder = new PdfBuilder($service);
        $builder->setSections($sections)->getEmptyElements();

        return $builder->sections;
    }

    private function pdfService(array $values): PdfService
    {
        $service = (new \ReflectionClass(PdfService::class))->newInstanceWithoutConstructor();

        $company = new Company();
        $company->company_key = 'test-company';
        $service->company = $company;
        $service->html_variables = [
            'labels' => [],
            'values' => $values,
        ];

        return $service;
    }
}
