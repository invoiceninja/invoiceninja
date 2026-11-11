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

use Mockery;
use Tests\TestCase;
use Tests\MockAccountData;
use App\DataMapper\QuickbooksSettings;
use App\Services\Quickbooks\SdkWrapper;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use QuickBooksOnline\API\DataService\DataService;
use QuickBooksOnline\API\Exception\ServiceException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use QuickBooksOnline\API\Core\OAuth\OAuth2\OAuth2LoginHelper;
use QuickBooksOnline\API\Core\OAuth\OAuth2\OAuth2AccessToken;

class SdkWrapperTokenRefreshTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        config(['services.quickbooks.client_id' => 'test-client-id']);
        config(['services.quickbooks.client_secret' => 'test-client-secret']);

        Cache::flush();

        $this->makeTestData();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_refresh_token_lock_key_is_scoped_by_company_id_and_database(): void
    {
        $this->company->db = 'db-ninja-02';
        $this->company->save();

        $this->configureQuickbooks(accessTokenExpiresAt: time() + 3600);

        $lock = Mockery::mock(Lock::class);
        $lock->shouldReceive('block')
            ->once()
            ->with(10, Mockery::type(\Closure::class))
            ->andReturnNull();

        Cache::shouldReceive('lock')
            ->once()
            ->with("quickbooks-token-refresh:{$this->company->id}:db-ninja-02", 30)
            ->andReturn($lock);

        $sdk = Mockery::mock(DataService::class)->makePartial();
        $wrapper = new SdkWrapper($sdk, $this->company);

        $wrapper->refreshTokenLocked();

        $this->addToAssertionCount(1);
    }

    public function test_query_refreshes_nearly_expired_token_before_calling_quickbooks(): void
    {
        $this->configureQuickbooks(accessTokenExpiresAt: time() + 60);

        $newToken = $this->makeToken('new-access-token', 'new-refresh-token');
        $loginHelper = Mockery::mock(OAuth2LoginHelper::class);
        $loginHelper->shouldReceive('refreshAccessTokenWithRefreshToken')
            ->once()
            ->with('test-refresh-token')
            ->andReturn($newToken);

        $sdk = Mockery::mock(DataService::class)->makePartial();
        $sdk->shouldReceive('getOAuth2LoginHelper')
            ->once()
            ->andReturn($loginHelper);
        $sdk->shouldReceive('updateOAuth2Token')
            ->once()
            ->with(Mockery::on(fn (OAuth2AccessToken $token): bool => $token->getAccessToken() === 'new-access-token'
                && $token->getRefreshToken() === 'new-refresh-token'
                && $token->getRealmID() === 'test-realm'))
            ->andReturnSelf();
        $sdk->shouldReceive('Query')
            ->once()
            ->with('SELECT * FROM Customer')
            ->andReturn([(object) ['Id' => '1']]);

        $wrapper = new SdkWrapper($sdk, $this->company);

        $records = $wrapper->query('SELECT * FROM Customer');

        $this->assertCount(1, $records);

        $quickbooks = $this->company->fresh()->quickbooks;
        $this->assertSame('new-access-token', $quickbooks->accessTokenKey);
        $this->assertSame('new-refresh-token', $quickbooks->refresh_token);
        $this->assertSame('test-realm', $quickbooks->realmID);
        $this->assertSame('https://sandbox-quickbooks.api.intuit.com', $quickbooks->baseURL);
    }

    public function test_query_refreshes_token_and_retries_once_after_401(): void
    {
        $this->configureQuickbooks(accessTokenExpiresAt: time() + 3600);

        $newToken = $this->makeToken('retry-access-token', 'retry-refresh-token');
        $loginHelper = Mockery::mock(OAuth2LoginHelper::class);
        $loginHelper->shouldReceive('refreshAccessTokenWithRefreshToken')
            ->once()
            ->with('test-refresh-token')
            ->andReturn($newToken);

        $sdk = Mockery::mock(DataService::class)->makePartial();
        $sdk->shouldReceive('Query')
            ->once()
            ->with('SELECT * FROM Invoice')
            ->andThrow(new ServiceException('Request is not made successful. Response Code:[401]', 401));
        $sdk->shouldReceive('getOAuth2LoginHelper')
            ->once()
            ->andReturn($loginHelper);
        $sdk->shouldReceive('updateOAuth2Token')
            ->once()
            ->with(Mockery::on(fn (OAuth2AccessToken $token): bool => $token->getAccessToken() === 'retry-access-token'))
            ->andReturnSelf();
        $sdk->shouldReceive('Query')
            ->once()
            ->with('SELECT * FROM Invoice')
            ->andReturn([(object) ['Id' => '10']]);

        $wrapper = new SdkWrapper($sdk, $this->company);

        $records = $wrapper->query('SELECT * FROM Invoice');

        $this->assertCount(1, $records);
        $this->assertSame('retry-access-token', $this->company->fresh()->quickbooks->accessTokenKey);
    }

    public function test_fetch_records_page_uses_start_position_and_page_size(): void
    {
        $this->configureQuickbooks(accessTokenExpiresAt: time() + 3600);

        $sdk = Mockery::mock(DataService::class)->makePartial();
        $sdk->shouldReceive('Query')
            ->once()
            ->with('select * from Customer', 1001, 1000)
            ->andReturn((object) ['Id' => '42']);

        $wrapper = new SdkWrapper($sdk, $this->company);

        $records = $wrapper->fetchRecordsPage('Customer', 1001, 5000);

        $this->assertCount(1, $records);
        $this->assertSame('42', $records[0]->Id);
    }

    private function configureQuickbooks(int $accessTokenExpiresAt): void
    {
        $this->company->quickbooks = new QuickbooksSettings([
            'accessTokenKey' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'realmID' => 'test-realm',
            'accessTokenExpiresAt' => $accessTokenExpiresAt,
            'refreshTokenExpiresAt' => time() + 86400,
            'baseURL' => 'https://sandbox-quickbooks.api.intuit.com',
            'companyName' => 'Test Company',
            'settings' => [],
        ]);
        $this->company->save();
    }

    private function makeToken(string $accessToken, string $refreshToken): OAuth2AccessToken
    {
        $token = new OAuth2AccessToken(
            'test-client-id',
            'test-client-secret',
            $accessToken,
            $refreshToken,
            3600,
            8726400
        );
        $token->setAccessTokenExpiresAt(time() + 3600);
        $token->setRefreshTokenExpiresAt(time() + 8726400);
        $token->setRealmID('test-realm');
        $token->setBaseURL('https://sandbox-quickbooks.api.intuit.com');

        return $token;
    }
}
