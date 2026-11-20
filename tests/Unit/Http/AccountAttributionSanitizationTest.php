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

namespace Tests\Unit\Http;

use App\Http\Requests\Account\CreateAccountRequest;
use Tests\TestCase;

class AccountAttributionSanitizationTest extends TestCase
{
    public function testUtmKeysAreKept(): void
    {
        $this->assertSame(
            [
                'utm_source'   => 'facebook',
                'utm_medium'   => 'cpc',
                'utm_campaign' => 'january_launch',
                'utm_id'       => '123',
            ],
            CreateAccountRequest::sanitizeAttribution([
                'utm_source'   => 'facebook',
                'utm_medium'   => 'cpc',
                'utm_campaign' => 'january_launch',
                'utm_id'       => '123',
            ])
        );
    }

    public function testClickIdsAreKept(): void
    {
        $this->assertSame(
            [
                'gclid'  => 'a',
                'gbraid' => 'b',
                'wbraid' => 'c',
                'fbclid' => 'd',
                'fbc'    => 'e',
                'fbp'    => 'f',
                'twclid' => 'g',
                'oppref' => 'h',
                'obref'  => 'i',
            ],
            CreateAccountRequest::sanitizeAttribution([
                'gclid'  => 'a',
                'gbraid' => 'b',
                'wbraid' => 'c',
                'fbclid' => 'd',
                'fbc'    => 'e',
                'fbp'    => 'f',
                'twclid' => 'g',
                'oppref' => 'h',
                'obref'  => 'i',
            ])
        );
    }

    public function testAttributionIsOptional(): void
    {
        $this->assertNull(CreateAccountRequest::sanitizeAttribution(null));
        $this->assertNull(CreateAccountRequest::sanitizeAttribution([]));
        $this->assertNull(CreateAccountRequest::sanitizeAttribution('junk'));
    }

    public function testUnknownKeysAreDroppedInsteadOfRejected(): void
    {
        $this->assertSame(
            ['utm_source' => 'facebook'],
            CreateAccountRequest::sanitizeAttribution([
                'utm_source' => 'facebook',
                'ref'        => 'newsletter',
                'rc'         => 'friend',
                'email'      => 'user@example.com',
            ])
        );
    }

    public function testMoreThanTwentyKeysAreCapped(): void
    {
        $keys = [];

        for ($i = 0; $i < 25; $i++) {
            $keys['utm_key'.$i] = 'value';
        }

        $this->assertCount(20, CreateAccountRequest::sanitizeAttribution($keys));
    }

    public function testValueLongerThanColumnIsDropped(): void
    {
        $this->assertSame(
            ['utm_source' => str_repeat('a', 191)],
            CreateAccountRequest::sanitizeAttribution([
                'utm_source' => str_repeat('a', 191),
                'utm_medium' => str_repeat('a', 192),
            ])
        );
    }

    public function testKeyLongerThanSixtyThreeCharactersIsDropped(): void
    {
        $this->assertSame(
            ['utm_'.str_repeat('a', 59) => 'value'],
            CreateAccountRequest::sanitizeAttribution([
                'utm_'.str_repeat('a', 59) => 'value',
                'utm_'.str_repeat('a', 60) => 'value',
            ])
        );
    }

    public function testNonStringValueIsDropped(): void
    {
        $this->assertNull(CreateAccountRequest::sanitizeAttribution([
            'utm_source' => ['nested'],
        ]));
    }
}
