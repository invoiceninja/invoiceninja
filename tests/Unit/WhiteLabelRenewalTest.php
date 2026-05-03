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

use App\Models\Account;
use App\Services\License\WhiteLabelRenewalService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\MockAccountData;
use Tests\TestCase;

class WhiteLabelRenewalTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    private WhiteLabelRenewalService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTestData();
        $this->service = new WhiteLabelRenewalService();
    }

    public function testRenewedLicenseUpdatesAccount(): void
    {
        config(['ninja.license_key' => 'v5_test_license_key']);

        $account = $this->account;
        $account->plan = Account::PLAN_WHITE_LABEL;
        $account->plan_expires = now()->subDay()->format('Y-m-d');
        $account->save();

        $futureDate = now()->addYear()->format('Y-m-d');

        Http::fake([
            '*/claim_license*' => Http::response(['expires' => $futureDate], 200),
        ]);

        $result = $this->service->checkAndRenew($account);

        $this->assertTrue($result);

        $account->refresh();
        $this->assertEquals(Account::PLAN_WHITE_LABEL, $account->plan);
        $this->assertEquals($futureDate, $account->plan_expires);
        $this->assertEquals(Account::PLAN_TERM_YEARLY, $account->plan_term);
    }

    public function testNotRenewedLicenseReturnsFalse(): void
    {
        config(['ninja.license_key' => 'v5_test_license_key']);

        $account = $this->account;
        $account->plan = Account::PLAN_WHITE_LABEL;
        $account->plan_expires = now()->subDay()->format('Y-m-d');
        $account->save();

        Http::fake([
            '*/claim_license*' => Http::response(['message' => 'Invalid license'], 400),
        ]);

        $result = $this->service->checkAndRenew($account);

        $this->assertFalse($result);
    }

    public function testNetworkErrorReturnsNull(): void
    {
        config(['ninja.license_key' => 'v5_test_license_key']);

        $account = $this->account;
        $account->plan = Account::PLAN_WHITE_LABEL;
        $account->plan_expires = now()->subDay()->format('Y-m-d');
        $account->save();

        Http::fake([
            '*/claim_license*' => fn () => throw new ConnectionException('Connection timed out'),
        ]);

        $result = $this->service->checkAndRenew($account);

        $this->assertNull($result);

        $account->refresh();
        $this->assertEquals(Account::PLAN_WHITE_LABEL, $account->plan);
    }

    public function testNoLicenseKeyReturnsFalse(): void
    {
        config(['ninja.license_key' => false]);

        $account = $this->account;
        $account->plan = Account::PLAN_WHITE_LABEL;
        $account->plan_expires = now()->subDay()->format('Y-m-d');
        $account->save();

        Http::fake();

        $result = $this->service->checkAndRenew($account);

        $this->assertFalse($result);

        Http::assertNothingSent();
    }

    public function testExpiredRenewalDateReturnsFalse(): void
    {
        config(['ninja.license_key' => 'v5_test_license_key']);

        $account = $this->account;
        $account->plan = Account::PLAN_WHITE_LABEL;
        $account->plan_expires = now()->subDay()->format('Y-m-d');
        $account->save();

        $pastDate = now()->subMonth()->format('Y-m-d');

        Http::fake([
            '*/claim_license*' => Http::response(['expires' => $pastDate], 200),
        ]);

        $result = $this->service->checkAndRenew($account);

        $this->assertFalse($result);

        $account->refresh();
        $this->assertEquals(Account::PLAN_WHITE_LABEL, $account->plan);
    }
}
