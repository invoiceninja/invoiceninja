{{-- <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 flex items-center">
    <dt class="text-sm leading-5 font-medium text-gray-500 mr-4">
        {{ ctrans('texts.credit_card') }}
    </dt>
    <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
        {{ strtoupper($token->meta->brand) }} - **** {{ $token->meta->last4 }}
    </dd>
</div> --}}

@component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.credit_card')])
    {{ strtoupper($token->meta->brand) }} - **** {{ $token->meta->last4 }}
@endcomponent


{{-- <div class="bg-white px-4 py-5 flex justify-end">
    <button type="button" data-secret="{{ $intent->client_secret }}" data-token="{{ $token->token }}" id="pay-now-with-token" class="button button-primary bg-primary inline-flex items-center">
        <svg class="animate-spin h-5 w-5 text-white hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span>{{ __('texts.pay_now') }}</span>
    </button>
</div> --}}
