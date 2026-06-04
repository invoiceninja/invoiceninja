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

namespace Tests\Feature\ClientPortal;

use App\Exceptions\PaymentFailed;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use App\Models\PaymentHash;
use App\Services\ClientPortal\LivewireInstantPayment;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * Regression tests for the gateway-fee duplicate-application race.
 *
 * Production incident 2026-05-27: two requests for the same invoice landed
 * ~4ms apart, each calling addGatewayFee and creating a PaymentHash —
 * resulting in a doubled gateway-fee line item and doubled client balance.
 *
 * The fix wraps the fee-add + PaymentHash creation in a Cache::lock and has
 * the loser adopt the winner's PaymentHash row instead of creating its own.
 */
class LivewireInstantPaymentRaceTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        if (! config('ninja.testvars.stripe')) {
            $this->markTestSkipped('Skip test no company gateways installed');
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeCompanyGateway(): CompanyGateway
    {
        $data = [];
        $data[1]['min_limit'] = -1;
        $data[1]['max_limit'] = -1;
        $data[1]['fee_amount'] = 1.00;
        $data[1]['fee_percent'] = 0.000;
        $data[1]['fee_tax_name1'] = '';
        $data[1]['fee_tax_rate1'] = 0;
        $data[1]['fee_tax_name2'] = '';
        $data[1]['fee_tax_rate2'] = 0;
        $data[1]['fee_tax_name3'] = '';
        $data[1]['fee_tax_rate3'] = 0;
        $data[1]['adjust_fee_percent'] = false;
        $data[1]['fee_cap'] = 0;
        $data[1]['is_enabled'] = true;

        $cg = new CompanyGateway();
        $cg->company_id = $this->company->id;
        $cg->user_id = $this->user->id;
        $cg->gateway_key = 'd14dd26a37cecc30fdd65700bfb55b23';
        $cg->require_cvv = true;
        $cg->require_billing_address = true;
        $cg->require_shipping_address = true;
        $cg->update_details = true;
        $cg->config = encrypt(config('ninja.testvars.stripe'));
        $cg->fees_and_limits = $data;
        $cg->save();

        return $cg;
    }

    private function makePayload(CompanyGateway $cg): array
    {
        return [
            'company_gateway_id' => $cg->id,
            'payment_method_id' => GatewayType::CREDIT_CARD,
            'payable_invoices' => [
                ['invoice_id' => $this->invoice->hashed_id, 'amount' => $this->invoice->balance],
            ],
            'signature' => false,
            'signature_ip' => false,
            'pre_payment' => false,
            'frequency_id' => false,
            'remaining_cycles' => false,
            'is_recurring' => false,
        ];
    }

    private function bindFakeLock(bool $getResult, bool $blockResult = true): void
    {
        $lock = Mockery::mock(Lock::class);
        $lock->shouldReceive('get')->andReturn($getResult);
        $lock->shouldReceive('block')->andReturn($blockResult);
        $lock->shouldReceive('release')->andReturn(true);

        Cache::partialMock()
            ->shouldReceive('lock')
            ->andReturn($lock);
    }

    public function testWinnerPathCreatesSingleFeeAndSingleHash(): void
    {
        $cg = $this->makeCompanyGateway();

        $starting_balance = $this->invoice->balance;
        $starting_client_balance = $this->client->balance;

        $response = (new LivewireInstantPayment($this->makePayload($cg)))->run();

        $this->assertTrue($response['success']);

        $invoice = $this->invoice->fresh();
        $fee_items = collect($invoice->line_items)->where('type_id', '3');

        $this->assertCount(1, $fee_items, 'expected exactly one gateway-fee line item');
        $this->assertEquals($starting_balance + 1.0, (float) $invoice->balance);

        $hashes = PaymentHash::where('fee_invoice_id', $invoice->id)->get();
        $this->assertCount(1, $hashes, 'expected exactly one PaymentHash row');
        $this->assertEquals(1.0, (float) $hashes->first()->fee_total);

        $client = $this->client->fresh();
        $this->assertEquals($starting_client_balance + 1.0, (float) $client->balance);
    }

