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

use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;
use App\DataMapper\InvoiceItem;
use App\Services\EDocument\Standards\Peppol\PeppolLineBuilder;

/**
 * Pure-predicate coverage for PeppolLineBuilder::isBlankItem().
 *
 * No DB, no Saxon, no HTTP — uses ReflectionClass::newInstanceWithoutConstructor()
 * so we can invoke the private predicate without bootstrapping a full Peppol
 * document. This file is a regression net for the silent-drop policy: every
 * row state we care about is tabulated below, and the test fails loudly if
 * the predicate's behaviour drifts.
 */
class PeppolLineBuilderBlankItemTest extends TestCase
{
    private ReflectionMethod $isBlankItem;
    private PeppolLineBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->builder = (new ReflectionClass(PeppolLineBuilder::class))
            ->newInstanceWithoutConstructor();

        $this->isBlankItem = new ReflectionMethod(PeppolLineBuilder::class, 'isBlankItem');
        $this->isBlankItem->setAccessible(true);
    }

    private function isBlank(InvoiceItem $item): bool
    {
        return (bool) $this->isBlankItem->invoke($this->builder, $item);
    }

    private function item(array $overrides = []): InvoiceItem
    {
        $item = new InvoiceItem();
        foreach ($overrides as $field => $value) {
            $item->{$field} = $value;
        }
        return $item;
    }

    /**
     * UI ghost row: merchant added a line in the editor and never typed
     * anything. Every field is at its default. Must drop.
     */
    public function testGhostRowIsBlank(): void
    {
        $this->assertTrue($this->isBlank($this->item()));
    }

    /**
     * "Section header" — notes filled in but no money, no tax tag.
     * Dropped: a notes-only row with no tax cannot produce a valid Peppol
     * line (no breakdown entry under any synthesised category). Section
     * dividers are a PDF concern, not a structured-document concern.
     */
    public function testTextOnlyRowWithoutTaxIsBlank(): void
    {
        $this->assertTrue($this->isBlank($this->item([
            'notes'      => 'Section header',
            'product_key' => 'Header',
        ])));
    }

    /**
     * Free item with a tax tag: $0 price but the merchant is signalling
     * tax category. Kept — the breakdown will get a category subtotal of
     * 0/0 which is structurally valid.
     */
    public function testZeroCostWithTaxNameIsKept(): void
    {
        $this->assertFalse($this->isBlank($this->item([
            'cost'      => 0,
            'tax_name1' => 'VAT',
            'tax_rate1' => 19,
        ])));
    }

    /**
     * Tax-config-only row (zero amounts but tax tag present). Kept for
     * the same reason as the free-item case.
     */
    public function testZeroAmountsWithTaxNameIsKept(): void
    {
        $this->assertFalse($this->isBlank($this->item([
            'tax_name1' => 'TVA',
            'tax_rate1' => 21,
        ])));
    }

    /**
     * Real billable, fully tagged: the happy path. Always kept.
     */
    public function testBillableTaxedRowIsKept(): void
    {
        $this->assertFalse($this->isBlank($this->item([
            'product_key' => 'Widget',
            'cost'        => 100,
            'quantity'    => 2,
            'tax_name1'   => 'VAT',
            'tax_rate1'   => 19,
        ])));
    }

    /**
     * Critical: a row with a non-zero cost but NO tax_name1 must be kept.
     * Dropping it would change the invoice total silently. The schematron
     * will surface this row as a real Peppol-validity failure (BR-{cat}-01),
     * which is the desired loud-fail behaviour.
     */
    public function testBillableRowWithoutTaxNameIsKept(): void
    {
        $this->assertFalse($this->isBlank($this->item([
            'product_key' => 'Widget',
            'cost'        => 100,
            'quantity'    => 2,
        ])));
    }

    /**
     * Refund / negative-cost line with a tax tag. The predicate is
     * cost-magnitude-aware (uses abs()), so a -10 row is non-zero and kept.
     */
    public function testNegativeCostWithTaxNameIsKept(): void
    {
        $this->assertFalse($this->isBlank($this->item([
            'cost'      => -10,
            'quantity'  => 1,
            'tax_name1' => 'VAT',
            'tax_rate1' => 19,
        ])));
    }

    /**
     * Floating-point residue boundary: a value that is zero in spirit but
     * non-zero by `==` due to upstream arithmetic. The predicate uses a
     * half-cent epsilon (matches the round($..., 2) regime used throughout
     * PeppolLineBuilder), so 1e-9 is treated as zero.
     */
    public function testSubHalfCentCostIsTreatedAsZero(): void
    {
        $this->assertTrue($this->isBlank($this->item([
            'cost' => 0.000000001,
        ])));
    }

    /**
     * Boundary just BELOW the half-cent threshold — must drop.
     */
    public function testJustBelowHalfCentIsZero(): void
    {
        $this->assertTrue($this->isBlank($this->item([
            'cost' => 0.0049,
        ])));
    }

    /**
     * Boundary just AT/ABOVE the half-cent threshold — must keep.
     * 0.005 rounds to $0.01 at 2dp, so it is a real (smallest-possible)
     * monetary value and must not be silently dropped.
     */
    public function testHalfCentBoundaryIsNonZero(): void
    {
        $this->assertFalse($this->isBlank($this->item([
            'cost' => 0.005,
        ])));
    }

    /**
     * Single-character tax_name1 — strlen is 1, considered absent
     * (matches PeppolTaxCalculator::getAllUsedTaxes() filter
     * `strlen($tax['name']) > 1`). With cost=0 the row is blank.
     */
    public function testSingleCharTaxNameIsTreatedAsMissing(): void
    {
        $this->assertTrue($this->isBlank($this->item([
            'tax_name1' => 'X',
        ])));
    }

    /**
     * Two-character tax_name1 with cost=0 — keep. Even a short tax label
     * is a valid breakdown signal.
     */
    public function testTwoCharTaxNameIsKept(): void
    {
        $this->assertFalse($this->isBlank($this->item([
            'tax_name1' => 'GB',
            'tax_rate1' => 20,
        ])));
    }

    /**
     * Whitespace-only tax_name1 ("  ") with strlen 2 — interesting edge
     * case. The predicate uses strlen, NOT strlen(trim(...)), so a
     * whitespace-only label is NOT treated as missing. This mirrors the
     * (likewise non-trimming) filter in PeppolTaxCalculator::getAllUsedTaxes,
     * keeping the two predicates symmetric. If we ever tighten one, we
     * tighten both together.
     */
    public function testWhitespaceTaxNameMirrorsCalculatorBehaviour(): void
    {
        $this->assertFalse($this->isBlank($this->item([
            'tax_name1' => '  ',
        ])));
    }

    /**
     * A row with discount but no cost and no tax. Discount alone produces
     * no line total, no tax breakdown contribution — drop is loss-less.
     */
    public function testDiscountOnlyRowIsBlank(): void
    {
        $this->assertTrue($this->isBlank($this->item([
            'discount'           => 50,
            'is_amount_discount' => true,
        ])));
    }

    /**
     * Quantity set but cost=0 and no tax tag. "5 units of nothing,
     * untagged" — drop.
     */
    public function testQuantityOnlyRowWithoutTaxIsBlank(): void
    {
        $this->assertTrue($this->isBlank($this->item([
            'quantity' => 5,
        ])));
    }
}
