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

use Modules\Admin\Http\ValidationRules\AnonymousMailProviderRule;
use Modules\Admin\Services\Spam\AnonymousMailProviderDetector;
use Tests\TestCase;

class AnonymousMailProviderRuleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(AnonymousMailProviderRule::class)) {
            $this->markTestSkipped('Modules\\Admin package is not available (hosted-only).');
        }
    }

    /**
     * @param list<string> $mxTargets
     */
    private function ruleWithMx(array $mxTargets): AnonymousMailProviderRule
    {
        $detector = new AnonymousMailProviderDetector(
            mxResolver: fn (string $domain): array => array_map(
                fn (string $target): array => ['type' => 'MX', 'target' => $target, 'pri' => 10],
                $mxTargets,
            ),
        );

        return new AnonymousMailProviderRule($detector);
    }

    public function testDisposableDomainFailsValidation(): void
    {
        $failed = false;

        $this->ruleWithMx(['in.mail.tm'])->validate('email', 'bob@web-library.net', function () use (&$failed) {
            $failed = true;
        });

        $this->assertTrue($failed, 'A mail.tm-backed domain should be rejected.');
    }

    public function testLegitimateDomainPassesValidation(): void
    {
        $failed = false;

        $this->ruleWithMx(['gmail-smtp-in.l.google.com'])->validate('email', 'bob@gmail.com', function () use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed, 'A legitimate domain should pass.');
    }

    public function testNonStringValueIsIgnored(): void
    {
        $failed = false;

        $this->ruleWithMx([])->validate('email', null, function () use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed);
    }
}
