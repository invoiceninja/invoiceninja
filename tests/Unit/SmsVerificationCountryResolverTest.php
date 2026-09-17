<?php

namespace Tests\Unit;

use Modules\Admin\Services\Spam\SmsVerificationCountryResolver;
use PHPUnit\Framework\TestCase;

class SmsVerificationCountryResolverTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();

        if (
            ! class_exists(\Modules\Admin\Services\Spam\SmsVerificationCountryResolver::class)
        ) {
            $this->markTestSkipped('Admin module SMS verification country resolver is not installed.');
        }
    }

    public function testItUsesTheLongestMatchingPrefix(): void
    {
        $countries = (new SmsVerificationCountryResolver())->resolve('+1 242 555 1234', [
            'US' => '+1',
            'CA' => '+1',
            'BS' => '+1242',
        ]);

        $this->assertSame(['BS'], $countries);
    }

    public function testItReturnsCountriesSharingTheSamePrefix(): void
    {
        $countries = (new SmsVerificationCountryResolver())->resolve('+1 555 123 4567', [
            'US' => '+1',
            'CA' => '+1',
        ]);

        $this->assertSame(['US', 'CA'], $countries);
    }

    public function testItRejectsAnInvalidNumber(): void
    {
        $countries = (new SmsVerificationCountryResolver())->resolve('5551234', ['US' => '+1']);

        $this->assertSame([], $countries);
    }

    public function testItMatchesAConfiguredHighRiskPrefix(): void
    {
        $prefix = (new SmsVerificationCountryResolver())->matchPrefix(
            '+44 20 7946 0958',
            ['+44', '+91'],
        );

        $this->assertSame('+44', $prefix);
    }

    public function testItDoesNotMatchAnUnconfiguredPrefix(): void
    {
        $prefix = (new SmsVerificationCountryResolver())->matchPrefix(
            '+61 2 9374 4000',
            ['+44', '+91'],
        );

        $this->assertNull($prefix);
    }
}
