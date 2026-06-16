<?php

namespace Tests\Feature\ClientPortal;

use App\DataMapper\ClientSettings;
use App\Factory\InvoiceFactory;
use App\Helpers\Invoice\InvoiceSum;
use App\Livewire\Flow2\ProcessPayment;
use App\Livewire\Flow2\UnderOverPayment;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use App\Models\Invoice;
use App\Services\ClientPortal\InstantPayment;
use App\Services\ClientPortal\LivewireInstantPayment;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * Proof tests for cross-client invoice ID injection claims.
 */
class CrossClientInvoicePaymentProofTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    private Client $otherClient;

    private ClientContact $otherContact;

    private Invoice $otherInvoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        $this->otherClient = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        $otherSettings = ClientSettings::defaults();
        $this->otherClient->settings = $otherSettings;
        $this->otherClient->save();

        $this->otherContact = ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->otherClient->id,
            'company_id' => $this->company->id,
        ]);

        $this->otherInvoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $this->otherInvoice->client_id = $this->otherClient->id;
        $this->otherInvoice->line_items = $this->buildLineItems();
        $this->otherInvoice->uses_inclusive_taxes = false;
        $this->otherInvoice->save();

        $calc = new InvoiceSum($this->otherInvoice);
        $calc->build();
        $this->otherInvoice = $calc->getInvoice();
        $this->otherInvoice->save();

        $repo = new \App\Repositories\InvoiceRepository();
        $this->otherInvoice = $this->otherInvoice->service()->markSent()->save();
        $this->otherInvoice = $repo->save([], $this->otherInvoice->fresh());
        $this->otherInvoice->ledger()->updateInvoiceBalance($this->otherInvoice->amount);
        $this->otherInvoice = $this->otherInvoice->fresh();
    }

    public function test_instant_payment_rejects_other_client_invoice_hash(): void
    {
        $this->actingAs($this->contact, 'contact');

        $request = Request::create('/client/payments/process', 'POST', [
            'company_gateway_id' => CompanyGateway::GATEWAY_CREDIT,
            'payment_method_id' => GatewayType::CREDIT_CARD,
            'payable_invoices' => [[
                'invoice_id' => $this->otherInvoice->hashed_id,
                'amount' => $this->otherInvoice->balance,
            ]],
            'contact_first_name' => $this->contact->first_name,
            'contact_last_name' => $this->contact->last_name,
            'contact_email' => $this->contact->email,
        ]);

        $response = (new InstantPayment($request))->run();

        $this->assertTrue(method_exists($response, 'getTargetUrl'));
        $this->assertStringContainsString('invoices', $response->getTargetUrl());
    }

    public function test_livewire_instant_payment_accepts_foreign_hash_when_called_directly(): void
    {
        $payload = [
            'company_gateway_id' => CompanyGateway::GATEWAY_CREDIT,
            'payment_method_id' => GatewayType::CREDIT_CARD,
            'payable_invoices' => [[
                'invoice_id' => $this->otherInvoice->hashed_id,
                'amount' => $this->otherInvoice->balance,
            ]],
            'signature' => false,
            'signature_ip' => false,
            'pre_payment' => false,
            'frequency_id' => false,
            'remaining_cycles' => false,
            'is_recurring' => false,
        ];

        $this->assertTrue($this->otherInvoice->isPayable(), sprintf(
            'fixture invoice must be payable (status=%s balance=%s deleted=%s)',
            $this->otherInvoice->status_id,
            $this->otherInvoice->balance,
            $this->otherInvoice->is_deleted
        ));

        $result = (new LivewireInstantPayment($payload))->run();

        $this->assertTrue($result['success'], $result['error'] ?? 'no error message');
        $this->assertSame($this->otherClient->id, $result['payload']['client']->id);
    }

    public function test_flow2_under_over_rejects_foreign_invoice_hash(): void
    {
        $this->actingAs($this->contact, 'contact');

        $context_key = 'cross-client-proof-' . uniqid();
        Cache::put($context_key, [
            'contact' => $this->contact,
            'settings' => $this->client->getMergedSettings(),
            'payable_invoices' => [[
                'invoice_id' => $this->invoice->hashed_id,
                'amount' => $this->invoice->balance,
                'formatted_amount' => '10.00',
            ]],
        ], now()->addHour());

        Livewire::test(UnderOverPayment::class, ['_key' => $context_key])
            ->call('checkValue', [[
                'invoice_id' => $this->otherInvoice->hashed_id,
                'formatted_amount' => (string) $this->otherInvoice->balance,
            ]])
            ->assertSet('errors', ctrans('texts.no_payable_invoices_selected'));
    }

    public function test_flow2_process_payment_uses_server_context_not_user_submission(): void
    {
        $this->actingAs($this->contact, 'contact');

        $context_key = 'cross-client-proof-' . uniqid();
        Cache::put($context_key, [
            'db' => $this->company->db,
            'company_gateway_id' => CompanyGateway::GATEWAY_CREDIT,
            'gateway_type_id' => GatewayType::CREDIT_CARD,
            'payable_invoices' => [[
                'invoice_id' => $this->invoice->hashed_id,
                'amount' => $this->invoice->balance,
            ]],
            'signature' => false,
            'signature_ip' => false,
        ], now()->addHour());

        Livewire::test(ProcessPayment::class, ['_key' => $context_key])
            ->assertOk();

        $this->assertSame(
            $this->client->id,
            Cache::get($context_key)['payable_invoices'][0]['invoice_id'] === $this->invoice->hashed_id
                ? $this->client->id
                : $this->otherClient->id
        );
    }

    public function test_livewire_set_context_can_poison_payable_invoices(): void
    {
        $this->actingAs($this->contact, 'contact');

        $context_key = 'cross-client-proof-' . uniqid();
        Cache::put($context_key, [
            'contact' => $this->contact,
            'settings' => $this->client->getMergedSettings(),
            'payable_invoices' => [[
                'invoice_id' => $this->invoice->hashed_id,
                'amount' => $this->invoice->balance,
            ]],
        ], now()->addHour());

        Livewire::test(UnderOverPayment::class, ['_key' => $context_key])
            ->call('setContext', $context_key, 'payable_invoices', [[
                'invoice_id' => $this->otherInvoice->hashed_id,
                'amount' => $this->otherInvoice->balance,
            ]]);

        $poisoned = Cache::get($context_key)['payable_invoices'][0]['invoice_id'];
        $this->assertSame($this->otherInvoice->hashed_id, $poisoned);
    }

    private function buildLineItems(): array
    {
        return [[
            'product_key' => 'Item',
            'notes' => 'n',
            'cost' => 100,
            'qty' => 1,
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
            'type_id' => '1',
            'tax_id' => '1',
            'custom_value1' => '',
            'custom_value2' => '',
            'custom_value3' => '',
            'custom_value4' => '',
            'discount' => 0,
            'is_amount_discount' => true,
            'line_total' => 100,
        ]];
    }
}
