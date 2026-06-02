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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Mockery;
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
