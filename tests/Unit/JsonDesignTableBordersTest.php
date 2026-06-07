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
 * Border rendering parity with the frontend table builder spec
 * (resolveTableBorderProps + tableHeader/BodyCellBorderStyles).
 */
class JsonDesignTableBordersTest extends TestCase
{
    public function testShowBordersOmittedRendersNoBorders(): void
    {
        // showBorders defaults to false (strict-equal `true` gate) — every
        // header and body cell border MUST be `none`.
        $sections = $this->adapt(['columns' => $this->columns()]);

        $headerStyle = $this->headerCellStyle($sections);
        $firstRowStyle = $this->bodyCellStyle($sections, 0);

        $this->assertStringContainsString('border: none;', $headerStyle);
        $this->assertStringNotContainsString('border-top:', $headerStyle);
        $this->assertStringContainsString('border: none;', $firstRowStyle);
        $this->assertStringNotContainsString('border-top:', $firstRowStyle);
    }

    public function testShowBordersFalseRendersNoBorders(): void
    {
        $sections = $this->adapt([
            'columns' => $this->columns(),
            'showBorders' => false,
            'headerBorders' => ['color' => '#000', 'width' => 4],
            'rowBorders'    => ['color' => '#000', 'width' => 4],
        ]);

        $headerStyle = $this->headerCellStyle($sections);
        $firstRowStyle = $this->bodyCellStyle($sections, 0);

        $this->assertStringContainsString('border: none;', $headerStyle);
        $this->assertStringContainsString('border: none;', $firstRowStyle);
    }

    public function testDefaultRegionsWhenShowBordersTrueWithoutRegions(): void
    {
        // showBorders=true with no headerBorders/rowBorders → defaults
        // (1px solid #E5E7EB, all sides). Seam rule still applies on row 0.
        $sections = $this->adapt([
            'columns' => $this->columns(),
            'showBorders' => true,
        ]);

        $headerStyle = $this->headerCellStyle($sections);
        $firstRowStyle = $this->bodyCellStyle($sections, 0);
        $secondRowStyle = $this->bodyCellStyle($sections, 1);

        $this->assertStringContainsString('border-top: 1px solid #E5E7EB',    $headerStyle);
        $this->assertStringContainsString('border-bottom: 1px solid #E5E7EB', $headerStyle);

        // Header bottom is on by default → first row top MUST be suppressed
        // to avoid the doubled seam.
        $this->assertStringContainsString('border-top: none', $firstRowStyle);
        $this->assertStringContainsString('border-bottom: 1px solid #E5E7EB', $firstRowStyle);

        // Subsequent rows resume the row's own top stroke.
        $this->assertStringContainsString('border-top: 1px solid #E5E7EB', $secondRowStyle);
    }

    public function testSeamSuppressesFirstRowTopOnlyWhenHeaderBottomEnabled(): void
    {
        // Header bottom OFF → first row top should render normally.
        $sections = $this->adapt([
            'columns' => $this->columns(),
            'showBorders' => true,
            'headerBorders' => [
                'color' => '#111827',
                'width' => 2,
                'sides' => ['top' => true, 'right' => true, 'bottom' => false, 'left' => true],
            ],
            'rowBorders' => [
                'color' => '#E5E7EB',
                'width' => 1,
            ],
        ]);

        $firstRowStyle = $this->bodyCellStyle($sections, 0);

        $this->assertStringContainsString('border-top: 1px solid #E5E7EB', $firstRowStyle);
    }

