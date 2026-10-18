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

use App\Models\TransactionEvent;
use App\Services\EDocument\Standards\France\FranceReportingEventType;
use App\Services\EDocument\Standards\France\FranceReportingStatus;
use PHPUnit\Framework\TestCase;

class TransactionEventFranceConstantsTest extends TestCase
{
    public function test_france_event_types_are_typed_and_isolated_from_tax_events(): void
    {
        $this->assertSame([1001, 1002, 1003, 1004, 1005, 1006, 1007, 1008, 1009], FranceReportingEventType::values());
        $this->assertSame([1001, 1002, 1003, 1004, 1005, 1006, 1007], FranceReportingEventType::retainedValues());
        $this->assertSame([1008, 1009], FranceReportingEventType::transientValues());
        $this->assertSame([1005, 1007], FranceReportingEventType::submissionValues());
        $this->assertEmpty(array_intersect(
            TransactionEvent::TAX_REPORTING_EVENTS,
            FranceReportingEventType::values(),
        ));
    }

    public function test_france_submission_statuses_distinguish_sent_from_accepted(): void
    {
        $this->assertSame(1, FranceReportingStatus::Pending->value);
        $this->assertSame(2, FranceReportingStatus::Sent->value);
        $this->assertSame(3, FranceReportingStatus::Accepted->value);
        $this->assertSame(4, FranceReportingStatus::Rejected->value);
        $this->assertSame(5, FranceReportingStatus::RetryableFailure->value);
        $this->assertSame([1, 2, 5], FranceReportingStatus::openValues());
    }
}
