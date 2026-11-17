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

namespace Tests\Feature\Account;

use App\Jobs\Account\CreateAccount;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class SignupAttributionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
        Bus::fake();

        config([
            'ninja.environment' => 'selfhost',
        ]);
    }

    public function testSignupStoresAttributionAndMirrorsLegacyColumns(): void
    {
        $account = $this->signup([
            'attribution' => [
                'utm_source'   => 'facebook',
                'utm_medium'   => 'cpc',
                'utm_campaign' => 'january_launch',
            ],
        ])->fresh();

        $this->assertSame([
            'utm_source'   => 'facebook',
            'utm_medium'   => 'cpc',
            'utm_campaign' => 'january_launch',
        ], $account->attribution);

        $this->assertSame('facebook', $account->utm_source);
        $this->assertSame('cpc', $account->utm_medium);
        $this->assertSame('january_launch', $account->utm_campaign);
    }

    public function testSignupWithoutParamsLeavesAttributionNull(): void
    {
        $account = $this->signup()->fresh();

        $this->assertNull($account->attribution);
        $this->assertNull($account->utm_source);
    }

    private function signup(array $overrides = [])
    {
        return (new CreateAccount($this->payload($overrides), '127.0.0.1'))->handle();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'email'                => 'signup-'.Str::lower(Str::random(12)).'@invoiceninja.com',
            'password'             => 'password123',
            'password_confirmation' => 'password123',
            'terms_of_service'     => true,
            'privacy_policy'       => true,
            'name'                 => 'Attribution Test',
        ], $overrides);
    }
}