    public function testCustomRegionsRenderPerSideStrokes(): void
    {
        $sections = $this->adapt([
            'columns' => $this->columns(),
            'showBorders' => true,
            'headerBorders' => [
                'color' => '#111827',
                'width' => 2,
                'sides' => ['top' => true, 'right' => false, 'bottom' => true, 'left' => true],
            ],
            'rowBorders' => [
                'color' => '#9CA3AF',
                'width' => '3px',
                'sides' => ['top' => true, 'right' => true, 'bottom' => false, 'left' => true],
            ],
        ]);

        $headerStyle = $this->headerCellStyle($sections);
        $secondRowStyle = $this->bodyCellStyle($sections, 1);

        $this->assertStringContainsString('border-top: 2px solid #111827',    $headerStyle);
        $this->assertStringContainsString('border-right: none',               $headerStyle);
        $this->assertStringContainsString('border-bottom: 2px solid #111827', $headerStyle);
        $this->assertStringContainsString('border-left: 2px solid #111827',   $headerStyle);

        // "3px" string must coerce to integer 3.
        $this->assertStringContainsString('border-top: 3px solid #9CA3AF',    $secondRowStyle);
        $this->assertStringContainsString('border-right: 3px solid #9CA3AF',  $secondRowStyle);
        $this->assertStringContainsString('border-bottom: none',              $secondRowStyle);
        $this->assertStringContainsString('border-left: 3px solid #9CA3AF',   $secondRowStyle);
    }

    public function testWidthClampsAboveTwenty(): void
    {
        $sections = $this->adapt([
            'columns' => $this->columns(),
            'showBorders' => true,
            'rowBorders' => ['color' => '#000', 'width' => 99],
            'headerBorders' => ['color' => '#000', 'width' => -5, 'sides' => ['bottom' => false]],
        ]);

        $headerStyle = $this->headerCellStyle($sections);
        $firstRowStyle = $this->bodyCellStyle($sections, 0);

        // -5 clamps to 0; an enabled side with width 0 still renders as `0px solid`.
        $this->assertStringContainsString('border-top: 0px solid #000', $headerStyle);
        // 99 clamps to 20.
        $this->assertStringContainsString('border-top: 20px solid #000', $firstRowStyle);
    }

    public function testSidesAreOnlyDisabledByStrictFalse(): void
    {
        // Per spec: missing/null/0/"false" all coerce to true. Only literal
        // false disables the side.
        $sections = $this->adapt([
            'columns' => $this->columns(),
            'showBorders' => true,
            'headerBorders' => [
                'color' => '#000',
                'width' => 1,
                'sides' => ['top' => 0, 'right' => 'false', 'bottom' => null, 'left' => true],
            ],
        ]);

        $headerStyle = $this->headerCellStyle($sections);

        $this->assertStringContainsString('border-top: 1px solid #000',    $headerStyle);
        $this->assertStringContainsString('border-right: 1px solid #000',  $headerStyle);
        $this->assertStringContainsString('border-bottom: 1px solid #000', $headerStyle);
        $this->assertStringContainsString('border-left: 1px solid #000',   $headerStyle);
    }

    private function adapt(array $properties): array
    {
        $service = $this->pdfService();

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
        // Two columns, both using a simple string field so formatting never
        // touches PdfConfiguration::$currency_entity (uninitialized in the
        // lightweight test fixture used here).
        return [
            ['id' => 'product_key', 'header' => 'Item',  'field' => 'item.product_key'],
            ['id' => 'product_key2','header' => 'Item2', 'field' => 'item.product_key'],
        ];
    }

    private function pdfService(): PdfService
    {
        $service = (new \ReflectionClass(PdfService::class))->newInstanceWithoutConstructor();

        $company = new Company();
        $company->company_key = 'test-company';
        $service->company = $company;

        $invoice = new Invoice();
        // Two rows so we can assert seam behavior on row 0 vs row 1.
        $invoice->line_items = [
            (object) ['type_id' => '1', 'product_key' => 'A-1'],
            (object) ['type_id' => '1', 'product_key' => 'A-2'],
        ];

        $config = new PdfConfiguration($service);
        $config->entity = $invoice;
        $config->settings = (object) ['hide_empty_columns_on_pdf' => false];

        $service->config = $config;
        $service->html_variables = ['values' => [], 'labels' => []];

        return $service;
    }

    private function headerCellStyle(array $sections): string
    {
        $headerCells = $sections['items']['elements'][0]['elements'][0]['elements'][0]['elements'];
        return $headerCells[0]['properties']['style'];
    }

    private function bodyCellStyle(array $sections, int $rowIndex): string
    {
        $rows = $sections['items']['elements'][0]['elements'][1]['elements'];
        return $rows[$rowIndex]['elements'][0]['properties']['style'];
    }
}
