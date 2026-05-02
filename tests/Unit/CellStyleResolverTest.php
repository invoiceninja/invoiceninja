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
use App\Services\Pdf\CellStyleResolver;

/**
 * Validates the precedence chain documented in the JSON design API spec.
 *
 * For each axis (font-size, font-weight, font-style, color), the chain is:
 *   row.{label,value}Style.X  →  row.X (legacy flat)  →  row-type default  →  block default
 */
class CellStyleResolverTest extends TestCase
{
    private CellStyleResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new CellStyleResolver();
    }

    private function invoiceDetails(): array
    {
        return ['kind' => CellStyleResolver::KIND_INVOICE_DETAILS];
    }

    private function totalContext(bool $isTotal = false, bool $isBalance = false): array
    {
        return [
            'kind' => CellStyleResolver::KIND_TOTAL,
            'isTotal' => $isTotal,
            'isBalance' => $isBalance,
        ];
    }

    /* -------------------- fontSize precedence -------------------- */

    public function testLabelFontSizeFromRowLabelStyleWinsOverEverything(): void
    {
        $r = $this->resolver->resolveLabel(
            ['fontSize' => '12px'],
            ['fontSize' => '14px', 'labelStyle' => ['fontSize' => '20px']],
            $this->invoiceDetails(),
        );
        $this->assertSame('20px', $r['font-size']);
    }

    public function testLabelFontSizeFallsBackToRowLegacyFlat(): void
    {
        $r = $this->resolver->resolveLabel(
            ['fontSize' => '12px'],
            ['fontSize' => '14px'],
            $this->invoiceDetails(),
        );
        $this->assertSame('14px', $r['font-size']);
    }

    public function testLabelFontSizeFallsBackToBlockDefault(): void
    {
        $r = $this->resolver->resolveLabel(['fontSize' => '12px'], [], $this->invoiceDetails());
        $this->assertSame('12px', $r['font-size']);
    }

    public function testLabelFontSizeIsNullWhenNothingSet(): void
    {
        $r = $this->resolver->resolveLabel([], [], $this->invoiceDetails());
        $this->assertNull($r['font-size']);
    }

    public function testTotalRowFontSizeUsesTotalFontSize(): void
    {
        $r = $this->resolver->resolveValue(
            ['fontSize' => '12px', 'totalFontSize' => '16px'],
            ['isTotal' => true],
            $this->totalContext(isTotal: true),
        );
        $this->assertSame('16px', $r['font-size']);
    }

    public function testTotalRowFontSizeRowOverrideBeatsTotalFontSize(): void
    {
        $r = $this->resolver->resolveValue(
            ['totalFontSize' => '16px'],
            ['valueStyle' => ['fontSize' => '24px'], 'isTotal' => true],
            $this->totalContext(isTotal: true),
        );
        $this->assertSame('24px', $r['font-size']);
    }

    /* -------------------- fontWeight precedence -------------------- */

    public function testLabelFontWeightDefaultsToNormalForInvoiceDetails(): void
    {
        $r = $this->resolver->resolveLabel([], [], $this->invoiceDetails());
        $this->assertSame('normal', $r['font-weight']);
    }

    public function testLabelFontWeightDefaultsToBoldForTotalRow(): void
    {
        $r = $this->resolver->resolveLabel([], ['isTotal' => true], $this->totalContext(isTotal: true));
        $this->assertSame('bold', $r['font-weight']);
    }

    public function testLabelFontWeightHonoursTotalFontWeightProp(): void
    {
        $r = $this->resolver->resolveLabel(
            ['totalFontWeight' => '600'],
            ['isTotal' => true],
            $this->totalContext(isTotal: true),
        );
        $this->assertSame('600', $r['font-weight']);
    }

    public function testRowLabelStyleFontWeightOverridesRowTypeDefault(): void
    {
        $r = $this->resolver->resolveLabel(
            ['totalFontWeight' => 'bold'],
            ['isTotal' => true, 'labelStyle' => ['fontWeight' => 'normal']],
            $this->totalContext(isTotal: true),
        );
        $this->assertSame('normal', $r['font-weight']);
    }

    public function testRowLegacyFontWeightOverridesRowTypeDefault(): void
    {
        $r = $this->resolver->resolveLabel(
            ['totalFontWeight' => 'bold'],
            ['isTotal' => true, 'fontWeight' => '900'],
            $this->totalContext(isTotal: true),
        );
        $this->assertSame('900', $r['font-weight']);
    }

    /* -------------------- fontStyle precedence -------------------- */

    public function testFontStyleDefaultsToNormal(): void
    {
        $r = $this->resolver->resolveLabel([], [], $this->invoiceDetails());
        $this->assertSame('normal', $r['font-style']);
    }

    public function testFontStyleFromRowLabelStyleWins(): void
    {
        $r = $this->resolver->resolveLabel(
            [],
            ['fontStyle' => 'italic', 'labelStyle' => ['fontStyle' => 'normal']],
            $this->invoiceDetails(),
        );
        $this->assertSame('normal', $r['font-style']);
    }

    public function testFontStyleFromRowLegacyFlat(): void
    {
        $r = $this->resolver->resolveLabel([], ['fontStyle' => 'italic'], $this->invoiceDetails());
        $this->assertSame('italic', $r['font-style']);
    }

    /* -------------------- label color precedence -------------------- */

    public function testInvoiceDetailsLabelColorChain(): void
    {
        // Block fallback: labelColor wins over color
        $r = $this->resolver->resolveLabel(
            ['color' => '#000', 'labelColor' => '#666'],
            [],
            $this->invoiceDetails(),
        );
        $this->assertSame('#666', $r['color']);

        // Falls through to color when labelColor unset
        $r = $this->resolver->resolveLabel(['color' => '#000'], [], $this->invoiceDetails());
        $this->assertSame('#000', $r['color']);

        // Row legacy beats block
        $r = $this->resolver->resolveLabel(
            ['labelColor' => '#666'],
            ['color' => '#f00'],
            $this->invoiceDetails(),
        );
        $this->assertSame('#f00', $r['color']);

        // labelStyle.color beats everything
        $r = $this->resolver->resolveLabel(
            ['labelColor' => '#666'],
            ['color' => '#f00', 'labelStyle' => ['color' => '#0f0']],
            $this->invoiceDetails(),
        );
        $this->assertSame('#0f0', $r['color']);
    }

    public function testTotalLabelColorUsesTotalColorOnTotalRow(): void
    {
        $r = $this->resolver->resolveLabel(
            ['labelColor' => '#999', 'totalColor' => '#000'],
            ['isTotal' => true],
            $this->totalContext(isTotal: true),
        );
        $this->assertSame('#000', $r['color']);
    }

    public function testTotalLabelColorUsesBalanceColorOnBalanceRow(): void
    {
        $r = $this->resolver->resolveLabel(
            ['labelColor' => '#999', 'balanceColor' => '#f00'],
            ['isBalance' => true],
            $this->totalContext(isBalance: true),
        );
        $this->assertSame('#f00', $r['color']);
    }

    public function testTotalLabelColorFallsBackToLabelColorWhenTotalColorUnsetOnTotalRow(): void
    {
        $r = $this->resolver->resolveLabel(
            ['labelColor' => '#999'],
            ['isTotal' => true],
            $this->totalContext(isTotal: true),
        );
        $this->assertSame('#999', $r['color']);
    }

    public function testTotalLabelColorRowLegacyBeatsBlockTotalColor(): void
    {
        $r = $this->resolver->resolveLabel(
            ['totalColor' => '#000'],
            ['isTotal' => true, 'color' => '#abc'],
            $this->totalContext(isTotal: true),
        );
        $this->assertSame('#abc', $r['color']);
    }

    /* -------------------- value color precedence -------------------- */

    public function testInvoiceDetailsValueColorChain(): void
    {
        // Block fallback: color
        $r = $this->resolver->resolveValue(['color' => '#111'], [], $this->invoiceDetails());
        $this->assertSame('#111', $r['color']);

        // Row legacy color beats block
        $r = $this->resolver->resolveValue(['color' => '#111'], ['color' => '#222'], $this->invoiceDetails());
        $this->assertSame('#222', $r['color']);

        // valueStyle.color wins
        $r = $this->resolver->resolveValue(
            [],
            ['color' => '#222', 'valueStyle' => ['color' => '#333']],
            $this->invoiceDetails(),
        );
        $this->assertSame('#333', $r['color']);
    }

    public function testTotalValueColorAmountColorBeatsRowColor(): void
    {
        // Per spec: row.valueStyle.color → row.amountColor (total only) → row.color → block
        $r = $this->resolver->resolveValue(
            [],
            ['amountColor' => '#aaa', 'color' => '#bbb'],
            $this->totalContext(),
        );
        $this->assertSame('#aaa', $r['color']);
    }

    public function testTotalValueColorBlockFallbackUsesTotalColorOnTotalRow(): void
    {
        $r = $this->resolver->resolveValue(
            ['amountColor' => '#000', 'totalColor' => '#f00'],
            ['isTotal' => true],
            $this->totalContext(isTotal: true),
        );
        $this->assertSame('#f00', $r['color']);
    }

    public function testTotalValueColorBlockFallbackUsesBalanceColorOnBalanceRow(): void
    {
        $r = $this->resolver->resolveValue(
            ['amountColor' => '#000', 'balanceColor' => '#0f0'],
            ['isBalance' => true],
            $this->totalContext(isBalance: true),
        );
        $this->assertSame('#0f0', $r['color']);
    }

    public function testTotalValueColorFallsBackToAmountColorOnPlainRow(): void
    {
        $r = $this->resolver->resolveValue(
            ['amountColor' => '#456'],
            [],
            $this->totalContext(),
        );
        $this->assertSame('#456', $r['color']);
    }

    /* -------------------- empty / edge cases -------------------- */

    public function testEmptyCellTypographyTreatedAsAbsent(): void
    {
        $r = $this->resolver->resolveLabel(
            ['fontSize' => '12px'],
            ['labelStyle' => []],
            $this->invoiceDetails(),
        );
        $this->assertSame('12px', $r['font-size']);
    }

    public function testCellTypographyWithEmptyStringValueTreatedAsAbsent(): void
    {
        $r = $this->resolver->resolveLabel(
            ['fontSize' => '12px'],
            ['labelStyle' => ['fontSize' => '']],
            $this->invoiceDetails(),
        );
        $this->assertSame('12px', $r['font-size']);
    }

    public function testRowLegacyEmptyStringTreatedAsAbsent(): void
    {
        $r = $this->resolver->resolveLabel(
            ['fontSize' => '12px'],
            ['fontSize' => ''],
            $this->invoiceDetails(),
        );
        $this->assertSame('12px', $r['font-size']);
    }

    public function testNullFontSizeOmittedWhenNothingSet(): void
    {
        $r = $this->resolver->resolveValue([], [], $this->invoiceDetails());
        $this->assertNull($r['font-size']);
    }
}
