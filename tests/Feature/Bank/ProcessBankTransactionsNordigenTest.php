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

use App\Helpers\Bank\Nordigen\Nordigen;
use App\Jobs\Bank\ProcessBankTransactionsNordigen;
use App\Models\BankIntegration;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Nordigen\NordigenPHP\API\NordigenClient as NordigenApiClient;
use ReflectionClass;
use Tests\MockAccountData;
use Tests\TestCase;

class ProcessBankTransactionsNordigenTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        Model::reguard();

        config(['ninja.nordigen.secret_id' => 'test-id', 'ninja.nordigen.secret_key' => 'test-key']);

        Bus::fake();

        foreach (['last', 'attempts', 'paused'] as $scope) {
            Cache::forget("nordigen:wake:{$scope}:acc-1");
        }

        Cache::lock('nordigen:wake:lock:acc-1', 1)->forceRelease();
    }

    private function nordigenIntegration(array $overrides = []): BankIntegration
    {
        $this->bank_integration->forceFill(array_merge([
            'integration_type' => BankIntegration::INTEGRATION_TYPE_NORDIGEN,
            'nordigen_account_id' => 'acc-1',
            'requisition_id' => 'req-1',
            'disabled_upstream' => false,
            'bank_account_status' => 'READY',
        ], $overrides))->save();

        return $this->bank_integration->refresh();
    }

    private function runJob(BankIntegration $bank_integration, Nordigen $nordigen): void
    {
        $job = new ProcessBankTransactionsNordigen($bank_integration);
        $job->nordigen = $nordigen;
        $job->handle();
    }

    /**
     * @param array<int, Response> $responses
     * @param array<int, array<string, mixed>> $history
     */
    private function nordigenHelperWithResponses(array $responses, array &$history): Nordigen
    {
        $mock = new MockHandler($responses);
        $history = [];
        $handler_stack = HandlerStack::create($mock);
        $handler_stack->push(Middleware::history($history));

        $api_client = new NordigenApiClient('test-id', 'test-key', new Client([
            'handler' => $handler_stack,
            'base_uri' => NordigenApiClient::BASE_URL,
        ]));

        $reflection = new ReflectionClass(Nordigen::class);
        $nordigen = $reflection->newInstanceWithoutConstructor();
        $client_property = $reflection->getProperty('client');
        $client_property->setAccessible(true);
        $client_property->setValue($nordigen, $api_client);

        return $nordigen;
    }

    public function testInvalidRequisitionDisablesAndEmails()
    {
        $bi = $this->nordigenIntegration();

        $nordigen = Mockery::mock(Nordigen::class);
        $nordigen->shouldReceive('requisitionStatus')->once()->with('req-1')->andReturn('EX');
        $nordigen->shouldReceive('disabledAccountEmail')->once();
        $nordigen->shouldNotReceive('isAccountActive');
        $nordigen->shouldNotReceive('getTransactions');

        $this->runJob($bi, $nordigen);

        $bi->refresh();
        $this->assertTrue((bool) $bi->disabled_upstream);
        $this->assertEquals('EX', $bi->bank_account_status);
    }

    public function testTransientRequisitionFetchFailureDoesNotDisable()
    {
        // requisitionStatus() returns null when the requisition endpoint can't be read
        // (404/429/5xx/timeout). That must NOT disable — it falls through to the account check.
        $bi = $this->nordigenIntegration();

        $nordigen = Mockery::mock(Nordigen::class);
        $nordigen->shouldReceive('requisitionStatus')->once()->with('req-1')->andReturnNull();
        $nordigen->shouldReceive('isAccountActive')->once()->with('acc-1')->andReturn(['status' => 'READY']);
        $nordigen->shouldReceive('getTransactions')->andReturn([]);
        $nordigen->shouldNotReceive('disabledAccountEmail');

        $this->runJob($bi, $nordigen);

        $bi->refresh();
        $this->assertFalse((bool) $bi->disabled_upstream);
        $this->assertEquals('READY', $bi->bank_account_status);
    }

    public function testLinkedRequisitionAndReadyAccountProcesses()
    {
        $bi = $this->nordigenIntegration(['disabled_upstream' => true, 'bank_account_status' => 'EX']);

        $nordigen = Mockery::mock(Nordigen::class);
        $nordigen->shouldReceive('requisitionStatus')->once()->with('req-1')->andReturn('LN');
        $nordigen->shouldReceive('isAccountActive')->once()->with('acc-1')->andReturn(['status' => 'READY']);
        $nordigen->shouldReceive('getTransactions')->andReturn([]);
        $nordigen->shouldNotReceive('disabledAccountEmail');

        $this->runJob($bi, $nordigen);

        $bi->refresh();
        $this->assertFalse((bool) $bi->disabled_upstream);
        $this->assertEquals('READY', $bi->bank_account_status);
    }

    public function testRateLimitedAccountLeavesIntegrationUntouched()
    {
        $bi = $this->nordigenIntegration();

        $nordigen = Mockery::mock(Nordigen::class);
        $nordigen->shouldReceive('requisitionStatus')->once()->with('req-1')->andReturn('LN');
        $nordigen->shouldReceive('isAccountActive')->once()->with('acc-1')->andReturn(['status' => 'RATE_LIMITED', 'code' => 429]);
        $nordigen->shouldNotReceive('disabledAccountEmail');
        $nordigen->shouldNotReceive('getTransactions');

        $this->runJob($bi, $nordigen);

        $bi->refresh();
        $this->assertFalse((bool) $bi->disabled_upstream);
        $this->assertEquals('READY', $bi->bank_account_status);
    }

    public function testTransientAccountErrorDoesNotDisable()
    {
        $bi = $this->nordigenIntegration();

        $nordigen = Mockery::mock(Nordigen::class);
        $nordigen->shouldReceive('requisitionStatus')->once()->with('req-1')->andReturn('LN');
        $nordigen->shouldReceive('isAccountActive')->once()->with('acc-1')->andReturn(['status' => 'TRANSIENT_ERROR', 'error' => 'upstream 500']);
        $nordigen->shouldNotReceive('disabledAccountEmail');
        $nordigen->shouldNotReceive('getTransactions');

        $this->runJob($bi, $nordigen);

        $bi->refresh();
        $this->assertFalse((bool) $bi->disabled_upstream);
    }

    public function testErrorAccountAttemptsWakeAndDoesNotProcessTransactions()
    {
        $bi = $this->nordigenIntegration();

        $nordigen = Mockery::mock(Nordigen::class);
        $nordigen->shouldReceive('requisitionStatus')->once()->with('req-1')->andReturn('LN');
        $nordigen->shouldReceive('isAccountActive')->once()->with('acc-1')->andReturn(['status' => 'ERROR']);
        $nordigen->shouldReceive('wakeAccount')->once()->with('acc-1', 'ERROR')->andReturn(['status' => 'WAKE_PROBED']);
        $nordigen->shouldNotReceive('disabledAccountEmail');
        $nordigen->shouldNotReceive('getTransactions');

        $this->runJob($bi, $nordigen);

        $bi->refresh();
        $this->assertFalse((bool) $bi->disabled_upstream);
        $this->assertEquals('ERROR', $bi->bank_account_status);
        $this->assertTrue(Cache::has('nordigen:wake:last:acc-1'));
        $this->assertEquals(1, Cache::get('nordigen:wake:attempts:acc-1'));
    }

    public function testErrorAccountSkipsWakeDuringCooldown()
    {
        $bi = $this->nordigenIntegration();
        Cache::put('nordigen:wake:last:acc-1', true, 60 * 60 * 6);

        $nordigen = Mockery::mock(Nordigen::class);
        $nordigen->shouldReceive('requisitionStatus')->once()->with('req-1')->andReturn('LN');
        $nordigen->shouldReceive('isAccountActive')->once()->with('acc-1')->andReturn(['status' => 'ERROR']);
        $nordigen->shouldNotReceive('wakeAccount');
        $nordigen->shouldNotReceive('disabledAccountEmail');
        $nordigen->shouldNotReceive('getTransactions');

        $this->runJob($bi, $nordigen);

        $bi->refresh();
        $this->assertFalse((bool) $bi->disabled_upstream);
        $this->assertEquals('ERROR', $bi->bank_account_status);
    }

    public function testKnownErrorAccountSkipsStatusCheckDuringCooldown()
    {
        $bi = $this->nordigenIntegration(['bank_account_status' => 'ERROR']);
        Cache::put('nordigen:wake:last:acc-1', true, 60 * 60 * 6);

        $nordigen = Mockery::mock(Nordigen::class);
        $nordigen->shouldReceive('requisitionStatus')->once()->with('req-1')->andReturn('LN');
        $nordigen->shouldNotReceive('isAccountActive');
        $nordigen->shouldNotReceive('wakeAccount');
        $nordigen->shouldNotReceive('disabledAccountEmail');
        $nordigen->shouldNotReceive('getTransactions');

        $this->runJob($bi, $nordigen);

        $bi->refresh();
        $this->assertFalse((bool) $bi->disabled_upstream);
        $this->assertEquals('ERROR', $bi->bank_account_status);
    }

    public function testErrorAccountSkipsWakeWhilePaused()
    {
        $bi = $this->nordigenIntegration();
        Cache::put('nordigen:wake:paused:acc-1', true, 60 * 60 * 24);

        $nordigen = Mockery::mock(Nordigen::class);
        $nordigen->shouldReceive('requisitionStatus')->once()->with('req-1')->andReturn('LN');
        $nordigen->shouldReceive('isAccountActive')->once()->with('acc-1')->andReturn(['status' => 'ERROR']);
        $nordigen->shouldNotReceive('wakeAccount');
        $nordigen->shouldNotReceive('disabledAccountEmail');
        $nordigen->shouldNotReceive('getTransactions');

        $this->runJob($bi, $nordigen);

        $bi->refresh();
        $this->assertFalse((bool) $bi->disabled_upstream);
        $this->assertEquals('ERROR', $bi->bank_account_status);
    }

    public function testRateLimitedWakeLeavesIntegrationEnabled()
    {
        $bi = $this->nordigenIntegration();

        $nordigen = Mockery::mock(Nordigen::class);
        $nordigen->shouldReceive('requisitionStatus')->once()->with('req-1')->andReturn('LN');
        $nordigen->shouldReceive('isAccountActive')->once()->with('acc-1')->andReturn(['status' => 'ERROR']);
        $nordigen->shouldReceive('wakeAccount')->once()->with('acc-1', 'ERROR')->andReturn(['status' => 'WAKE_RATE_LIMITED', 'code' => 429]);
        $nordigen->shouldNotReceive('disabledAccountEmail');
        $nordigen->shouldNotReceive('getTransactions');

        $this->runJob($bi, $nordigen);

        $bi->refresh();
        $this->assertFalse((bool) $bi->disabled_upstream);
        $this->assertEquals('ERROR', $bi->bank_account_status);
        $this->assertEquals(1, Cache::get('nordigen:wake:attempts:acc-1'));
        $this->assertFalse(Cache::has('nordigen:wake:paused:acc-1'));
    }

    public function testTransientWakeFailureLeavesIntegrationEnabled()
    {
        $bi = $this->nordigenIntegration();

        $nordigen = Mockery::mock(Nordigen::class);
        $nordigen->shouldReceive('requisitionStatus')->once()->with('req-1')->andReturn('LN');
        $nordigen->shouldReceive('isAccountActive')->once()->with('acc-1')->andReturn(['status' => 'ERROR']);
        $nordigen->shouldReceive('wakeAccount')->once()->with('acc-1', 'ERROR')->andReturn(['status' => 'WAKE_TRANSIENT_ERROR']);
        $nordigen->shouldNotReceive('disabledAccountEmail');
        $nordigen->shouldNotReceive('getTransactions');

        $this->runJob($bi, $nordigen);

        $bi->refresh();
        $this->assertFalse((bool) $bi->disabled_upstream);
        $this->assertEquals('ERROR', $bi->bank_account_status);
        $this->assertEquals(1, Cache::get('nordigen:wake:attempts:acc-1'));
    }

    public function testRepeatedTransientWakeFailurePausesWakeAttempts()
    {
        $bi = $this->nordigenIntegration();
        Cache::put('nordigen:wake:attempts:acc-1', 2, 60 * 60 * 24);

        $nordigen = Mockery::mock(Nordigen::class);
        $nordigen->shouldReceive('requisitionStatus')->once()->with('req-1')->andReturn('LN');
        $nordigen->shouldReceive('isAccountActive')->once()->with('acc-1')->andReturn(['status' => 'ERROR']);
        $nordigen->shouldReceive('wakeAccount')->once()->with('acc-1', 'ERROR')->andReturn(['status' => 'WAKE_TRANSIENT_ERROR']);
        $nordigen->shouldNotReceive('disabledAccountEmail');
        $nordigen->shouldNotReceive('getTransactions');

        $this->runJob($bi, $nordigen);

        $bi->refresh();
        $this->assertFalse((bool) $bi->disabled_upstream);
        $this->assertEquals('ERROR', $bi->bank_account_status);
        $this->assertEquals(3, Cache::get('nordigen:wake:attempts:acc-1'));
        $this->assertTrue(Cache::has('nordigen:wake:paused:acc-1'));
    }

    public function testPermanentWakeFailureDisablesAndEmails()
    {
        $bi = $this->nordigenIntegration();

        $nordigen = Mockery::mock(Nordigen::class);
        $nordigen->shouldReceive('requisitionStatus')->once()->with('req-1')->andReturn('LN');
        $nordigen->shouldReceive('isAccountActive')->once()->with('acc-1')->andReturn(['status' => 'ERROR']);
        $nordigen->shouldReceive('wakeAccount')->once()->with('acc-1', 'ERROR')->andReturn(['status' => 'EXPIRED']);
        $nordigen->shouldReceive('disabledAccountEmail')->once();
        $nordigen->shouldNotReceive('getTransactions');

        $this->runJob($bi, $nordigen);

        $bi->refresh();
        $this->assertTrue((bool) $bi->disabled_upstream);
        $this->assertEquals('EXPIRED', $bi->bank_account_status);
    }

    public function testReadyAccountClearsWakeState()
    {
        $bi = $this->nordigenIntegration(['bank_account_status' => 'ERROR']);
        Cache::put('nordigen:wake:attempts:acc-1', 2, 60 * 60 * 24);

        $nordigen = Mockery::mock(Nordigen::class);
        $nordigen->shouldReceive('requisitionStatus')->once()->with('req-1')->andReturn('LN');
        $nordigen->shouldReceive('isAccountActive')->once()->with('acc-1')->andReturn(['status' => 'READY']);
        $nordigen->shouldReceive('getTransactions')->andReturn([]);
        $nordigen->shouldNotReceive('disabledAccountEmail');

        $this->runJob($bi, $nordigen);

        $bi->refresh();
        $this->assertFalse((bool) $bi->disabled_upstream);
        $this->assertEquals('READY', $bi->bank_account_status);
        $this->assertFalse(Cache::has('nordigen:wake:last:acc-1'));
        $this->assertFalse(Cache::has('nordigen:wake:attempts:acc-1'));
        $this->assertFalse(Cache::has('nordigen:wake:paused:acc-1'));
    }

    public function testWakeAccountUsesKnownErrorStatusAndTouchesBalancesOnly()
    {
        $history = [];
        $nordigen = $this->nordigenHelperWithResponses([
            new Response(200, [], json_encode(['balances' => []])),
        ], $history);

        $result = $nordigen->wakeAccount('acc-1', 'ERROR');

        $this->assertEquals('WAKE_PROBED', $result['status']);
        $this->assertCount(1, $history);
        $this->assertStringEndsWith('/accounts/acc-1/balances/', $history[0]['request']->getUri()->getPath());
    }

    public function testWakeAccountNormalizesUnknownPermanentErrors()
    {
        $history = [];
        $nordigen = $this->nordigenHelperWithResponses([
            new Response(400, [], json_encode([
                'type' => 'UnknownRequestError',
                'summary' => 'Invalid Account ID',
                'detail' => '',
            ])),
        ], $history);

        $result = $nordigen->wakeAccount('acc-1', 'ERROR');

        $this->assertEquals('Invalid Account ID', $result['status']);
    }

    public function testLegacyRowWithoutRequisitionStillDisablesOnExpired()
    {
        $bi = $this->nordigenIntegration(['requisition_id' => null]);

        $nordigen = Mockery::mock(Nordigen::class);
        $nordigen->shouldNotReceive('requisitionStatus');
        $nordigen->shouldReceive('isAccountActive')->once()->with('acc-1')->andReturn(['status' => 'EXPIRED']);
        $nordigen->shouldReceive('disabledAccountEmail')->once();

        $this->runJob($bi, $nordigen);

        $bi->refresh();
        $this->assertTrue((bool) $bi->disabled_upstream);
        $this->assertEquals('EXPIRED', $bi->bank_account_status);
    }
}
