<?php

namespace Tests\Unit\EDocument\France;

use App\Models\Paymentable;
use App\Services\EDocument\Standards\France\FrancePaymentApplicationDateResolver;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class FrancePaymentApplicationDateResolverTest extends TestCase
{
    public function testDateDerivedTimestampPreservesThePaymentDateAcrossTimezones(): void
    {
        $paymentable = $this->paymentableAt('2026-09-15 00:00:00 UTC');

        $date = app(FrancePaymentApplicationDateResolver::class)->resolve(
            $paymentable,
            'America/Los_Angeles',
        );

        $this->assertSame('2026-09-15', $date);
    }

    public function testActualApplicationInstantUsesTheCompanyTimezone(): void
    {
        $paymentable = $this->paymentableAt('2026-09-16 23:30:00 UTC');

        $date = app(FrancePaymentApplicationDateResolver::class)->resolve(
            $paymentable,
            'Australia/Sydney',
        );

        $this->assertSame('2026-09-17', $date);
    }

    public function testMissingPaymentableDoesNotFallBackToThePaymentDate(): void
    {
        $date = app(FrancePaymentApplicationDateResolver::class)->resolve(
            null,
            'Europe/Paris',
        );

        $this->assertNull($date);
    }

    public function testNonMidnightApplicationNeverFallsBackToPaymentDate(): void
    {
        $paymentable = $this->paymentableAt('2026-09-15 23:30:00 UTC');

        $date = app(FrancePaymentApplicationDateResolver::class)->resolve(
            $paymentable,
            'Australia/Sydney',
        );

        $this->assertSame('2026-09-16', $date);
    }

    public function testEncodedBusinessDateRoundTripsInANegativeOffsetTimezone(): void
    {
        $resolver = app(FrancePaymentApplicationDateResolver::class);
        $paymentable = $this->paymentableAt(
            CarbonImmutable::createFromTimestampUTC(
                $resolver->encodeBusinessDate('2026-09-10', 'America/Los_Angeles'),
            )->toDateTimeString().' UTC',
        );

        $this->assertSame('2026-09-10', $resolver->resolve($paymentable, 'America/Los_Angeles'));
    }

    public function testCandidateBoundsIncludeLegacyAndTimezoneAwareTimestamps(): void
    {
        [$start, $end] = app(FrancePaymentApplicationDateResolver::class)
            ->candidateBounds('2026-01-01', '2026-01-31', 'America/Los_Angeles');

        $this->assertSame('2026-01-01 00:00:00', $start->toDateTimeString());
        $this->assertSame('2026-02-01 08:00:00', $end->toDateTimeString());
    }

    private function paymentableAt(string $timestamp): Paymentable
    {
        $paymentable = new Paymentable();
        $paymentable->setRawAttributes([
            'created_at' => CarbonImmutable::parse($timestamp)->timestamp,
        ]);

        return $paymentable;
    }
}
