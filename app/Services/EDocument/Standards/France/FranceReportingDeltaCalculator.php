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

use InvalidArgumentException;

final readonly class FranceReportingDeltaCalculator
{
    public function __construct(
        private FranceReportEntryInverter $inverter,
    ) {}

    /**
     * @param iterable<int, FranceReportingSubject> $current
     * @param iterable<int, FranceReportingSubject> $accepted
     */
    public function calculate(iterable $current, iterable $accepted): FranceReportingDelta
    {
        $current = $this->keyBySubject($current);
        $accepted = $this->keyBySubject($accepted);
        $entries = [];
        $snapshots = [];

        foreach (array_unique([...array_keys($current), ...array_keys($accepted)]) as $key) {
            $currentSubject = $current[$key] ?? null;
            $acceptedSubject = $accepted[$key] ?? null;

            if ($currentSubject && $acceptedSubject && hash_equals($currentSubject->hash(), $acceptedSubject->hash())) {
                continue;
            }

            if ($acceptedSubject?->entry) {
                $entries[] = $this->inverter->invert($acceptedSubject->entry);
            }

            if ($currentSubject?->entry) {
                $entries[] = $currentSubject->entry;
                $snapshots[] = $currentSubject;
                continue;
            }

            if ($acceptedSubject) {
                $snapshots[] = $acceptedSubject->tombstone();
            }
        }

        return new FranceReportingDelta($entries, $snapshots);
    }

    /**
     * Replace a scope when report-level declarant context changes.
     *
     * @param iterable<int, FranceReportingSubject> $current
     * @param iterable<int, FranceReportingSubject> $accepted
     */
    public function replace(iterable $current, iterable $accepted): FranceReportingDelta
    {
        $current = $this->keyBySubject($current);
        $accepted = $this->keyBySubject($accepted);
        $entries = [];

        foreach ($accepted as $subject) {
            if ($subject->entry) {
                $entries[] = $this->inverter->invert($subject->entry);
            }
        }

        foreach ($current as $subject) {
            if ($subject->entry) {
                $entries[] = $subject->entry;
            }
        }

        $snapshots = array_values($current);

        foreach (array_diff_key($accepted, $current) as $subject) {
            $snapshots[] = $subject->tombstone();
        }

        return new FranceReportingDelta($entries, $snapshots);
    }

    /**
     * @param iterable<int, FranceReportingSubject> $subjects
     * @return array<string, FranceReportingSubject>
     */
    private function keyBySubject(iterable $subjects): array
    {
        $keyed = [];

        foreach ($subjects as $subject) {
            if (isset($keyed[$subject->key])) {
                throw new InvalidArgumentException("Duplicate France reporting subject [{$subject->key}].");
            }

            $keyed[$subject->key] = $subject;
        }

        ksort($keyed);

        return $keyed;
    }
}
