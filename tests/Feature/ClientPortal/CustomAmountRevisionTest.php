<?php

namespace Tests\Feature\ClientPortal;

use App\Livewire\Flow2\InvoicePay;
use App\Livewire\Flow2\UnderOverPayment;
use App\Models\CompanyGateway;
use App\Models\Invoice;
use App\Models\PaymentHash;
use App\Services\ClientPortal\InstantPayment;
use App\Services\ClientPortal\LivewireInstantPayment;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * Under/over limits are applied in two different places.
 *
 * Smooth checkout (single invoice and bulk) asks UnderOverPayment::checkValue
 * to compare the typed amount with payable_invoices[].amount. A successful
 * check writes the typed amount back into that same field.
 *
 * Default checkout (single invoice and bulk) posts to InstantPayment, which
 * compares every request with the invoice partial, or the balance when no
 * partial is set.
 *
 * The smooth charge step, LivewireInstantPayment, does not apply either limit.
 */
class CustomAmountRevisionTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
        $this->actingAs($this->contact, 'contact');
        $this->allowCustomAmounts(under: true, over: false);
    }

    public function testSmoothAmountStepComparesASecondSubmitToThePreviousCustomAmount(): void
    {
        $invoice = $this->prepareInvoice(amount: 1000, balance: 1000);
        $context_key = $invoice->invitations()->first()->key;
        $this->putPayableContext($context_key, [$this->payableRow($invoice, 1000)]);

        $component = Livewire::test(UnderOverPayment::class, ['_key' => $context_key])
            ->call('checkValue', [$this->submittedRow($invoice, 250)])
            ->assertSet('errors', '');

        $this->assertEquals(250, Cache::get($context_key)['payable_invoices'][0]['amount']);

        $component
            ->call('checkValue', [$this->submittedRow($invoice, 500)])
            ->assertSet('errors', ctrans('texts.over_payments_disabled'));

        $this->assertEquals(250, Cache::get($context_key)['payable_invoices'][0]['amount']);
    }

    public function testSmoothBulkAmountStepKeepsASeparateCeilingForEachInvoice(): void
    {
        $first = $this->prepareInvoice(amount: 1000, balance: 1000);
        $second = $this->makeInvoice(amount: 400, balance: 400);
        $context_key = $first->invitations()->first()->key;
        $this->putPayableContext($context_key, [
            $this->payableRow($first, 1000),
            $this->payableRow($second, 400),
        ]);

        Livewire::test(UnderOverPayment::class, ['_key' => $context_key])
            ->call('checkValue', [
                $this->submittedRow($first, 250),
                $this->submittedRow($second, 300),
            ])
            ->assertSet('errors', '')
            ->call('checkValue', [
                $this->submittedRow($first, 500),
                $this->submittedRow($second, 300),
            ])
            ->assertSet('errors', ctrans('texts.over_payments_disabled'));

        $stored = collect(Cache::get($context_key)['payable_invoices'])->keyBy('invoice_id');
        $this->assertEquals(250, $stored[$first->hashed_id]['amount']);
        $this->assertEquals(300, $stored[$second->hashed_id]['amount']);
    }

    public function testSmoothDepositStepUsesThePartialUntilTheCustomAmountReplacesIt(): void
    {
        $invoice = $this->prepareInvoice(amount: 1000, balance: 1000, partial: 400);
        $context_key = $invoice->invitations()->first()->key;
        $this->putPayableContext($context_key, [$this->payableRow($invoice, 400)]);

        Livewire::test(UnderOverPayment::class, ['_key' => $context_key])
            ->call('checkValue', [$this->submittedRow($invoice, 200)])
            ->assertSet('errors', '')
            ->call('checkValue', [$this->submittedRow($invoice, 350)])
            ->assertSet('errors', ctrans('texts.over_payments_disabled'));

        $this->assertEquals(200, Cache::get($context_key)['payable_invoices'][0]['amount']);
        $this->assertEquals(400, (float) $invoice->fresh()->partial);
    }

    public function testFreshSmoothPageLoadRebuildsThePayableFromTheInvoice(): void
    {
        $invoice = $this->prepareInvoice(amount: 1000, balance: 1000, partial: 400);
        $invitation = $invoice->invitations()->first();
        $this->putPayableContext($invitation->key, [$this->payableRow($invoice, 400)]);

        Livewire::test(UnderOverPayment::class, ['_key' => $invitation->key])
            ->call('checkValue', [$this->submittedRow($invoice, 200)])
            ->assertSet('errors', '');

        $this->assertEquals(200, Cache::get($invitation->key)['payable_invoices'][0]['amount']);

        Livewire::test(InvoicePay::class, [
            'invoices' => [$invoice->hashed_id],
            'invitation_id' => $invitation->id,
            'db' => $this->company->db,
            'variables' => false,
        ]);

        $rebuilt = Cache::get($invitation->key)['payable_invoices'][0]['amount'];
        $this->assertEquals(400, $rebuilt);

        Livewire::test(UnderOverPayment::class, ['_key' => $invitation->key])
            ->call('checkValue', [$this->submittedRow($invoice, 350)])
            ->assertSet('errors', '');

        $this->assertEquals(350, Cache::get($invitation->key)['payable_invoices'][0]['amount']);
    }

    public function testDefaultFlowComparesEverySubmitWithTheInvoice(): void
    {
        $invoice = $this->prepareInvoice(amount: 1000, balance: 1000);

        $this->assertNull($this->defaultRejection([$this->postedRow($invoice, 250)]));
        $this->assertNull($this->defaultRejection([$this->postedRow($invoice, 500)]));
        $this->assertSame(
            ctrans('texts.over_payments_disabled'),
            $this->defaultRejection([$this->postedRow($invoice, 1250)])
        );

        $invoice = $this->prepareInvoice($invoice, amount: 1000, balance: 1000, partial: 400);

        $this->assertNull($this->defaultRejection([$this->postedRow($invoice, 200)]));
        $this->assertNull($this->defaultRejection([$this->postedRow($invoice, 350)]));
        $this->assertSame(
            ctrans('texts.over_payments_disabled'),
            $this->defaultRejection([$this->postedRow($invoice, 450)])
        );
    }

    public function testDefaultBulkFlowComparesEachInvoiceOnEverySubmit(): void
    {
        $first = $this->prepareInvoice(amount: 1000, balance: 1000);
        $second = $this->makeInvoice(amount: 400, balance: 400);

        $this->assertNull($this->defaultRejection([
            $this->postedRow($first, 250),
            $this->postedRow($second, 300),
        ]));
        $this->assertNull($this->defaultRejection([
            $this->postedRow($first, 500),
            $this->postedRow($second, 300),
        ]));
        $this->assertSame(
            ctrans('texts.over_payments_disabled'),
            $this->defaultRejection([
                $this->postedRow($first, 500),
                $this->postedRow($second, 450),
            ])
        );
    }

    #[DataProvider('smoothCheckoutPayableAmounts')]
    public function testSmoothCheckoutUsesThePartialOrBalanceAsThePayable(
        bool $allow_under_payment,
        bool $allow_over_payment,
        float $partial,
        float $submitted_amount,
        bool $accepted,
        ?string $error,
    ): void {
        $this->allowCustomAmounts($allow_under_payment, $allow_over_payment);
        $invoice = $this->prepareInvoice(amount: 1000, balance: 1000, partial: $partial);
        $payable = $partial > 0 ? $partial : 1000.0;
        $hashes_before = PaymentHash::count();

        $response = $this->smoothCheckout($invoice, $submitted_amount);

        $invoice->refresh();
        $this->assertEquals($partial, (float) $invoice->partial);
        $this->assertEquals(1000, (float) $invoice->balance);

        if ($accepted) {
            $this->assertTrue($response['success']);
            $this->assertSame($hashes_before + 1, PaymentHash::count());
            $this->assertEquals($submitted_amount, (float) data_get($response, 'payload.ph.data.invoices.0.amount'));

            return;
        }

        $this->assertFalse($response['success']);
        $this->assertSame($hashes_before, PaymentHash::count());
        $this->assertSame($this->payableError($error, $payable), $response['error']);
    }

    public static function smoothCheckoutPayableAmounts(): array
    {
        return [
            'pay the deposit' => [true, false, 400, 400, true, null],
            'pay the balance instead of the deposit' => [true, false, 400, 1000, false, 'over'],
            'underpay the deposit down to the minimum' => [true, false, 400, 350, true, null],
            'exceed the deposit while remaining under the balance' => [true, false, 400, 450, false, 'over'],
            'pay the balance when no deposit is set' => [true, false, 0, 1000, true, null],
            'exceed the balance when no deposit is set' => [true, false, 0, 1500, false, 'over'],
            'exact deposit when custom amounts are disabled' => [false, false, 400, 400, true, null],
            'balance is above the deposit when custom amounts are disabled' => [false, false, 400, 1000, false, 'over'],
            'amount below the deposit is rejected when underpayments are disabled' => [false, false, 400, 200, false, 'under'],
            'amount above the deposit is allowed when overpayments are enabled' => [false, true, 400, 1000, true, null],
            'amount below a deposit that is itself below the minimum' => [true, false, 0.5, 0.4, false, 'under'],
            'amount below the configured minimum while the deposit is above it' => [true, false, 400, 0.5, false, 'minimum'],
        ];
    }

    private function allowCustomAmounts(bool $under, bool $over): void
    {
        foreach ([$this->client, $this->company] as $entity) {
            $settings = $entity->settings;
            $settings->client_portal_allow_under_payment = $under;
            $settings->client_portal_allow_over_payment = $over;
            $settings->client_portal_under_payment_minimum = 1;
            $settings->show_accept_invoice_terms = false;
            $settings->require_invoice_signature = false;
            $entity->settings = $settings;
            $entity->saveQuietly();
        }
    }

    private function prepareInvoice(?Invoice $invoice = null, float $amount = 1000, float $balance = 1000, float $partial = 0): Invoice
    {
        $invoice = $invoice ?? $this->invoice;
        $invoice->forceFill([
            'amount' => $amount,
            'balance' => $balance,
            'partial' => $partial,
            'paid_to_date' => 0,
            'status_id' => Invoice::STATUS_SENT,
            'is_deleted' => false,
        ])->saveQuietly();

        return $invoice->fresh();
    }

    private function makeInvoice(float $amount, float $balance, float $partial = 0): Invoice
    {
        $invoice = $this->invoice->replicate();
        $invoice->number = $this->invoice->number . '-' . $amount;
        $invoice->save();

        return $this->prepareInvoice($invoice, $amount, $balance, $partial);
    }

    private function putPayableContext(string $key, array $payable_invoices): void
    {
        $settings = $this->client->fresh()->getMergedSettings();

        Cache::put($key, [
            'contact' => $this->contact,
            'settings' => $settings,
            'db' => $this->company->db,
            'payable_invoices' => $payable_invoices,
        ], now()->addHour());
    }

    private function payableRow(Invoice $invoice, float $amount): array
    {
        return [
            'invoice_id' => $invoice->hashed_id,
            'amount' => $amount,
            'formatted_amount' => number_format($amount, 2, '.', ''),
            'number' => $invoice->number,
        ];
    }

    private function submittedRow(Invoice $invoice, float $amount): array
    {
        return [
            'invoice_id' => $invoice->hashed_id,
            'formatted_amount' => (string) $amount,
        ];
    }

    private function postedRow(Invoice $invoice, float $amount): array
    {
        return [
            'invoice_id' => $invoice->hashed_id,
            'amount' => $amount,
        ];
    }

    private function smoothCheckout(Invoice $invoice, float $amount): array
    {
        return (new LivewireInstantPayment([
            'company_gateway_id' => CompanyGateway::GATEWAY_CREDIT,
            'payment_method_id' => 1,
            'payable_invoices' => [$this->postedRow($invoice, $amount)],
            'pre_payment' => false,
            'frequency_id' => false,
            'remaining_cycles' => false,
            'is_recurring' => false,
        ]))->run();
    }

    private function payableError(string $error, float $payable): string
    {
        return match ($error) {
            'over' => ctrans('texts.over_payments_disabled'),
            'under' => ctrans('texts.minimum_required_payment', ['amount' => $payable]),
            'minimum' => ctrans('texts.minimum_required_payment', ['amount' => 1]),
        };
    }

    private function defaultRejection(array $payable_invoices): ?string
    {
        $response = (new InstantPayment(Request::create('/client/payments/process', 'POST', [
            'company_gateway_id' => CompanyGateway::GATEWAY_CREDIT,
            'payment_method_id' => 1,
            'payable_invoices' => $payable_invoices,
            'pre_payment' => false,
            'frequency_id' => false,
            'remaining_cycles' => false,
            'is_recurring' => false,
        ])))->run();

        if (! $response instanceof RedirectResponse) {
            return null;
        }

        return $response->getSession()->get('message');
    }
}
