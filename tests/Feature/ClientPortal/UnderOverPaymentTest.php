<?php

namespace Tests\Feature\ClientPortal;

use App\Livewire\Flow2\UnderOverPayment;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\MockAccountData;
use Tests\TestCase;

class UnderOverPaymentTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
        $this->actingAs($this->contact, 'contact');
    }

    public function testCheckValueRejectsIntroducedInvoiceIds(): void
    {
        $payable_invoices = [[
            'invoice_id' => $this->invoice->hashed_id,
            'amount' => 10,
            'formatted_amount' => '10.00',
        ]];

        $context_key = $this->putPaymentContext($payable_invoices);
        $submitted_payable_invoices = $payable_invoices;
        $submitted_payable_invoices[] = [
            'invoice_id' => 'introduced-invoice-id',
            'formatted_amount' => '1.00',
        ];

        Livewire::test(UnderOverPayment::class, ['_key' => $context_key])
            ->call('checkValue', $submitted_payable_invoices)
            ->assertSet('errors', ctrans('texts.no_payable_invoices_selected'));

        $this->assertSame($payable_invoices, Cache::get($context_key)['payable_invoices']);
    }

    public function testCheckValueRejectsPerInvoiceUnderpaymentWhenUnderpaymentsAreDisabled(): void
    {
        $this->invoice->forceFill([
            'amount' => 10,
            'balance' => 10,
            'partial' => 0,
            'status_id' => Invoice::STATUS_SENT,
            'is_deleted' => false,
        ])->saveQuietly();

        $second_invoice = $this->invoice->replicate();
        $second_invoice->number .= '-second';
        $second_invoice->amount = 20;
        $second_invoice->balance = 20;
        $second_invoice->saveQuietly();

        $payable_invoices = [[
            'invoice_id' => $this->invoice->hashed_id,
            'amount' => 10,
            'formatted_amount' => '10.00',
        ], [
            'invoice_id' => $second_invoice->hashed_id,
            'amount' => 20,
            'formatted_amount' => '20.00',
        ]];

        $context_key = $this->putPaymentContext($payable_invoices, false, true);
        $submitted_payable_invoices = [[
            'invoice_id' => $this->invoice->hashed_id,
            'formatted_amount' => '5.00',
        ], [
            'invoice_id' => $second_invoice->hashed_id,
            'formatted_amount' => '25.00',
        ]];

        Livewire::test(UnderOverPayment::class, ['_key' => $context_key])
            ->call('checkValue', $submitted_payable_invoices)
            ->assertSet('errors', ctrans('texts.minimum_required_payment', ['amount' => 10.0]));

        $this->assertSame($payable_invoices, Cache::get($context_key)['payable_invoices']);
    }

    public function testCustomAmountCanBeRevisedForDraftInvoice(): void
    {
        $this->invoice->forceFill([
            'amount' => 1000,
            'balance' => 0,
            'partial' => 0,
            'status_id' => Invoice::STATUS_DRAFT,
            'is_deleted' => false,
        ])->saveQuietly();

        $context_key = $this->putPaymentContext([[
            'invoice_id' => $this->invoice->hashed_id,
            'amount' => 1000,
            'formatted_amount' => '1000.00',
        ]], true, false);

        Livewire::test(UnderOverPayment::class, ['_key' => $context_key])
            ->call('checkValue', [[
                'invoice_id' => $this->invoice->hashed_id,
                'formatted_amount' => '250',
            ]])
            ->assertSet('errors', '')
            ->call('checkValue', [[
                'invoice_id' => $this->invoice->hashed_id,
                'formatted_amount' => '500',
            ]])
            ->assertSet('errors', '');

        $this->assertEquals(500, Cache::get($context_key)['payable_invoices'][0]['amount']);
        $this->assertEquals(Invoice::STATUS_DRAFT, $this->invoice->fresh()->status_id);
        $this->assertEquals(0, $this->invoice->fresh()->balance);
    }

    public function testCheckValueUsesPaymentContextDatabase(): void
    {
        $this->invoice->forceFill([
            'amount' => 1000,
            'balance' => 1000,
            'partial' => 0,
            'status_id' => Invoice::STATUS_SENT,
            'is_deleted' => false,
        ])->saveQuietly();

        $this->contact->load('client');
        $this->contact->client->currency();
        $context_key = $this->putPaymentContext([[
            'invoice_id' => $this->invoice->hashed_id,
            'amount' => 1000,
            'formatted_amount' => '1000.00',
        ]], true, false);

        $original_connection = config('database.default');
        config([
            'database.connections.under_over_payment_other' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
            'database.default' => 'under_over_payment_other',
        ]);

        try {
            $component = new UnderOverPayment();
            $component->_key = $context_key;
            $component->checkValue([[
                'invoice_id' => $this->invoice->hashed_id,
                'formatted_amount' => '500',
            ]]);

            $this->assertSame('', $component->errors);
            $this->assertEquals(500, Cache::get($context_key)['payable_invoices'][0]['amount']);
        } finally {
            config(['database.default' => $original_connection]);
        }
    }

    private function putPaymentContext(array $payable_invoices, bool $allow_under_payment = true, bool $allow_over_payment = true): string
    {
        $settings = $this->client->getMergedSettings();
        $settings->client_portal_allow_under_payment = $allow_under_payment;
        $settings->client_portal_under_payment_minimum = 1;
        $settings->client_portal_allow_over_payment = $allow_over_payment;
        $context_key = 'under-over-payment-test-' . uniqid();

        Cache::put($context_key, [
            'db' => config('database.default'),
            'contact' => $this->contact,
            'settings' => $settings,
            'payable_invoices' => $payable_invoices,
        ], now()->addHour());

        return $context_key;
    }
}
