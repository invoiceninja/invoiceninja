@extends('portal.ninja2020.layout.clean', ['custom_body_class' => 'bg-gray-100'])
@section('meta_title', ctrans('texts.register'))

@section('body')
    <div class="grid lg:grid-cols-12 py-8">
        <div class="col-span-12 lg:col-span-6 lg:col-start-4 xl:col-span-4 xl:col-start-5 px-6">
            <div class="flex justify-center">
                <img class="h-32 w-auto" src="{{ $company->present()->logo() }}" alt="{{ ctrans('texts.logo') }}">
            </div>
            <h1 class="text-center text-3xl mt-8">{{ ctrans('texts.register') }}</h1>
            <p class="block text-center text-gray-600">{{ ctrans('texts.register_label') }}</p>

            <form action="{{ route('client.register', request()->route('company_key')) }}" method="POST" x-data="{ more: false }">
                @csrf
                @include('portal.ninja2020.auth.includes.register.personal_information')

                <span class="block mt-4 text-gray-800 hover:text-gray-900 text-right cursor-pointer" x-on:click="more = !more">{{ ctrans('texts.more_fields') }}</span>

                <div x-show="more">
                    @include('portal.ninja2020.auth.includes.register.website')
                    @include('portal.ninja2020.auth.includes.register.personal_address')
                    @include('portal.ninja2020.auth.includes.register.shipping_address')
                </div>

                <div class="flex justify-between items-center mt-8">
                    <span class="inline-flex items-center" x-data="{ terms_of_service: false, privacy_policy: false }">
                            @if(!empty($company->settings->client_portal_terms) || !empty($company->settings->client_portal_privacy_policy))
                                <input type="checkbox" name="terms" class="form-checkbox mr-2 cursor-pointer" checked>
                                <span class="text-sm text-gray-800">

                                {{ ctrans('texts.i_agree') }}
                            @endif

                            @includeWhen(!empty($company->settings->client_portal_terms), 'portal.ninja2020.auth.includes.register.popup', ['property' => 'terms_of_service', 'title' => ctrans('texts.terms_of_service'), 'content' => $company->settings->client_portal_terms])
                            @includeWhen(!empty($company->settings->client_portal_privacy_policy), 'portal.ninja2020.auth.includes.register.popup', ['property' => 'privacy_policy', 'title' => ctrans('texts.privacy_policy'), 'content' => $company->settings->client_portal_privacy_policy])
                        </span>
                    </span>

                    <button class="button button-primary bg-blue-600">{{ ctrans('texts.register') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection
