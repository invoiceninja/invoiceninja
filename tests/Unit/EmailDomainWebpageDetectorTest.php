<?php

namespace Tests\Unit;

use Modules\Admin\Services\Spam\EmailDomainWebpageDetector;
use PHPUnit\Framework\TestCase;

class EmailDomainWebpageDetectorTest extends TestCase
{


    protected function setUp(): void
    {
        parent::setUp();

        if (
            ! class_exists(\Modules\Admin\Services\Spam\EmailDomainWebpageDetector::class)
        ) {
            $this->markTestSkipped('Admin module email domain webpage detector is not installed.');
        }
    }


    public function testItMatchesAnARecord(): void
    {
        $detector = new EmailDomainWebpageDetector(static fn (string $domain): array => [
            ['host' => $domain, 'type' => 'A', 'ip' => '93.184.216.34'],
        ]);

        $this->assertTrue($detector->inspect('example.com'));
    }

    public function testItMatchesAnAaaaRecord(): void
    {
        $detector = new EmailDomainWebpageDetector(static fn (string $domain): array => [
            ['host' => $domain, 'type' => 'AAAA', 'ipv6' => '2606:2800:220:1:248:1893:25c8:1946'],
        ]);

        $this->assertTrue($detector->inspect('example.com'));
    }

    public function testItDoesNotMatchWithoutAnAddressRecord(): void
    {
        $detector = new EmailDomainWebpageDetector(static fn (string $domain): array => [
            ['host' => $domain, 'type' => 'MX', 'target' => 'mail.example.com'],
        ]);

        $this->assertFalse($detector->inspect('example.com'));
    }

    public function testItDoesNotMatchWhenDnsResolutionFails(): void
    {
        $detector = new EmailDomainWebpageDetector(static fn (string $domain): false => false);

        $this->assertFalse($detector->inspect('example.com'));
    }

    public function testItRejectsAnInvalidDomain(): void
    {
        $detector = new EmailDomainWebpageDetector(
            static function (string $domain): array {
                self::fail('DNS should not be queried for an invalid domain.');
            },
        );

        $this->assertFalse($detector->inspect('not a domain'));
    }
}
