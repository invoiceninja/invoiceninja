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
use ReflectionClass;
use Tests\TestCase;
use App\Models\Company;
use App\DataMapper\QuickbooksSettings;
use App\Services\Quickbooks\QuickbooksService;
use QuickBooksOnline\API\DataService\DataService;

class QuickbooksServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Build a Company with quickbooks settings so QuickbooksService::init() can run.
     * Uses future expiry so checkToken() does not try to refresh when SDK is configured.
     */
    private function companyWithQuickbooks(): Company
    {
        $company = Company::factory()->make();
        $company->quickbooks = new QuickbooksSettings([
            'accessTokenKey' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'realmID' => 'test-realm',
            'accessTokenExpiresAt' => time() + 3600,
            'refreshTokenExpiresAt' => time() + 86400,
            'baseURL' => 'https://sandbox-quickbooks.api.intuit.com',
            'companyName' => 'Test Company',
            'settings' => [],
        ]);

        return $company;
    }

    public function test_is_token_valid_returns_false_when_sdk_not_configured(): void
    {
        $this->app['config']->set('services.quickbooks.client_id', null);

        $company = $this->companyWithQuickbooks();
        $service = new QuickbooksService($company);

        $this->assertFalse($service->isTokenValid());
    }

    public function test_is_token_valid_can_be_stubbed_to_avoid_real_api_call(): void
    {
        $this->app['config']->set('services.quickbooks.client_id', null);

        $company = $this->companyWithQuickbooks();
        $service = Mockery::mock(QuickbooksService::class, [$company])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $service->shouldReceive('isTokenValid')
            ->once()
            ->andReturn(true);

        $this->assertTrue($service->isTokenValid());
    }

    public function test_is_token_valid_returns_true_when_sdk_query_succeeds(): void
    {
        $this->app['config']->set('services.quickbooks.client_id', null);

        $company = $this->companyWithQuickbooks();
        $service = new QuickbooksService($company);

        $mockSdk = Mockery::mock(DataService::class)->makePartial();
        $mockSdk->shouldReceive('Query')
            ->once()
            ->with('SELECT Id FROM CompanyInfo MAXRESULTS 1')
            ->andReturn([]);

        $ref = new ReflectionClass($service);
        $prop = $ref->getProperty('sdk');
        $prop->setAccessible(true);
        $prop->setValue($service, $mockSdk);

        $this->assertTrue($service->isTokenValid());
    }

    public function test_is_token_valid_returns_false_when_sdk_query_throws(): void
    {
        $this->app['config']->set('services.quickbooks.client_id', null);

        $company = $this->companyWithQuickbooks();
        $service = new QuickbooksService($company);

        $mockSdk = Mockery::mock(DataService::class)->makePartial();
        $mockSdk->shouldReceive('Query')
            ->once()
            ->with('SELECT Id FROM CompanyInfo MAXRESULTS 1')
            ->andThrow(new \Exception('Unauthorized'));

        $ref = new ReflectionClass($service);
        $prop = $ref->getProperty('sdk');
        $prop->setAccessible(true);
        $prop->setValue($service, $mockSdk);

        $this->assertFalse($service->isTokenValid());
    }
}
