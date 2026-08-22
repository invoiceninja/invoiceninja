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

use App\Factory\InvoiceFactory;
use App\Factory\InvoiceItemFactory;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use App\Models\Invoice;
use App\Models\PaymentHash;
use App\Services\Invoice\ConfirmGatewayFee;
use Illuminate\Support\Str;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * Genuine parallelism, not a simulated stale value.
 *
 * Real OS processes confirm against the same invoice row concurrently. The updated_at
 * claim must serialise them: every fee lands exactly once, the amount reflects every
 * fee, and no writer silently overwrites another.
 *
 * These tests commit to the database - DatabaseTransactions is deliberately NOT used,
 * because a transaction would hide the rows from the child processes. Everything created
 * is torn down explicitly.
 */
class GatewayFeeParallelConfirmTest extends TestCase
{
    use MockAccountData;

    private array $created_invoice_ids = [];

    private array $created_hash_ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    protected function tearDown(): void
    {
        PaymentHash::query()->whereIn('id', $this->created_hash_ids)->forceDelete();
        Invoice::query()->whereIn('id', $this->created_invoice_ids)->forceDelete();

        parent::tearDown();
    }

    private function gateway(float $fee_amount = 5): CompanyGateway
    {
        $cg = new CompanyGateway();
        $cg->company_id = $this->company->id;
        $cg->user_id = $this->user->id;
        $cg->gateway_key = 'd14dd26a37cecc30fdd65700bfb55b23';
        $cg->config = encrypt(json_encode(new \stdClass()));
        $cg->fees_and_limits = [
            GatewayType::CREDIT_CARD => [
                'min_limit' => -1, 'max_limit' => -1,
                'fee_amount' => $fee_amount, 'fee_percent' => 0,
                'fee_tax_name1' => '', 'fee_tax_rate1' => 0,
                'fee_tax_name2' => '', 'fee_tax_rate2' => 0,
                'fee_tax_name3' => '', 'fee_tax_rate3' => 0,
                'fee_cap' => 0, 'adjust_fee_percent' => false, 'is_enabled' => true,
            ],
        ];
        $cg->save();

        return $cg;
    }

    private function committedInvoice(): Invoice
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 100;

        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;
        $invoice->line_items = [$item];
        $invoice->uses_inclusive_taxes = false;
        $invoice->save();

        $invoice = $invoice->calc()->getInvoice();
        $invoice->save();
        $invoice = $invoice->service()->markSent()->save();

        $this->created_invoice_ids[] = $invoice->id;

        return $invoice;
    }

    private function hashFor(Invoice $invoice, float $fee): PaymentHash
    {
        $hash = PaymentHash::create([
            'hash' => Str::random(32),
            'fee_total' => $fee,
            'fee_invoice_id' => $invoice->id,
            'data' => [
                'invoices' => [], 'credits' => 0,
                'fee_net' => $fee, 'amount_with_fee' => 100 + $fee,
            ],
        ]);

        $this->created_hash_ids[] = $hash->id;

        return $hash;
    }

    /**
     * Runs ConfirmGatewayFee for each hash in its own PHP process, started together.
     *
     * @param string[] $hashes
     */
    private function confirmInParallel(int $company_gateway_id, array $hashes): void
    {
        $script = base_path('tests/artifacts/parallel_confirm.php');

        $handles = [];

        foreach ($hashes as $hash) {
            $cmd = sprintf(
                'php %s %s %d 2>&1',
                escapeshellarg($script),
                escapeshellarg($hash),
                $company_gateway_id
            );

            $handles[] = popen($cmd, 'r');
        }

        foreach ($handles as $handle) {
            $output = stream_get_contents($handle);
            $status = pclose($handle);

            $this->assertSame(0, $status, "child process failed: {$output}");
        }
    }

    /**
     * Two different attempts confirm at the same instant. Both fees must land.
     * If the claim guard were absent, one would overwrite the other.
     */
    public function testTwoParallelConfirmationsBothLand(): void
    {
        $cg = $this->gateway(5);
        $invoice = $this->committedInvoice();

        $a = $this->hashFor($invoice, 5);
        $b = $this->hashFor($invoice, 5);

        $this->confirmInParallel($cg->id, [$a->hash, $b->hash]);

        $final = Invoice::withTrashed()->find($invoice->id);
        $lines = collect($final->line_items);

        $this->assertCount(1, $lines->where('unit_code', $a->hash), 'attempt A lost its fee to a concurrent writer');
        $this->assertCount(1, $lines->where('unit_code', $b->hash), 'attempt B lost its fee to a concurrent writer');
        $this->assertEquals(110, round((float) $final->amount, 2), 'the invoice total does not reflect both fees');
    }

    /**
     * The same attempt confirmed by several processes at once - webhook redelivery
     * racing itself. Exactly one fee, exactly one adjustment.
     */
    public function testParallelRedeliveryOfOneAttemptProducesOneFee(): void
    {
        $cg = $this->gateway(5);
        $invoice = $this->committedInvoice();

        $hash = $this->hashFor($invoice, 5);

        $this->confirmInParallel($cg->id, array_fill(0, 4, $hash->hash));

        $final = Invoice::withTrashed()->find($invoice->id);

        $this->assertCount(
            1,
            collect($final->line_items)->where('unit_code', $hash->hash),
            'parallel redelivery produced duplicate surcharges'
        );
        $this->assertEquals(105, round((float) $final->amount, 2));
    }

    /**
     * Heavy contention. Ten distinct attempts confirming at once against one invoice.
     *
     * This is well past anything production should see; it exists to prove the retry
     * ceiling and backoff hold with margin rather than only just.
     */
    public function testTenParallelConfirmationsAllLand(): void
    {
        $cg = $this->gateway(5);
        $invoice = $this->committedInvoice();

        $hashes = [];

        foreach (range(1, 10) as $ignored) {
            $hashes[] = $this->hashFor($invoice, 5)->hash;
        }

        $this->confirmInParallel($cg->id, $hashes);

        $final = Invoice::withTrashed()->find($invoice->id);
        $lines = collect($final->line_items);

        $lost = [];

        foreach ($hashes as $hash) {
            if ($lines->where('unit_code', $hash)->count() !== 1) {
                $lost[] = $hash;
            }
        }

        $this->assertSame([], $lost, count($lost) . ' of 10 parallel confirmations were lost');
        $this->assertEquals(150, round((float) $final->amount, 2), 'the invoice total does not reflect all ten fees');
    }

    /** Five distinct attempts at once - every one must survive. */
    public function testFiveParallelConfirmationsAllLand(): void
    {
        $cg = $this->gateway(5);
        $invoice = $this->committedInvoice();

        $hashes = [];

        foreach (range(1, 5) as $ignored) {
            $hashes[] = $this->hashFor($invoice, 5)->hash;
        }

        $this->confirmInParallel($cg->id, $hashes);

        $final = Invoice::withTrashed()->find($invoice->id);
        $lines = collect($final->line_items);

        foreach ($hashes as $hash) {
            $this->assertCount(1, $lines->where('unit_code', $hash), "attempt {$hash} was lost");
        }

        $this->assertEquals(125, round((float) $final->amount, 2), 'the invoice total does not reflect all five fees');
    }
}
