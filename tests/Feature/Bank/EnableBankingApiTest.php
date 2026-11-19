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

use App\Helpers\Bank\EnableBanking\EnableBanking;
use App\Models\BankIntegration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * Coverage for the EnableBanking API helper with a faked HTTP layer.
 *
 * A throwaway RSA key is generated per test, so no EnableBanking credentials are required.
 */
#[CoversClass(EnableBanking::class)]
class EnableBankingApiTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    private string $key_path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);

        $this->key_path = tempnam(sys_get_temp_dir(), 'enablebanking');
        file_put_contents($this->key_path, $pem);

        config([
            'ninja.enablebanking.application_id' => 'test-application-id',
            'ninja.enablebanking.key_path' => $this->key_path,
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->key_path);

        parent::tearDown();
    }

    private function transaction(string $reference): array
    {
        return [
            'entry_reference' => $reference,
            'transaction_amount' => ['currency' => 'EUR', 'amount' => '10.00'],
            'credit_debit_indicator' => 'CRDT',
            'status' => 'BOOK',
            'booking_date' => '2026-01-01',
        ];
    }

    public function testGetTransactionsFollowsContinuationKey()
    {
        Http::fakeSequence('api.enablebanking.com/accounts/*')
            ->push(['transactions' => [$this->transaction('t1'), $this->transaction('t2')], 'continuation_key' => 'page-2'])
            ->push(['transactions' => [], 'continuation_key' => 'page-3']) // empty page with a key
            ->push(['transactions' => [$this->transaction('t3')], 'continuation_key' => null]);

        $transactions = (new EnableBanking())->getTransactions($this->company, 'account-uid', '2026-01-01 10:00:00');

        $this->assertEquals(['t1', 't2', 't3'], array_column($transactions, 'nordigen_transaction_id'));

        $recorded = Http::recorded();
        $this->assertCount(3, $recorded);
        $this->assertEquals('2026-01-01', $recorded[0][0]->data()['date_from']);
        $this->assertArrayNotHasKey('continuation_key', $recorded[0][0]->data());
        $this->assertEquals('page-2', $recorded[1][0]->data()['continuation_key']);
        $this->assertEquals('page-3', $recorded[2][0]->data()['continuation_key']);
    }

    public function testGetTransactionsStopsOnRepeatedContinuationKey()
    {
        Http::fake([
            'api.enablebanking.com/accounts/*' => Http::response([
                'transactions' => [$this->transaction('t1')],
                'continuation_key' => 'same-key',
            ]),
        ]);

        $transactions = (new EnableBanking())->getTransactions($this->company, 'account-uid');

        // first page + one request with the (repeated) key, then stop
        Http::assertSentCount(2);
        $this->assertCount(2, $transactions);
    }

    public function testGetTransactionsThrowsOnApiError()
    {
        Http::fake([
            'api.enablebanking.com/accounts/*' => Http::response(['error' => 'ACCOUNT_NOT_ACCESSIBLE'], 403),
        ]);

        $this->expectException(\Exception::class);

        (new EnableBanking())->getTransactions($this->company, 'account-uid');
    }

    private function fakeAccountDetails(): void
    {
        Http::fake([
            'api.enablebanking.com/accounts/acc-uid/details' => Http::response([
                'uid' => 'acc-uid',
                'account_id' => ['iban' => 'BE68539007547034'],
                'name' => 'Business account',
                'identification_hash' => 'hash-1',
            ]),
            'api.enablebanking.com/accounts/acc-uid/balances' => Http::response([
                'balances' => [
                    ['balance_type' => 'CLBD', 'balance_amount' => ['amount' => '1234.56', 'currency' => 'EUR']],
                ],
            ]),
        ]);
    }

    public function testGetAccountReturnsASingleAccount()
    {
        $this->fakeAccountDetails();

        $account = (new EnableBanking())->getAccount('acc-uid');

        $this->assertEquals('acc-uid', $account['provider_account_id']);
        $this->assertEquals('1234.56', $account['current_balance']);
        $this->assertEquals('EUR', $account['account_currency']);
    }

    public function testGetAccountReportsRateLimit()
    {
        Http::fake(['api.enablebanking.com/*' => Http::response(['error' => 'TOO_MANY_REQUESTS'], 429)]);

        $account = (new EnableBanking())->getAccount('acc-uid');

        $this->assertEquals(429, $account['code']);
    }

    public function testGetAccountReportsApiError()
    {
        Http::fake(['api.enablebanking.com/*' => Http::response(['error' => 'ACCOUNT_NOT_ACCESSIBLE'], 403)]);

        $account = (new EnableBanking())->getAccount('acc-uid');

        $this->assertEquals(403, $account['code']);
        $this->assertArrayHasKey('error', $account);
    }

    private function enableBankingIntegration(array $overrides = []): BankIntegration
    {
        $this->bank_integration->forceFill(array_merge([
            'integration_type' => BankIntegration::INTEGRATION_TYPE_ENABLEBANKING,
            'nordigen_account_id' => 'acc-uid',
            'requisition_id' => 'session-1',
            'disabled_upstream' => false,
            'auto_sync' => true,
            'bank_account_status' => 'READY',
            'balance' => 0,
        ], $overrides))->save();

        return $this->bank_integration->refresh();
    }

    private function refreshAccounts()
    {
        Cache::forget("throttle_polling:{$this->account->key}");

        return $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/bank_integrations/refresh_accounts');
    }

    public function testRefreshAccountsUpdatesTheBalance()
    {
        $this->fakeAccountDetails();
        $bank_integration = $this->enableBankingIntegration();

        $this->refreshAccounts()->assertStatus(200);

        $bank_integration->refresh();
        $this->assertEquals(1234.56, (float) $bank_integration->balance);
        // the status is owned by the sync job, not by the account details
        $this->assertEquals('READY', $bank_integration->bank_account_status);
    }

    public function testRefreshAccountsLeavesIntegrationUntouchedWhenRateLimited()
    {
        Http::fake(['api.enablebanking.com/*' => Http::response(['error' => 'TOO_MANY_REQUESTS'], 429)]);
        $bank_integration = $this->enableBankingIntegration(['balance' => 10]);

        $this->refreshAccounts()->assertStatus(200);

        $bank_integration->refresh();
        $this->assertEquals(10, (float) $bank_integration->balance);
        $this->assertEquals('READY', $bank_integration->bank_account_status);
        $this->assertFalse((bool) $bank_integration->disabled_upstream);
    }
}
