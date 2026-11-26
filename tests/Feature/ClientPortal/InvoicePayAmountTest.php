<?php

namespace Tests\Feature\ClientPortal;

use App\Livewire\Flow2\InvoicePay;
use App\Livewire\Flow2\UnderOverPayment;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\MockAccountData;
use Tests\TestCase;

class InvoicePayAmountTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
        $this->actingAs($this->contact, 'contact');
    }

    #[DataProvider('selectedPaymentAmounts')]
    public function testPaymentMethodSelectionPreservesValidatedInvoiceAmounts(float $partial, float $selected_amount): void
    {
        $this->invoice->forceFill([
            'amount' => 1000,
            'balance' => 1000,
            'partial' => $partial,
            'status_id' => Invoice::STATUS_SENT,
        ])->saveQuietly();

        $invitation = $this->invoice->invitations()->first();
        $default_amount = $partial > 0 ? $partial : 1000;
        $settings = $this->client->getMergedSettings();
        $settings->client_portal_allow_under_payment = true;
        $settings->client_portal_allow_over_payment = true;
        $settings->client_portal_under_payment_minimum = 1;

        Cache::put($invitation->key, [
            'contact' => $this->contact,
            'settings' => $settings,
            'payable_invoices' => [[
                'invoice_id' => $this->invoice->hashed_id,
                'amount' => $default_amount,
                'formatted_amount' => (string) $default_amount,
            ]],
        ], now()->addHour());

        Livewire::test(UnderOverPayment::class, ['_key' => $invitation->key])
            ->call('checkValue', [[
                'invoice_id' => $this->invoice->hashed_id,
                'formatted_amount' => (string) $selected_amount,
            ]])
            ->assertSet('errors', '')
            ->assertDispatched('payable-amount');

        $payable_invoices = Cache::get($invitation->key)['payable_invoices'];
        $this->assertEquals($selected_amount, $payable_invoices[0]['amount']);

        $component = new InvoicePay();
        $component->invitation_id = $invitation->id;
        $component->invoices = [$this->invoice->hashed_id];
        $component->payableAmount($selected_amount);
        $component->paymentMethodSelected(CompanyGateway::GATEWAY_CREDIT, GatewayType::CREDIT_CARD, $selected_amount);

        $context = Cache::get($invitation->key);
        $this->assertSame($payable_invoices, $context['payable_invoices']);
        $this->assertEquals($selected_amount, $context['amount']);
    }

    public static function selectedPaymentAmounts(): array
    {
        return [
            'underpayment' => [0, 250],
            'deposit underpayment' => [400, 125],
            'overpayment' => [0, 1250],
            'full payment' => [0, 1000],
        ];
    }

    #[DataProvider('revisedCustomAmounts')]
    public function testCustomAmountCanBeRevisedAgainstOriginalPayable(
        bool $allow_under_payment,
        bool $allow_over_payment,
        float $first_amount,
        float $revised_amount,
    ): void {
        $this->invoice->forceFill([
            'amount' => 1000,
            'balance' => 1000,
            'partial' => 0,
            'status_id' => Invoice::STATUS_SENT,
        ])->saveQuietly();

        $invitation = $this->invoice->invitations()->first();
        $settings = $this->client->getMergedSettings();
        $settings->client_portal_allow_under_payment = $allow_under_payment;
        $settings->client_portal_allow_over_payment = $allow_over_payment;
        $settings->client_portal_under_payment_minimum = 1;

        Cache::put($invitation->key, [
            'contact' => $this->contact,
            'settings' => $settings,
            'payable_invoices' => [[
                'invoice_id' => $this->invoice->hashed_id,
                'amount' => 1000,
                'formatted_amount' => '1000.00',
            ]],
        ], now()->addHour());

        Livewire::test(UnderOverPayment::class, ['_key' => $invitation->key])
            ->call('checkValue', [[
                'invoice_id' => $this->invoice->hashed_id,
                'formatted_amount' => (string) $first_amount,
            ]])
            ->assertSet('errors', '')
            ->call('checkValue', [[
                'invoice_id' => $this->invoice->hashed_id,
                'formatted_amount' => (string) $revised_amount,
            ]])
            ->assertSet('errors', '');

        $this->assertEquals($revised_amount, Cache::get($invitation->key)['payable_invoices'][0]['amount']);
    }

    public static function revisedCustomAmounts(): array
    {
        return [
            'raise an underpayment while remaining below balance' => [true, false, 250, 500],
            'reduce an overpayment back to full balance' => [false, true, 1250, 1000],
        ];
    }
}