    public function testLoserAdoptsExistingWinnerHash(): void
    {
        $cg = $this->makeCompanyGateway();

        // Simulate a winner having just finished: fee is on the invoice and
        // a PaymentHash row exists.
        $this->invoice = $this->invoice
            ->service()
            ->addGatewayFee($cg, GatewayType::CREDIT_CARD, $this->invoice->balance, 'winner-hash-string')
            ->save();

        $balance_after_winner = $this->invoice->balance;
        $client_balance_after_winner = $this->client->fresh()->balance;

        $winner = new PaymentHash();
        $winner->hash = 'winner-hash-string';
        $winner->data = [
            'invoices' => [],
            'credits' => 0,
            'amount_with_fee' => 0,
            'pre_payment' => false,
            'frequency_id' => false,
            'remaining_cycles' => false,
            'is_recurring' => false,
        ];
        $winner->fee_total = 1.0;
        $winner->fee_invoice_id = $this->invoice->id;
        $winner->save();

        // Force the loser branch
        $this->bindFakeLock(getResult: false, blockResult: true);

        $response = (new LivewireInstantPayment($this->makePayload($cg)))->run();

        $this->assertTrue($response['success']);
        $this->assertEquals('winner-hash-string', $response['payload']['payment_hash']);

        // The critical invariant: still exactly ONE PaymentHash row, ONE fee line, ONE balance bump.
        $hashes = PaymentHash::where('fee_invoice_id', $this->invoice->id)->get();
        $this->assertCount(1, $hashes, 'loser must adopt winner hash, not insert a second row');
        $this->assertEquals('winner-hash-string', $hashes->first()->hash);

        $invoice = $this->invoice->fresh();
        $fee_items = collect($invoice->line_items)->where('type_id', '3');
        $this->assertCount(1, $fee_items, 'loser must not append a second fee line');
        $this->assertEquals((float) $balance_after_winner, (float) $invoice->balance);

        $this->assertEquals((float) $client_balance_after_winner, (float) $this->client->fresh()->balance);
    }

    public function testLoserThrowsWhenNoRecentWinnerHashExists(): void
    {
        $cg = $this->makeCompanyGateway();

        $this->bindFakeLock(getResult: false, blockResult: true);

        $this->expectException(PaymentFailed::class);
        $this->expectExceptionMessage(ctrans('texts.processing_request'));

        (new LivewireInstantPayment($this->makePayload($cg)))->run();
    }

    public function testLoserIgnoresStaleHashOutsideAdoptionWindow(): void
    {
        $cg = $this->makeCompanyGateway();

        // A PaymentHash exists but is older than the 2-second adoption window;
        // the loser must treat it as not-a-winner and throw.
        $stale = new PaymentHash();
        $stale->hash = 'stale-hash';
        $stale->data = [
            'invoices' => [], 'credits' => 0, 'amount_with_fee' => 0,
            'pre_payment' => false, 'frequency_id' => false,
            'remaining_cycles' => false, 'is_recurring' => false,
        ];
        $stale->fee_total = 0;
        $stale->fee_invoice_id = $this->invoice->id;
        $stale->save();

        // Backdate it past the adoption window
        PaymentHash::where('id', $stale->id)->update(['created_at' => now()->subSeconds(10)]);

        $this->bindFakeLock(getResult: false, blockResult: true);

        $this->expectException(PaymentFailed::class);

        (new LivewireInstantPayment($this->makePayload($cg)))->run();
    }

    public function testNonGatewayPathSkipsLockAndCreatesHash(): void
    {
        // GATEWAY_CREDIT sentinel: CompanyGateway::find() returns null, lock block is skipped.
        $payload = [
            'company_gateway_id' => CompanyGateway::GATEWAY_CREDIT,
            'payment_method_id' => GatewayType::CREDIT,
            'payable_invoices' => [
                ['invoice_id' => $this->invoice->hashed_id, 'amount' => $this->invoice->balance],
            ],
            'signature' => false,
            'signature_ip' => false,
            'pre_payment' => false,
            'frequency_id' => false,
            'remaining_cycles' => false,
            'is_recurring' => false,
        ];

        $starting_balance = $this->invoice->balance;

        $response = (new LivewireInstantPayment($payload))->run();

        $this->assertTrue($response['success']);

        $invoice = $this->invoice->fresh();
        $fee_items = collect($invoice->line_items)->where('type_id', '3');
        $this->assertCount(0, $fee_items, 'credit payment must not add a gateway fee');
        $this->assertEquals((float) $starting_balance, (float) $invoice->balance);

        $hashes = PaymentHash::where('fee_invoice_id', $invoice->id)->get();
        $this->assertCount(1, $hashes, 'credit payment still creates one PaymentHash row');
        $this->assertEquals(0.0, (float) $hashes->first()->fee_total);
    }
}
