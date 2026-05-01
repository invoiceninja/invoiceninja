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

use Tests\TestCase;
use Tests\MockAccountData;
use App\Services\Pdf\PdfService;
use App\Services\Pdf\PdfConfiguration;
use App\Services\Pdf\JsonToSectionsAdapter;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * End-to-end coverage of the cell-typography spec wired into
 * JsonToSectionsAdapter for both invoice-details and total blocks.
 */
class JsonDesignCellStyleTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    private PdfService $ps;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTestData();

        $invitation = $this->invoice->invitations()->first();
        $this->ps = new PdfService($invitation, 'product');
        $this->ps->config = (new PdfConfiguration($this->ps))->init();
        $this->ps->html_variables = (new \App\Utils\HtmlEngine($invitation))->generateLabelsAndValues();
    }

    private function adapt(array $blocks): array
    {
        return (new JsonToSectionsAdapter(['pageSettings' => [], 'blocks' => $blocks], $this->ps))->toSections();
    }

    private function detailsBlock(array $properties, string $id = 'inv-details'): array
    {
        return [
            'id' => $id,
            'type' => 'invoice-details',
            'gridPosition' => ['x' => 0, 'y' => 0, 'w' => 6, 'h' => 3],
            'properties' => $properties,
        ];
    }

    private function totalBlock(array $properties, string $id = 'totals'): array
    {
        return [
            'id' => $id,
            'type' => 'total',
            'gridPosition' => ['x' => 0, 'y' => 0, 'w' => 6, 'h' => 3],
            'properties' => $properties,
        ];
    }

    private function detailsCells(array $sections, string $id): array
    {
        return $sections[$id]['elements'][0]['elements'][0]['elements'];
    }

    private function totalRows(array $sections, string $id): array
    {
        return $sections[$id]['elements'][0]['elements'][0]['elements'];
    }

    /* -------------------- invoice-details: column-level props -------------------- */

    public function testInvoiceDetailsLabelAlignAndValueAlign(): void
    {
        $sections = $this->adapt([$this->detailsBlock([
            'labelAlign' => 'right',
            'valueAlign' => 'center',
            'items' => [['variable' => '$invoice.number', 'label' => 'Invoice #:', 'show' => true]],
        ])]);

        $cells = $this->detailsCells($sections, 'inv-details');
        $this->assertStringContainsString('text-align: right', $cells[0]['properties']['style']);
        $this->assertStringContainsString('text-align: center', $cells[1]['properties']['style']);
    }

    public function testInvoiceDetailsValueAlignDefaultsToBlockAlign(): void
    {
        $sections = $this->adapt([$this->detailsBlock([
            'align' => 'right',
            'items' => [['variable' => '$invoice.number', 'label' => 'Invoice #:', 'show' => true]],
        ])]);

        $cells = $this->detailsCells($sections, 'inv-details');
        // valueAlign falls back to align
        $this->assertStringContainsString('text-align: right', $cells[1]['properties']['style']);
        // labelAlign default is "left" regardless of block align
        $this->assertStringContainsString('text-align: left', $cells[0]['properties']['style']);
    }

    public function testInvoiceDetailsLabelValueGapAppliedAsPaddingRightAfterPadding(): void
    {
        $sections = $this->adapt([$this->detailsBlock([
            'labelPadding' => '4px',
            'labelValueGap' => '20px',
            'items' => [['variable' => '$invoice.number', 'label' => 'Invoice #:', 'show' => true]],
        ])]);

        $label_style = $this->detailsCells($sections, 'inv-details')[0]['properties']['style'];

        // Both must appear, AND padding must come before padding-right so the
        // longhand survives the shorthand reset (CSS source-order rule)
        $this->assertStringContainsString('padding: 4px', $label_style);
        $this->assertStringContainsString('padding-right: 20px', $label_style);

        $padding_pos = strpos($label_style, 'padding: ');
        $right_pos = strpos($label_style, 'padding-right: ');
        $this->assertLessThan($right_pos, $padding_pos,
            'padding shorthand must precede padding-right longhand');
    }

    public function testInvoiceDetailsRowSpacingAppliedToBothCells(): void
    {
        $sections = $this->adapt([$this->detailsBlock([
            'rowSpacing' => '6px',
            'items' => [['variable' => '$invoice.number', 'label' => 'Invoice #:', 'show' => true]],
        ])]);

        $cells = $this->detailsCells($sections, 'inv-details');
        $this->assertStringContainsString('padding-bottom: 6px', $cells[0]['properties']['style']);
        $this->assertStringContainsString('padding-bottom: 6px', $cells[1]['properties']['style']);
    }

    public function testInvoiceDetailsValueMinWidthAppliedOnlyToValueCell(): void
    {
        $sections = $this->adapt([$this->detailsBlock([
            'valueMinWidth' => '120px',
            'items' => [['variable' => '$invoice.number', 'label' => 'Invoice #:', 'show' => true]],
        ])]);

        $cells = $this->detailsCells($sections, 'inv-details');
        $this->assertStringNotContainsString('min-width', $cells[0]['properties']['style']);
        $this->assertStringContainsString('min-width: 120px', $cells[1]['properties']['style']);
    }

    public function testInvoiceDetailsShowLabelsFalseCollapsesToValueOnly(): void
    {
        $sections = $this->adapt([$this->detailsBlock([
            'showLabels' => false,
            'items' => [['variable' => '$invoice.number', 'label' => 'Invoice #:', 'show' => true]],
        ])]);

        $cells = $this->detailsCells($sections, 'inv-details');
        $this->assertCount(1, $cells, 'showLabels=false must produce a single value cell');
        $this->assertSame('$invoice.number', $cells[0]['content']);
    }

    public function testInvoiceDetailsLineHeightAppliedToBothCells(): void
    {
        $sections = $this->adapt([$this->detailsBlock([
            'lineHeight' => '1.8',
            'items' => [['variable' => '$invoice.number', 'label' => 'Invoice #:', 'show' => true]],
        ])]);

        $cells = $this->detailsCells($sections, 'inv-details');
        $this->assertStringContainsString('line-height: 1.8', $cells[0]['properties']['style']);
        $this->assertStringContainsString('line-height: 1.8', $cells[1]['properties']['style']);
    }

    /* -------------------- invoice-details: per-row precedence -------------------- */

    public function testInvoiceDetailsPerRowLabelStyleOverridesBlockLabelColor(): void
    {
        $sections = $this->adapt([$this->detailsBlock([
            'labelColor' => '#999',
            'fieldConfigs' => [[
                'id' => 'r1',
                'label' => '$number_label',
                'variable' => '$number',
                'labelStyle' => ['color' => '#000'],
            ]],
        ])]);

        $cells = $this->detailsCells($sections, 'inv-details');
        $this->assertStringContainsString('color: #000', $cells[0]['properties']['style']);
        // Block labelColor must not appear in label cell when overridden
        $this->assertStringNotContainsString('color: #999', $cells[0]['properties']['style']);
    }

    public function testInvoiceDetailsLegacyFlatRowFontSizeFallback(): void
    {
        $sections = $this->adapt([$this->detailsBlock([
            'fontSize' => '12px',
            'fieldConfigs' => [[
                'id' => 'r1',
                'label' => '$number_label',
                'variable' => '$number',
                'fontSize' => '20px', // legacy flat
            ]],
        ])]);

        $cells = $this->detailsCells($sections, 'inv-details');
        $this->assertStringContainsString('font-size: 20px', $cells[0]['properties']['style']);
        $this->assertStringContainsString('font-size: 20px', $cells[1]['properties']['style']);
    }

    /* -------------------- total block: column alignment -------------------- */

    public function testTotalLabelAlignAndValueAlignDefaultsAreRight(): void
    {
        $sections = $this->adapt([$this->totalBlock([
            'items' => [['label' => 'Subtotal', 'field' => '$10.00']],
        ])]);

        $cells = $this->totalRows($sections, 'totals')[0]['elements'];
        $this->assertStringContainsString('text-align: right', $cells[0]['properties']['style']);
        $this->assertStringContainsString('text-align: right', $cells[1]['properties']['style']);
    }

    public function testTotalLabelAlignOverridable(): void
    {
        $sections = $this->adapt([$this->totalBlock([
            'labelAlign' => 'left',
            'valueAlign' => 'left',
            'items' => [['label' => 'Subtotal', 'field' => '$10.00']],
        ])]);

        $cells = $this->totalRows($sections, 'totals')[0]['elements'];
        $this->assertStringContainsString('text-align: left', $cells[0]['properties']['style']);
        $this->assertStringContainsString('text-align: left', $cells[1]['properties']['style']);
    }

    /* -------------------- total block: row-type color resolution -------------------- */

    public function testTotalLabelColorOnIsTotalRowUsesTotalColor(): void
    {
        $sections = $this->adapt([$this->totalBlock([
            'labelColor' => '#999',
            'totalColor' => '#000',
            'items' => [
                ['label' => 'Subtotal', 'field' => '$10.00'],
                ['label' => 'Total', 'field' => '$11.00', 'isTotal' => true],
            ],
        ])]);

        $rows = $this->totalRows($sections, 'totals');
        $subtotal_label_style = $rows[0]['elements'][0]['properties']['style'];
        $total_label_style = $rows[1]['elements'][0]['properties']['style'];

        $this->assertStringContainsString('color: #999', $subtotal_label_style,
            'Plain row label uses block labelColor');
        $this->assertStringContainsString('color: #000', $total_label_style,
            'isTotal row label uses totalColor');
    }

    public function testTotalLabelColorOnIsBalanceRowUsesBalanceColor(): void
    {
        $sections = $this->adapt([$this->totalBlock([
            'labelColor' => '#999',
            'balanceColor' => '#f00',
            'items' => [['label' => 'Balance', 'field' => '$5.00', 'isBalance' => true]],
        ])]);

        $cells = $this->totalRows($sections, 'totals')[0]['elements'];
        $this->assertStringContainsString('color: #f00', $cells[0]['properties']['style']);
    }

    public function testTotalValueColorChainAmountColorBeatsRowColorFromRowLevel(): void
    {
        $sections = $this->adapt([$this->totalBlock([
            'amountColor' => '#000',
            'items' => [[
                'label' => 'Subtotal',
                'field' => '$10.00',
                'amountColor' => '#aaa', // row-level amountColor
                'color' => '#bbb',       // row-level legacy color (label fallback)
            ]],
        ])]);

        $cells = $this->totalRows($sections, 'totals')[0]['elements'];
        // Value uses row.amountColor; label uses row.color (per spec)
        $this->assertStringContainsString('color: #aaa', $cells[1]['properties']['style']);
        $this->assertStringContainsString('color: #bbb', $cells[0]['properties']['style']);
    }

    /* -------------------- total block: row-type font defaults -------------------- */

    public function testTotalIsTotalRowFontWeightDefaultsBold(): void
    {
        $sections = $this->adapt([$this->totalBlock([
            'items' => [
                ['label' => 'Subtotal', 'field' => '$10.00'],
                ['label' => 'Total', 'field' => '$11.00', 'isTotal' => true],
            ],
        ])]);

        $rows = $this->totalRows($sections, 'totals');
        $this->assertStringContainsString('font-weight: normal', $rows[0]['elements'][0]['properties']['style']);
        $this->assertStringContainsString('font-weight: bold', $rows[1]['elements'][0]['properties']['style']);
        $this->assertStringContainsString('font-weight: bold', $rows[1]['elements'][1]['properties']['style']);
    }

    public function testTotalIsTotalRowFontSizeUsesTotalFontSize(): void
    {
        $sections = $this->adapt([$this->totalBlock([
            'fontSize' => '12px',
            'totalFontSize' => '18px',
            'items' => [
                ['label' => 'Subtotal', 'field' => '$10.00'],
                ['label' => 'Total', 'field' => '$11.00', 'isTotal' => true],
            ],
        ])]);

        $rows = $this->totalRows($sections, 'totals');
        $this->assertStringContainsString('font-size: 12px', $rows[0]['elements'][0]['properties']['style']);
        $this->assertStringContainsString('font-size: 18px', $rows[1]['elements'][0]['properties']['style']);
    }

    /* -------------------- total block: per-row labelStyle / valueStyle -------------------- */

    public function testTotalPerRowLabelStyleOverridesEverything(): void
    {
        $sections = $this->adapt([$this->totalBlock([
            'totalColor' => '#000',
            'totalFontWeight' => 'bold',
            'items' => [[
                'label' => 'Total',
                'field' => '$11.00',
                'isTotal' => true,
                'labelStyle' => ['color' => '#0f0', 'fontWeight' => '300', 'fontStyle' => 'italic'],
            ]],
        ])]);

        $label_style = $this->totalRows($sections, 'totals')[0]['elements'][0]['properties']['style'];
        $this->assertStringContainsString('color: #0f0', $label_style);
        $this->assertStringContainsString('font-weight: 300', $label_style);
        $this->assertStringContainsString('font-style: italic', $label_style);
    }

    /* -------------------- total block: column knobs -------------------- */

    public function testTotalLabelPaddingValuePaddingValueMinWidthShowLabels(): void
    {
        $sections = $this->adapt([$this->totalBlock([
            'labelPadding' => '2px',
            'valuePadding' => '4px',
            'valueMinWidth' => '100px',
            'items' => [['label' => 'Subtotal', 'field' => '$10.00']],
        ])]);

        $cells = $this->totalRows($sections, 'totals')[0]['elements'];
        $this->assertStringContainsString('padding: 2px', $cells[0]['properties']['style']);
        $this->assertStringContainsString('padding: 4px', $cells[1]['properties']['style']);
        $this->assertStringContainsString('min-width: 100px', $cells[1]['properties']['style']);
    }

    public function testTotalShowLabelsFalseCollapsesToValueOnly(): void
    {
        $sections = $this->adapt([$this->totalBlock([
            'showLabels' => false,
            'items' => [['label' => 'Subtotal', 'field' => '$10.00']],
        ])]);

        $cells = $this->totalRows($sections, 'totals')[0]['elements'];
        $this->assertCount(1, $cells);
        $this->assertSame('$10.00', $cells[0]['content']);
    }

    /* -------------------- backward compatibility -------------------- */

    public function testInvoiceDetailsBackwardCompatNoNewKeys(): void
    {
        // A pre-spec template with only the old props must still produce a
        // working, well-formed style with sensible defaults.
        $sections = $this->adapt([$this->detailsBlock([
            'fontSize' => '12px',
            'color' => '#374151',
            'labelColor' => '#6B7280',
            'items' => [['variable' => '$invoice.number', 'label' => 'Invoice #:', 'show' => true]],
        ])]);

        $cells = $this->detailsCells($sections, 'inv-details');
        $label_style = $cells[0]['properties']['style'];
        $value_style = $cells[1]['properties']['style'];

        // Block-level defaults reach the cells via the resolver
        $this->assertStringContainsString('font-size: 12px', $label_style);
        $this->assertStringContainsString('color: #6B7280', $label_style);
        $this->assertStringContainsString('font-size: 12px', $value_style);
        $this->assertStringContainsString('color: #374151', $value_style);

        // Default labelValueGap of 12px is preserved
        $this->assertStringContainsString('padding-right: 12px', $label_style);
    }

    public function testTotalBackwardCompatNoNewKeys(): void
    {
        $sections = $this->adapt([$this->totalBlock([
            'fontSize' => '12px',
            'totalFontSize' => '14px',
            'totalFontWeight' => 'bold',
            'labelColor' => '#6B7280',
            'amountColor' => '#374151',
            'totalColor' => '#000',
            'spacing' => '4px',
            'labelValueGap' => '20px',
            'items' => [
                ['label' => 'Subtotal', 'field' => '$10.00'],
                ['label' => 'Total', 'field' => '$11.00', 'isTotal' => true],
            ],
        ])]);

        $rows = $this->totalRows($sections, 'totals');
        $subtotal_value = $rows[0]['elements'][1]['properties']['style'];
        $total_value = $rows[1]['elements'][1]['properties']['style'];

        $this->assertStringContainsString('font-size: 12px', $subtotal_value);
        $this->assertStringContainsString('color: #374151', $subtotal_value);
        $this->assertStringContainsString('font-size: 14px', $total_value);
        $this->assertStringContainsString('font-weight: bold', $total_value);
        $this->assertStringContainsString('color: #000', $total_value);
        $this->assertStringContainsString('padding-bottom: 4px', $subtotal_value);
        $this->assertStringContainsString('padding-right: 20px', $rows[0]['elements'][0]['properties']['style']);
    }
}
