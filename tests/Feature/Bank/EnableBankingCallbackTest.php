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

namespace Tests\Feature\Bank;

use App\Http\Controllers\Bank\EnableBankingController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * Coverage for the OAuth callback (/enablebanking/confirm): invalid states and provider errors
 * must render the failure page instead of crashing.
 */
#[CoversClass(EnableBankingController::class)]
class EnableBankingCallbackTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        config([
            'ninja.environment' => 'selfhost',
            'ninja.enablebanking.application_id' => 'test-application-id',
            'ninja.enablebanking.key_path' => '/not/read/before/the/code/exchange',
        ]);
    }

    /**
     * Cache a context as connect() leaves it right before redirecting to the bank.
     */
    private function cacheContext(array $overrides = []): string
    {
        $token = Str::random(64);

        Cache::put($token, array_merge([
            'user_id' => $this->user->id,
            'company_key' => $this->company->company_key,
            'context' => 'enablebanking',
            'is_react' => false,
            'lang' => substr($this->company->locale(), 0, 2),
            'redirect' => config('ninja.app_url'),
            'state' => $token,
            'aspsp_name' => 'Test Bank',
            'aspsp_country' => 'BE',
        ], $overrides), 3600);

        return $token;
    }

    public function testUnknownStateRendersRefInvalid()
    {
        $this->get('/enablebanking/confirm?state=unknown-or-expired&code=abc')
            ->assertOk()
            ->assertViewHas('failed_reason', 'ref-invalid');
    }

    public function testMissingStateRendersRefInvalid()
    {
        $this->get('/enablebanking/confirm?code=abc')
            ->assertOk()
            ->assertViewHas('failed_reason', 'ref-invalid');
    }

    public function testForeignContextRendersRefInvalid()
    {
        $token = $this->cacheContext(['context' => 'nordigen']);

        $this->get("/enablebanking/confirm?state={$token}&code=abc")
            ->assertOk()
            ->assertViewHas('failed_reason', 'ref-invalid');
    }

    public function testContextWithoutStateRendersRefInvalid()
    {
        $token = $this->cacheContext();
        Cache::put($token, array_diff_key(Cache::get($token), ['state' => true]), 3600);

        $this->get("/enablebanking/confirm?state={$token}&code=abc")
            ->assertOk()
            ->assertViewHas('failed_reason', 'ref-invalid');
    }

    public function testProviderErrorWithoutCodeRendersAuthFailure()
    {
        $token = $this->cacheContext();

        $this->get("/enablebanking/confirm?state={$token}&error=access_denied&error_description=User+cancelled")
            ->assertOk()
            ->assertViewHas('failed_reason', 'auth-failure')
            ->assertViewHas('error_code', 'access_denied');
    }

    public function testMissingCodeRendersAuthCodeMissing()
    {
        $token = $this->cacheContext();

        $this->get("/enablebanking/confirm?state={$token}")
            ->assertOk()
            ->assertViewHas('failed_reason', 'auth-code-missing');
    }
}
