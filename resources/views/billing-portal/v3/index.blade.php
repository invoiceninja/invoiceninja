@extends('portal.ninja2020.layout.clean')
@section('meta_title', ctrans('texts.purchase'))

@section('body')
    @livewire('billing-portal.purchase', ['subscription' => $subscription, 'db' => $subscription->company->db, 'hash' => $hash, 'request_data' => $request_data, 'campaign' => request()->query('campaign') ?? null])
@stop

@push('footer')
    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('purchase.submit', (event) => {
                document.querySelector('input[name="invoices[]"]').value = event.invoice_hashed_id;
                document.querySelector('input[name="payable_invoices[0][amount]"').value = event.payable_amount;
                document.querySelector('input[name="payable_invoices[0][invoice_id]"').value = event.invoice_hashed_id;
                document.querySelector('input[name=company_gateway_id]').value = event.company_gateway_id;
                document.querySelector('input[name=payment_method_id]').value = event.payment_method_id;
                document.querySelector('input[name=contact_first_name]').value = event.contact_first_name;
                document.querySelector('input[name=contact_last_name]').value = event.contact_last_name;
                document.querySelector('input[name=contact_email]').value = event.contact_email;

                setTimeout(() => {
                    document.getElementById('payment-method-form').submit()
                }, 2000);
            });

            const target = document.getElementById('container');

            const observer = new MutationObserver((mutationsList) => {
                for (const mutation of mutationsList) {
                    if (mutation.type === 'childList' || mutation.type === 'subtree') {
                        setTimeout(() => {
                            document.getElementById('spinner').classList.add('hidden');
                            document.getElementById('container').classList.remove('hidden');
                        }, 1500);
                    }
                }
            });

            observer.observe(target, { childList: true, subtree: true })
        });
    </script>
@endpush
