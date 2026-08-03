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
            '2026-09-15',
            'America/Los_Angeles',
        );

        $this->assertSame('2026-09-15', $date);
    }

    public function testActualApplicationInstantUsesTheCompanyTimezone(): void
    {
        $paymentable = $this->paymentableAt('2026-09-16 23:30:00 UTC');

        $date = app(FrancePaymentApplicationDateResolver::class)->resolve(
            $paymentable,
            '2026-09-15',
            'Australia/Sydney',
        );

        $this->assertSame('2026-09-17', $date);
    }

    public function testMissingPaymentableDoesNotFallBackToThePaymentDate(): void
    {
        $date = app(FrancePaymentApplicationDateResolver::class)->resolve(
            null,
            '2026-09-15',
            'Europe/Paris',
        );

        $this->assertNull($date);
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
