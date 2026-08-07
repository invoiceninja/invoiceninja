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

use Modules\Admin\Services\Spam\AnonymousMailProviderDetector;
use Tests\TestCase;

class AnonymousMailProviderDetectorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(AnonymousMailProviderDetector::class)) {
            $this->markTestSkipped('Modules\\Admin package is not available (hosted-only).');
        }
    }

    /**
     * @param list<string> $mxTargets
     * @param list<string> $signatures
     */
    private function detector(array $mxTargets, array $signatures = ['mail.tm', 'guerrillamail.com', 'temp-mail.org']): AnonymousMailProviderDetector
    {
        return new AnonymousMailProviderDetector(
            signatures: $signatures,
            mxResolver: fn (string $domain): array => array_map(
                fn (string $target): array => ['type' => 'MX', 'target' => $target, 'pri' => 10],
                $mxTargets,
            ),
        );
    }

    public function testFrontDomainIsMatchedViaBackendMx(): void
    {
        // web-library.net is a mail.tm front domain; the MX gives it away.
        $result = $this->detector(['in.mail.tm'])->inspectEmail('bob@web-library.net');

        $this->assertTrue($result->matched);
        $this->assertSame('mail.tm', $result->provider);
        $this->assertSame('in.mail.tm', $result->matchedHost);
        $this->assertSame('web-library.net', $result->domain);
    }

    public function testProviderMatchedOnFrontDomainWhenMxIsGeneric(): void
    {
        // temp-mail.org uses Cloudflare Email Routing — no MX fingerprint, caught on domain.
        $result = $this->detector(['amir.mx.cloudflare.net', 'linda.mx.cloudflare.net'])
            ->inspectEmail('bob@temp-mail.org');

        $this->assertTrue($result->matched);
        $this->assertSame('temp-mail.org', $result->provider);
        $this->assertSame('temp-mail.org', $result->matchedHost);
    }

    public function testLegitimateDomainIsNotMatched(): void
    {
        $result = $this->detector(['gmail-smtp-in.l.google.com', 'alt1.gmail-smtp-in.l.google.com'])
            ->inspectEmail('bob@gmail.com');

        $this->assertFalse($result->matched);
        $this->assertNull($result->provider);
    }

    public function testSignatureMatchingRespectsLabelBoundary(): void
    {
        // "notmail.tm" must NOT match the "mail.tm" signature.
        $result = $this->detector(['mx.notmail.tm'])->inspectEmail('bob@notmail.tm');

        $this->assertFalse($result->matched);
    }

    public function testSubdomainOfSignatureIsMatched(): void
    {
        $result = $this->detector(['deep.relay.mail.tm'])->inspectEmail('bob@somefront.io');

        $this->assertTrue($result->matched);
        $this->assertSame('mail.tm', $result->provider);
    }

    public function testTrailingDotInMxTargetIsNormalised(): void
    {
        $result = $this->detector(['in.mail.tm.'])->inspectEmail('bob@web-library.net');

        $this->assertTrue($result->matched);
        $this->assertSame('in.mail.tm', $result->matchedHost);
    }

    public function testMalformedEmailIsNotMatched(): void
    {
        $result = $this->detector([])->inspectEmail('not-an-email');

        $this->assertFalse($result->matched);
    }

    public function testDefaultSignatureListIsApplied(): void
    {
        $detector = new AnonymousMailProviderDetector(
            mxResolver: fn (string $domain): array => [['type' => 'MX', 'target' => 'in.mail.tm', 'pri' => 10]],
        );

        $this->assertTrue($detector->inspectEmail('bob@web-library.net')->matched);
    }
}
