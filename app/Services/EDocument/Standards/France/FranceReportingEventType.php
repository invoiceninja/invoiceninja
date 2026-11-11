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

namespace App\Services\EDocument\Standards\France;

enum FranceReportingEventType: int
{
    case TransactionSnapshot = 1001;
    case PaymentMovement = 1002;
    case PaymentSnapshot = 1003;
    case DocumentLifecycle = 1004;
    case ReportSubmission = 1005;
    case PaymentNotificationSnapshot = 1006;
    case PaymentNotificationSubmission = 1007;
    case SubmissionCallback = 1008;
    case ScopeInvalidation = 1009;

    /**
     * @return array<int, int>
     */
    public static function values(): array
    {
        return array_map(
            static fn(self $event): int => $event->value,
            self::cases(),
        );
    }

    /**
     * @return array<int, int>
     */
    public static function submissionValues(): array
    {
        return [
            self::ReportSubmission->value,
            self::PaymentNotificationSubmission->value,
        ];
    }

    /** @return array<int, int> */
    public static function retainedValues(): array
    {
        return array_values(array_filter(
            self::values(),
            static fn(int $value): bool => ! in_array($value, self::transientValues(), true),
        ));
    }

    /** @return array<int, int> */
    public static function transientValues(): array
    {
        return [
            self::SubmissionCallback->value,
            self::ScopeInvalidation->value,
        ];
    }
}
