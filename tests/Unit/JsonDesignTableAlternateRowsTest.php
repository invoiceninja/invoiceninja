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

/**
 * Alternating row background parity with the frontend renderTableBlock spec
 * (rowBg / alternateRows / alternateRowBg). Backgrounds are painted on the
 * <tr> AND on each <td> for PDF engines that ignore <tr> backgrounds.
 */
class JsonDesignTableAlternateRowsTest extends TestCase
{
    public function testAlternatesRowBgAndAlternateRowBgWhenEnabled(): void
    {
        $sections = $this->adapt([
            'columns' => $this->columns(),
            'rowBg' => '#FFFFFF',
            'alternateRows' => true,
            'alternateRowBg' => '#F9FAFB',
        ], 4);

        // Even indices (0, 2) → rowBg; odd (1, 3) → alternateRowBg.
        $this->assertSame('background: #FFFFFF;', $this->trStyle($sections, 0));
        $this->assertSame('background: #F9FAFB;', $this->trStyle($sections, 1));
        $this->assertSame('background: #FFFFFF;', $this->trStyle($sections, 2));
        $this->assertSame('background: #F9FAFB;', $this->trStyle($sections, 3));

        // Each <td> must repeat the same colour for PDF engines that drop
        // <tr> backgrounds.
        $this->assertStringContainsString('background-color: #FFFFFF;', $this->bodyCellStyle($sections, 0));
        $this->assertStringContainsString('background-color: #F9FAFB;', $this->bodyCellStyle($sections, 1));
        $this->assertStringContainsString('background-color: #FFFFFF;', $this->bodyCellStyle($sections, 2));
        $this->assertStringContainsString('background-color: #F9FAFB;', $this->bodyCellStyle($sections, 3));
    }

    public function testAlternateRowsFalseUsesRowBgForEveryRow(): void
    {
        $sections = $this->adapt([
            'columns' => $this->columns(),
            'rowBg' => '#FFFFFF',
            'alternateRows' => false,
            'alternateRowBg' => '#F9FAFB',
        ], 3);

        for ($i = 0; $i < 3; $i++) {
            $this->assertSame('background: #FFFFFF;', $this->trStyle($sections, $i));
            $this->assertStringContainsString('background-color: #FFFFFF;', $this->bodyCellStyle($sections, $i));
        }
    }

    public function testAlternateRowsMissingUsesRowBgForEveryRow(): void
    {
        $sections = $this->adapt([
            'columns' => $this->columns(),
            'rowBg' => '#EEEEEE',
            // no alternateRows, no alternateRowBg
        ], 3);

        for ($i = 0; $i < 3; $i++) {
            $this->assertSame('background: #EEEEEE;', $this->trStyle($sections, $i));
            $this->assertStringContainsString('background-color: #EEEEEE;', $this->bodyCellStyle($sections, $i));
        }
    }

    public function testAlternateRowsTrueWithoutAlternateRowBgLeavesOddRowsBare(): void
    {
        // Per FE: ternary returns undefined for odd rows → no rule emitted.
        $sections = $this->adapt([
            'columns' => $this->columns(),
            'rowBg' => '#FFFFFF',
            'alternateRows' => true,
            // no alternateRowBg
        ], 2);

        $this->assertSame('background: #FFFFFF;', $this->trStyle($sections, 0));
        $this->assertNull($this->trStyle($sections, 1));

        $this->assertStringContainsString('background-color: #FFFFFF;', $this->bodyCellStyle($sections, 0));
        $this->assertStringNotContainsString('background-color', $this->bodyCellStyle($sections, 1));
    }

    public function testAllRowBackgroundKeysMissingEmitsNoBackground(): void
    {
        $sections = $this->adapt([
            'columns' => $this->columns(),
            // no rowBg, no alternateRows, no alternateRowBg
        ], 2);

        for ($i = 0; $i < 2; $i++) {
            $this->assertNull($this->trStyle($sections, $i));
            $this->assertStringNotContainsString('background-color', $this->bodyCellStyle($sections, $i));
            $this->assertStringNotContainsString('background:', $this->bodyCellStyle($sections, $i));
        }
    }

