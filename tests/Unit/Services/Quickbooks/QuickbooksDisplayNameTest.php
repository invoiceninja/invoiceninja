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

namespace Tests\Unit\Services\Quickbooks;

use App\Services\Quickbooks\QuickbooksDisplayName;
use PHPUnit\Framework\TestCase;

class QuickbooksDisplayNameTest extends TestCase
{
    public function testSanitizeReplacesQuickbooksIllegalCharacters(): void
    {
        $this->assertSame(
            'Regencium- Physical Therapy and Performance - West Greater Houston',
            QuickbooksDisplayName::sanitize('Regencium: Physical Therapy and Performance - West Greater Houston')
        );
        $this->assertSame('Smiths and OMalleys LLC', QuickbooksDisplayName::sanitize("Smith's & O'Malley's LLC"));
        $this->assertSame('Acme Co', QuickbooksDisplayName::sanitize('Acme <Co>'));
        $this->assertSame('North South', QuickbooksDisplayName::sanitize('North/South'));
    }

    public function testUniqueSuffixUsesSanitizedBase(): void
    {
        $this->assertSame(
            'Regencium- Physical Therapy and Performance - West Greater Houston (C)',
            QuickbooksDisplayName::unique('Regencium: Physical Therapy and Performance - West Greater Houston')
        );
        $this->assertSame('SORCA (C)', QuickbooksDisplayName::unique('SORCA'));
    }

    public function testConservativeRetryStripsRemainingPunctuation(): void
    {
        $this->assertSame(
            'Regencium Physical Therapy and Performance West Greater Houston',
            QuickbooksDisplayName::conservative('Regencium: Physical Therapy and Performance - West Greater Houston')
        );
    }

    public function testEmptyNameFallsBackToCustomer(): void
    {
        $this->assertSame('Customer', QuickbooksDisplayName::sanitize(':::""'));
        $this->assertSame('Customer', QuickbooksDisplayName::conservative('***'));
    }
}
