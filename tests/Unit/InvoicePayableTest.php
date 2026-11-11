<?php

namespace Tests\Unit;

use App\Models\Invoice;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InvoicePayableTest extends TestCase
{
    #[DataProvider('payableInvoices')]
    public function testInvoicePaymentEligibility(int $status_id, bool $is_deleted, float $balance, bool $expected): void
    {
        $invoice = new Invoice();
        $invoice->status_id = $status_id;
        $invoice->is_deleted = $is_deleted;
        $invoice->balance = $balance;

        $this->assertSame($expected, $invoice->isPayable());
    }

    public static function payableInvoices(): array
    {
        return [
            'sent invoice with outstanding balance' => [Invoice::STATUS_SENT, false, 100, true],
            'partially paid invoice with outstanding balance' => [Invoice::STATUS_PARTIAL, false, 50, true],
            'draft invoice' => [Invoice::STATUS_DRAFT, false, 100, true],
            'sent invoice with no balance' => [Invoice::STATUS_SENT, false, 0, false],
            'partially paid invoice with no balance' => [Invoice::STATUS_PARTIAL, false, 0, false],
            'sent invoice with negative balance' => [Invoice::STATUS_SENT, false, -10, false],
            'partially paid invoice with negative balance' => [Invoice::STATUS_PARTIAL, false, -10, false],
            'paid invoice' => [Invoice::STATUS_PAID, false, 100, false],
            'cancelled invoice' => [Invoice::STATUS_CANCELLED, false, 100, false],
            'deleted draft invoice' => [Invoice::STATUS_DRAFT, true, 100, false],
            'deleted sent invoice' => [Invoice::STATUS_SENT, true, 100, false],
            'deleted partially paid invoice' => [Invoice::STATUS_PARTIAL, true, 50, false],
        ];
    }
}
