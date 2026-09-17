<?php

namespace Tests\Feature\ClientPortal;

use App\Livewire\Flow2\UnderOverPayment;
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

    public function test_check_value_rejects_introduced_invoice_ids(): void
    {
        $payable_invoices = [[
            'invoice_id' => $this->invoice->hashed_id,
            'amount' => 10,
            'formatted_amount' => '10.00',
        ]];

        $context_key = $this->put_payment_context($payable_invoices);
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

    public function test_check_value_rejects_per_invoice_underpayment_when_underpayments_are_disabled(): void
    {
        $payable_invoices = [[
            'invoice_id' => $this->invoice->hashed_id,
            'amount' => 10,
            'formatted_amount' => '10.00',
        ], [
            'invoice_id' => 'context-invoice-id',
            'amount' => 20,
            'formatted_amount' => '20.00',
        ]];

        $context_key = $this->put_payment_context($payable_invoices, false, true);
        $submitted_payable_invoices = [[
            'invoice_id' => $this->invoice->hashed_id,
            'formatted_amount' => '5.00',
        ], [
            'invoice_id' => 'context-invoice-id',
            'formatted_amount' => '25.00',
        ]];

        Livewire::test(UnderOverPayment::class, ['_key' => $context_key])
            ->call('checkValue', $submitted_payable_invoices)
            ->assertSet('errors', ctrans('texts.minimum_required_payment', ['amount' => 10.0]));

        $this->assertSame($payable_invoices, Cache::get($context_key)['payable_invoices']);
    }

    private function put_payment_context(array $payable_invoices, bool $allow_under_payment = true, bool $allow_over_payment = true): string
    {
        $settings = $this->client->getMergedSettings();
        $settings->client_portal_allow_under_payment = $allow_under_payment;
        $settings->client_portal_under_payment_minimum = 1;
        $settings->client_portal_allow_over_payment = $allow_over_payment;
        $context_key = 'under-over-payment-test-' . uniqid();

        Cache::put($context_key, [
            'contact' => $this->contact,
            'settings' => $settings,
            'payable_invoices' => $payable_invoices,
        ], now()->addHour());

        return $context_key;
    }
}
