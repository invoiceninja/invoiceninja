<footer class="bg-white px-4 py-5 shadow px-4 sm:px-6 md:px-8 flex justify-center border border-gray-200 justify-between items-center" x-data="{ privacy: false, tos: false }">
    <section>
        <span class="text-xs md:text-sm text-gray-700">{{ ctrans('texts.footer_label', ['company' => auth('contact')->user() ? (auth('contact')->user()->user->account->isPaid() ? auth('contact')->user()->company->present()->name() : 'Invoice Ninja') : 'Invoice Ninja', 'year' => date('Y')])  }}</span>
        
        <div class="flex items-center space-x-2">
            @if(strlen($client->getSetting('client_portal_privacy_policy')) > 1)
                <a x-on:click="privacy = true; tos = false" href="#" class="button-link text-sm primary-color flex items-center">{{ __('texts.privacy_policy')}}</a>
            @endif

            @if(strlen($client->getSetting('client_portal_privacy_policy')) > 1 && strlen($client->getSetting('client_portal_terms')) > 1)
                <!-- <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-minus">
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg> Long dash between items. -->
            @endif

            @if(strlen($client->getSetting('client_portal_terms')) > 1)
                <a x-on:click="privacy = false; tos = true" href="#" class="button-link text-sm primary-color flex items-center">{{ __('texts.terms')}}</a>
            @endif
        </div>
    </section>

    @if(auth()->user()->user && !auth()->user()->user->account->isPaid())
        <a href="https://invoiceninja.com" target="_blank">
            <img class="h-8" src="{{ asset('images/invoiceninja-black-logo-2.png') }}" alt="Invoice Ninja Logo">
        </a>
    @endif

    @if(strlen($client->getSetting('client_portal_privacy_policy')) > 1)
        @component('portal.ninja2020.components.general.pop-up', ['title' => __('texts.privacy_policy') ,'show_property' => 'privacy'])
            {!! $client->getSetting('client_portal_privacy_policy') !!}
        @endcomponent
    @endif

    @if(strlen($client->getSetting('client_portal_terms')) > 1)
        @component('portal.ninja2020.components.general.pop-up', ['title' => __('texts.terms') ,'show_property' => 'tos'])
            {!! $client->getSetting('client_portal_terms') !!}
        @endcomponent
    @endif
</footer>
