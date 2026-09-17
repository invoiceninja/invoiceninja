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
use App\Livewire\Flow2\InvoicePay;
use App\Livewire\Flow2\ProcessPayment;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use App\Models\PaymentHash;
use App\Services\ClientPortal\InstantPayment;
use App\Services\ClientPortal\LivewireInstantPayment;
use App\Utils\Number;
use Livewire\Livewire;
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
 * ~4ms apart, each calling addGatewayFee and creating a PaymentHash -
 * resulting in a doubled gateway-fee line item and doubled client balance.
 *
 * That class of race is no longer reachable: gateway fees are quoted at
 * initiation and written only when a payment confirms, so concurrent
 * initialisations touch nothing on the invoice. The tests covering the old
 * Cache::lock arbitration have been removed with the mechanism; the invariants
 * they protected are covered by GatewayFeeConcurrencyTest.
 *
 * What remains here is Livewire component lifecycle - repeated mounts and
 * context events must not create additional PaymentHash rows.
 *
 * @see \Tests\Feature\GatewayFeeConcurrencyTest
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

    public function testInvoicePayDuplicatePaymentMethodSelectionDoesNotCreateAnotherPaymentHash(): void
    {
        $this->actingAs($this->contact, 'contact');

        $cg = $this->makeCompanyGateway();
        $cg->require_billing_address = false;
        $cg->require_shipping_address = false;
        $cg->save();

        $invitation = $this->invoice->invitations()->first();

        Livewire::test(InvoicePay::class, [
            'invoices' => [$this->invoice->hashed_id],
            'invitation_id' => $invitation->id,
            'db' => $this->company->db,
            'variables' => [],
        ])
            ->set('terms_accepted', true)
            ->set('signature_accepted', true)
            ->set('under_over_payment', false)
            ->set('required_fields', false)
            ->call('paymentMethodSelected', $cg->id, GatewayType::CREDIT_CARD, (string) $this->invoice->balance)
            ->set('required_fields', false)
            ->call('paymentMethodSelected', $cg->id, GatewayType::CREDIT_CARD, (string) $this->invoice->balance);

        $hashes = PaymentHash::query()
            ->where('fee_invoice_id', $this->invoice->id)
            ->whereNull('payment_id')
            ->get();

        $this->assertCount(1, $hashes, 'duplicate payment-method-selected events must not create another payment hash');
    }

    public function testInvoicePayParentRefreshAfterPaymentSelectionDoesNotCreateAnotherPaymentHash(): void
    {
        $this->actingAs($this->contact, 'contact');

        $cg = $this->makeCompanyGateway();
        $cg->require_billing_address = false;
        $cg->require_shipping_address = false;
        $cg->save();

        $invitation = $this->invoice->invitations()->first();

        Livewire::test(InvoicePay::class, [
            'invoices' => [$this->invoice->hashed_id],
            'invitation_id' => $invitation->id,
            'db' => $this->company->db,
            'variables' => [],
        ])
            ->set('terms_accepted', true)
            ->set('signature_accepted', true)
            ->set('under_over_payment', false)
            ->set('required_fields', false)
            ->call('paymentMethodSelected', $cg->id, GatewayType::CREDIT_CARD, (string) $this->invoice->balance)
            ->set('required_fields', false)
            ->refresh();

        $hashes = PaymentHash::query()
            ->where('fee_invoice_id', $this->invoice->id)
            ->whereNull('payment_id')
            ->get();

        $this->assertCount(1, $hashes, 'parent refresh after selecting a payment method must not remount ProcessPayment and create another hash');
    }

    public function testProcessPaymentUsesCurrentPayableInvoicesContextWhenItMounts(): void
    {
        $this->actingAs($this->contact, 'contact');

        $cg = $this->makeCompanyGateway();
        $cg->require_billing_address = false;
        $cg->require_shipping_address = false;
        $cg->save();

        $invitation = $this->invoice->invitations()->first();
        $payable_amount = Number::roundValue($this->invoice->balance / 2, $this->client->currency()->precision);

        Cache::put($invitation->key, [
            'db' => $this->company->db,
            'contact' => $this->contact,
            'company_gateway_id' => $cg->id,
            'gateway_type_id' => GatewayType::CREDIT_CARD,
            'payable_invoices' => [[
                'invoice_id' => $this->invoice->hashed_id,
                'amount' => $payable_amount,
            ]],
            'signature' => false,
            'signature_ip' => false,
        ], now()->addHour());

        Livewire::test(ProcessPayment::class, [
            '_key' => $invitation->key,
        ]);

        $hash = PaymentHash::query()
            ->where('fee_invoice_id', $this->invoice->id)
            ->whereNull('payment_id')
            ->latest('id')
            ->first();

        $this->assertNotNull($hash);
        $this->assertEquals($payable_amount, (float) data_get($hash->data, 'invoices.0.amount'));
    }

    public function testInvoiceSummaryContextEventsDoNotDisturbTheInvoiceOrPaymentHash(): void
    {
        $cg = $this->makeCompanyGateway();
        $response = (new LivewireInstantPayment($this->makePayload($cg)))->run();

        $this->assertTrue($response['success']);

        $invoice = $this->invoice->fresh();
        $fee_items = collect($invoice->line_items)->where('type_id', '3');
        $hashes = PaymentHash::query()
            ->where('fee_invoice_id', $invoice->id)
            ->whereNull('payment_id')
            ->get();

        /** The fee is quoted, not written - the invoice carries no pending fee line. */
        $this->assertCount(0, $fee_items);
        $this->assertCount(1, $hashes);

        $context_key = 'invoice-summary-propagation-' . $invoice->id;

        Cache::put($context_key, [
            'contact' => $this->contact,
            'payable_invoices' => [[
                'invoice_id' => $invoice->hashed_id,
                'number' => $invoice->number,
                'date' => $invoice->translateDate($invoice->date, $this->client->date_format(), $this->client->locale()),
                'due_date' => $invoice->due_date ? $invoice->translateDate($invoice->due_date, $this->client->date_format(), $this->client->locale()) : '',
                'formatted_currency' => Number::formatMoney($invoice->balance, $this->client),
            ]],
            'amount' => data_get($response, 'payload.total.amount_with_fee'),
            'gateway_fee' => data_get($response, 'payload.total.fee_total'),
            'db' => $this->company->db,
            'invitation_id' => $invoice->invitations()->first()?->id,
        ], now()->addHour());

        Livewire::test(\App\Livewire\Flow2\InvoiceSummary::class, ['_key' => $context_key])
            ->dispatch('payment-view-rendered')
            ->dispatch('secureContext.updated');

        $invoice = $this->invoice->fresh();
        $fee_items = collect($invoice->line_items)->where('type_id', '3');
        $hashes = PaymentHash::query()
            ->where('fee_invoice_id', $invoice->id)
            ->whereNull('payment_id')
            ->get();

        $this->assertCount(0, $fee_items, 'summary context events must not write a fee to the invoice');
        $this->assertCount(1, $hashes, 'summary context events must not create or remove PaymentHash rows');
    }
}
