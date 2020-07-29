<footer class="bg-white px-4 py-5 shadow px-4 sm:px-6 md:px-8 flex justify-center border border-gray-200 justify-between items-center">
    <span class="text-sm text-gray-700">{{ ctrans('texts.footer_label', ['year' => date('Y')])  }}</span>
    @if(auth()->user()->user && !auth()->user()->user->account->isPaid())
        <a href="https://invoiceninja.com" target="_blank">
            <img class="h-8" src="{{ asset('images/invoiceninja-black-logo-2.png') }}" alt="Invoice Ninja Logo">
        </a>
    @endif
</footer>