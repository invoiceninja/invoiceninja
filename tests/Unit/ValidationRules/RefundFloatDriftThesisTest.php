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

namespace Tests\Unit\ValidationRules;

use App\Utils\BcMath;
use Tests\TestCase;

/**
 * Proves the IEEE-754 thesis behind #12231 independently of the current
 * ValidRefundableRequest implementation.
 *
 * The production bug was not "wrong business logic". A full refund of a
 * 30.20 payment split as 18.35 + 11.85 is arithmetically exact, but the
 * pre-fix code summed those amounts with native floats and compared with
 * strict `>`. PHP evaluates 18.35 + 11.85 as 30.200000000000003, which
 * is greater than 30.20, so the refund was rejected.
 *
 * The same subtraction + `>` pattern in checkInvoice() false-rejects
 * other exact remaining balances (e.g. 0.30 - 0.10 vs a 0.20 refund).
 */
class RefundFloatDriftThesisTest extends TestCase
{
    /**
     * The reported amounts are not exactly representable as IEEE-754
     * doubles. Their native sum is therefore not identical to 30.20.
     */
    public function testReportedInvoiceAmountsSumPastThirtyTwentyAsNativeFloats(): void
    {
        $invoice_a = 18.35;
        $invoice_b = 11.85;
        $payment = 30.20;

        $native_sum = $invoice_a + $invoice_b;

        $this->assertNotSame($payment, $native_sum);
        $this->assertTrue($native_sum > $payment);
        $this->assertSame('30.200000000000003', var_export($native_sum, true));
    }

    /**
     * The pre-fix checkTotalRefundableAmount() used Collection::sum(),
     * which accumulates with native `+=`. That is the exact operator
     * that produced texts.max_refundable_payment for the reported case.
     */
    public function testLaravelCollectionSumUsesNativeFloatAccumulationAndExceedsPayment(): void
    {
        $request_invoices = [
            ['amount' => 18.35],
            ['amount' => 11.85],
        ];

        $total_refund_requested = collect($request_invoices)->sum('amount');
        $max_total_refundable = 30.20;

        $this->assertTrue($total_refund_requested > $max_total_refundable);
    }

    /**
     * Replica of the pre-fix total-refund check. It must reject the
     * reported full refund even though the requested amounts equal the
     * payment exactly in decimal arithmetic.
     */
    public function testPreFixTotalRefundCheckRejectsExactSplitOfThirtyTwenty(): void
    {
        $request_invoices = [
            ['amount' => 18.35],
            ['amount' => 11.85],
        ];

        $this->assertFalse(
            $this->preFixTotalRefundIsAllowed(30.20, 0, $request_invoices),
            'Native float sum of 18.35 + 11.85 should fail a strict > against 30.20'
        );
    }

    /**
     * The same replica must still reject a genuinely excessive refund,
     * so the thesis is not "all comparisons are noise".
     */
    public function testPreFixTotalRefundCheckStillRejectsGenuinelyExcessiveRefund(): void
    {
        $request_invoices = [
            ['amount' => 30.21],
        ];

        $this->assertFalse($this->preFixTotalRefundIsAllowed(30.20, 0, $request_invoices));
    }

    /**
     * BcMath at scale 2 treats 18.35 + 11.85 as equal to 30.20, which is
     * the intended currency comparison.
     */
    public function testBcMathScaleTwoDoesNotTreatReportedFullRefundAsExcess(): void
    {
        $total_refund_requested = BcMath::sum([18.35, 11.85], 2);
        $max_total_refundable = BcMath::sub(30.20, 0, 2);

        $this->assertFalse(BcMath::greaterThan($total_refund_requested, $max_total_refundable, 2));
        $this->assertTrue(BcMath::lessThanOrEqual($total_refund_requested, $max_total_refundable, 2));
        $this->assertSame('30.20', $total_refund_requested);
        $this->assertSame('30.20', $max_total_refundable);
    }

    /**
     * For the reported invoices, per-line `amount - refunded` does not
     * false-reject: 18.35 > (18.35 - 0) is false. The production failure
     * was the total sum, not the per-invoice check.
     */
    public function testPreFixPerInvoiceCheckDoesNotFalseRejectReportedFullLineRefunds(): void
    {
        $this->assertTrue($this->preFixInvoiceRefundIsAllowed(18.35, 0, 18.35));
        $this->assertTrue($this->preFixInvoiceRefundIsAllowed(11.85, 0, 11.85));
    }

    /**
     * The same `amount - refunded` then strict `>` pattern does
     * false-reject other exact remainders. 0.30 - 0.10 evaluates below
     * 0.20, so a remaining refund of 0.20 is treated as excessive.
     */
    public function testPreFixPerInvoiceCheckRejectsExactRemainingBalanceBecauseSubtractionUnderflows(): void
    {
        $this->assertFalse(
            $this->preFixInvoiceRefundIsAllowed(0.30, 0.10, 0.20),
            '0.30 - 0.10 as a native float is less than 0.20, so request > remaining is true'
        );

        $this->assertFalse(BcMath::greaterThan(0.20, BcMath::sub(0.30, 0.10, 2), 2));
    }

    /**
     * @param  array<int, array{amount: float}>  $request_invoices
     */
    private function preFixTotalRefundIsAllowed(float $payment_amount, float $payment_refunded, array $request_invoices): bool
    {
        $total_refund_requested = collect($request_invoices)->sum('amount');

        if ($total_refund_requested <= 0) {
            return true;
        }

        $max_cash_refund = $payment_amount - $payment_refunded;
        $max_credit_refund = 0;
        $max_total_refundable = $max_cash_refund + $max_credit_refund;

        if ($total_refund_requested > $max_total_refundable) {
            return false;
        }

        return true;
    }

    private function preFixInvoiceRefundIsAllowed(float $applied, float $already_refunded, float $requested): bool
    {
        $refundable_amount = ($applied - $already_refunded);

        if ($requested > $refundable_amount) {
            return false;
        }

        return true;
    }
}
