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

namespace Tests\Feature;

use Tests\TestCase;

/**
 * What a web client on another origin may send and read. The Flutter web app
 * sends X-API-OAUTH-PASSWORD when an OAuth user confirms with their provider
 * (PasswordProtection), and reads Retry-After to back off a 429.
 */
class CorsHeadersTest extends TestCase
{
    public function testThePreflightAllowsTheOAuthPasswordHeader()
    {
        $allowed = $this->call('OPTIONS', '/api/v1/ping')
            ->assertOk()
            ->headers->get('Access-Control-Allow-Headers');

        $this->assertContains('X-API-OAUTH-PASSWORD', explode(',', $allowed));
    }

    public function testAResponseAllowsItAndExposesRetryAfter()
    {
        $response = $this->get('/api/v1/ping');

        $this->assertContains('X-API-OAUTH-PASSWORD', explode(',', $response->headers->get('Access-Control-Allow-Headers')));
        $this->assertContains('Retry-After', explode(',', $response->headers->get('Access-Control-Expose-Headers')));
    }
}
