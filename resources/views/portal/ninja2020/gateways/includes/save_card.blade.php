@php
    $gateway_instance = $gateway instanceof \App\Models\CompanyGateway ? $gateway : $gateway->company_gateway;
    $first_invoice = collect($invoices ?? [])->first();
    $recurring_invoice_id = data_get($first_invoice, 'recurring_invoice_id');
    $prepayment_recurring = isset($pre_payment) && $pre_payment == '1' && isset($is_recurring) && $is_recurring == '1';
@endphp

<div>
    @if($recurring_invoice_id !== null)
        @livewire('recurring-invoices.update-auto-billing', [
            'invoice_id' => $recurring_invoice_id,
            'checkout_invoice_id' => data_get($first_invoice, 'invoice_id'),
            'db' => $gateway_instance->company->db,
            'show_radios' => true,
            'token_billing_policy' => $gateway_instance->token_billing,
            'prepayment_recurring' => $prepayment_recurring,
        ], key('checkout-auto-billing-' . $gateway_instance->id . '-' . $recurring_invoice_id . '-' . data_get($first_invoice, 'invoice_id')))
    @else
        <div id="save-card--container">
            @include('portal.ninja2020.gateways.includes.save_payment_method', [
                'token_billing_policy' => $gateway_instance->token_billing,
                'force_save_card' => false,
                'prepayment_recurring' => $prepayment_recurring,
            ])
        </div>
    @endif
</div>
