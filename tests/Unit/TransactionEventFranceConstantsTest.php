<?php

namespace Tests\Unit;

use App\Models\TransactionEvent;
use Tests\TestCase;

class TransactionEventFranceConstantsTest extends TestCase
{
    public function testFranceReportingEventsAreIsolatedFromTaxReportingEvents(): void
    {
        $this->assertSame([
            TransactionEvent::INVOICE_UPDATED,
            TransactionEvent::PAYMENT_REFUNDED,
            TransactionEvent::PAYMENT_DELETED,
            TransactionEvent::PAYMENT_CASH,
        ], TransactionEvent::TAX_REPORTING_EVENTS);

        $this->assertSame([
            TransactionEvent::FR_B2C_TRANSACTION,
            TransactionEvent::FR_B2C_PAYMENT,
            TransactionEvent::FR_VAT_EXCLUDED_TRANSACTION,
            TransactionEvent::FR_VAT_EXCLUDED_PAYMENT,
            TransactionEvent::FR_CORRECTIVE_TRANSACTION,
            TransactionEvent::FR_CORRECTIVE_PAYMENT,
        ], TransactionEvent::FR_REPORTING_EVENTS);

        $this->assertEmpty(array_intersect(
            TransactionEvent::TAX_REPORTING_EVENTS,
            TransactionEvent::FR_REPORTING_EVENTS
        ));

        $this->assertSame([
            TransactionEvent::FR_REPORT_SUBMISSION_B2C,
            TransactionEvent::FR_REPORT_SUBMISSION_VAT_EXCLUDED,
            TransactionEvent::FR_REPORT_SUBMISSION_CORRECTIVE,
        ], TransactionEvent::FR_REPORT_SUBMISSION_EVENTS);

        $this->assertEmpty(array_intersect(
            TransactionEvent::FR_REPORTING_EVENTS,
            TransactionEvent::FR_REPORT_SUBMISSION_EVENTS
        ));
    }
}
