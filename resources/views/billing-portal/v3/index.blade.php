@extends('portal.ninja2020.layout.clean')
@section('meta_title', ctrans('texts.purchase'))

@section('body')
    @livewire('billing-portal.purchase', ['subscription' => $subscription, 'db' => $subscription->company->db, 'hash' => $hash, 'request_data' => $request_data, 'campaign' => request()->query('campaign') ?? null])
@stop

@push('footer')
    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('purchase.submit', () => {
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
