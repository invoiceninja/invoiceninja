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

class AccountAttributionCookieTest extends TestCase
{
    public function testExplicitAttributionWinsOverCookie(): void
    {
        $this->assertSame(
            ['utm_source' => 'direct'],
            CreateAccountRequest::attributionFrom(['utm_source' => 'direct'], json_encode(['utm_source' => 'cookie']))
        );
    }

    public function testInvalidAttributionIsLeftForValidationToReject(): void
    {
        $this->assertSame('junk', CreateAccountRequest::attributionFrom('junk', json_encode(['utm_source' => 'cookie'])));
    }

    public function testCookieIsUsedWhenNoAttributionIsPosted(): void
    {
        $this->assertSame(
            ['utm_source' => 'facebook', 'utm_medium' => 'cpc'],
            CreateAccountRequest::attributionFrom(null, json_encode(['utm_source' => 'facebook', 'utm_medium' => 'cpc']))
        );
    }

    public function testMissingOrInvalidCookieYieldsNull(): void
    {
        $this->assertNull(CreateAccountRequest::attributionFrom(null, null));
        $this->assertNull(CreateAccountRequest::attributionFrom(null, 'not json'));
        $this->assertNull(CreateAccountRequest::attributionFrom(null, json_encode([])));
    }

    public function testCookieKeysOutsideTheAllowlistAreDropped(): void
    {
        $this->assertSame(
            ['utm_source' => 'facebook'],
            CreateAccountRequest::attributionFrom(null, json_encode([
                'utm_source' => 'facebook',
                'fbclid'     => 'abc123',
                'rc'         => 'friend',
            ]))
        );
    }

    public function testCookieValuesAndKeysAreCapped(): void
    {
        $this->assertSame(
            ['utm_source' => str_repeat('a', 191)],
            CreateAccountRequest::attributionFrom(null, json_encode([
                'utm_source' => str_repeat('a', 191),
                'utm_medium' => str_repeat('a', 192),
                'utm_'.str_repeat('a', 60) => 'value',
                'utm_campaign' => ['nested'],
            ]))
        );
    }

    public function testCookieIsCappedAtTwentyKeys(): void
    {
        $cookie = [];

        for ($i = 0; $i < 25; $i++) {
            $cookie['utm_key'.$i] = 'value';
        }

        $this->assertCount(20, CreateAccountRequest::attributionFrom(null, json_encode($cookie)));
    }
}