    public function testAlternateRowsStringTrueIsTreatedAsOff(): void
    {
        // Strict `=== true` gate (frontend parity): "true" is a string,
        // therefore not strict-equal to true → no striping.
        $sections = $this->adapt([
            'columns' => $this->columns(),
            'rowBg' => '#FFFFFF',
            'alternateRows' => 'true',
            'alternateRowBg' => '#F9FAFB',
        ], 2);

        $this->assertSame('background: #FFFFFF;', $this->trStyle($sections, 0));
        $this->assertSame('background: #FFFFFF;', $this->trStyle($sections, 1));
    }

    public function testCellStyleNoLongerCarriesLegacyShorthandRowBg(): void
    {
        // Regression guard: the old `background: rowBg;` shorthand on the
        // cell is gone. We only emit `background-color:` per-row now.
        $sections = $this->adapt([
            'columns' => $this->columns(),
            'rowBg' => '#FFFFFF',
        ], 1);

        $cellStyle = $this->bodyCellStyle($sections, 0);
        $this->assertStringNotContainsString('background:', $cellStyle);
        $this->assertStringContainsString('background-color: #FFFFFF;', $cellStyle);
    }

    public function testHeaderBgIsRepeatedOnEachThForPdfReliability(): void
    {
        $sections = $this->adapt([
            'columns' => $this->columns(),
            'headerBg' => '#F3F4F6',
        ], 1);

        $headerStyle = $this->headerCellStyle($sections);
        $this->assertStringContainsString('background-color: #F3F4F6;', $headerStyle);
    }

    public function testHeaderBgOmittedDoesNotEmitBackgroundColorOnTh(): void
    {
        $sections = $this->adapt([
            'columns' => $this->columns(),
            // no headerBg
        ], 1);

        $headerStyle = $this->headerCellStyle($sections);
        $this->assertStringNotContainsString('background-color', $headerStyle);
    }

    private function adapt(array $properties, int $rowCount): array
    {
        $service = $this->pdfService($rowCount);

        return (new JsonToSectionsAdapter([
            'pageSettings' => [],
            'blocks' => [[
                'id' => 'items',
                'type' => 'table',
                'gridPosition' => ['x' => 0, 'y' => 0, 'w' => 12, 'h' => 4],
                'properties' => $properties,
            ]],
        ], $service))->toSections();
    }

    private function columns(): array
    {
        return [
            ['id' => 'product_key',  'header' => 'Item',  'field' => 'item.product_key'],
            ['id' => 'product_key2', 'header' => 'Item2', 'field' => 'item.product_key'],
        ];
    }

    private function pdfService(int $rowCount): PdfService
    {
        $service = (new \ReflectionClass(PdfService::class))->newInstanceWithoutConstructor();

        $company = new Company();
        $company->company_key = 'test-company';
        $service->company = $company;

        $invoice = new Invoice();
        $items = [];
        for ($i = 0; $i < $rowCount; $i++) {
            $items[] = (object) ['type_id' => '1', 'product_key' => 'A-' . $i];
        }
        $invoice->line_items = $items;

        $config = new PdfConfiguration($service);
        $config->entity = $invoice;
        $config->settings = (object) ['hide_empty_columns_on_pdf' => false];

        $service->config = $config;
        $service->html_variables = ['values' => [], 'labels' => []];

        return $service;
    }

    private function trStyle(array $sections, int $rowIndex): ?string
    {
        $rows = $sections['items']['elements'][0]['elements'][1]['elements'];
        return $rows[$rowIndex]['properties']['style'] ?? null;
    }

    private function bodyCellStyle(array $sections, int $rowIndex): string
    {
        $rows = $sections['items']['elements'][0]['elements'][1]['elements'];
        return $rows[$rowIndex]['elements'][0]['properties']['style'];
    }

    private function headerCellStyle(array $sections): string
    {
        $headerCells = $sections['items']['elements'][0]['elements'][0]['elements'][0]['elements'];
        return $headerCells[0]['properties']['style'];
    }
}
