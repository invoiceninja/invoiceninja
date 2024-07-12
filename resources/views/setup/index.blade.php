@extends('setup.clean_setup')
@section('meta_title', ctrans('texts.setup'))

@section('body')
<div class="container mx-auto mb-10 mt-4">
    <form action="{{ url('/setup') }}" method="post">
        @csrf

        <div class="grid grid-cols-12 px-6">
            <div class="col-span-12 md:col-start-4 md:col-span-6 mt-4 md:mt-10">
                <h1 class="text-center text-2xl font-semibold">Invoice Ninja Setup</h1>
                <p class="text-sm text-center">{{ ctrans('texts.if_you_need_help') }}
                    <a 
                        target="_blank" 
                        href="https://forum.invoiceninja.com" 
                        class="button-link underline">
                        {{ ctrans('texts.support_forum') }}
                    </a>
                </p>

                @if($errors->any())
                    <div class="alert alert-failure">
                        <ul>
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if(session()->has('setup_error'))
                    <div class="alert alert-failure mt-4">
                        <span class="font-bold">{{ ctrans('texts.error_title') }}:</span>
                        <pre class="bg-white p-3 mt-2 rounded overflow-y-scroll">{{ session('setup_error') }}</pre>
                    </div>
                @endif

                @if($check['system_health'] === false)
                    @include('setup._issues')
                @else

                    @if(isset($check['npm_status']) && !$check['npm_status'])
                    <div class="alert alert-success mt-4">
                        <p>NPM Version => {{$check['npm_status']}}</p>
                    </div>
                    @endif

                    @if(isset($check['node_status']) && !$check['node_status'])
                    <div class="alert alert-success mt-4">
                        <p>Node Version => {{$check['node_status']}}</p>
                    </div>
                    @endif

                    @include('setup._database')
                    @include('setup._account')

                    <p class="mt-4 text-sm">{{ ctrans('texts.setup_steps_notice') }}</p>

                    <div class="flex justify-center mt-4 hidden" id="submit-wrapper">
                        <div class="flex flex-col">
                            <div class="mt-4 text-sm">
                                <label for="terms_of_service">
                                    <input type="checkbox" class="form-checkbox mr-2" name="terms_of_service" id="terms_of_service" required>
                                    <span>{{ ctrans('texts.i_agree') }}
                                        <a class="button-link text-blue-600" target="_blank" href="https://www.invoiceninja.com/self-hosting-terms-service/">{{ ctrans('texts.terms_of_service') }}</a>
                                    </span>
                                </label>
                            </div>
                            <div class="mt-2 text-sm">
                                <label for="privacy_policy">
                                    <input type="checkbox" class="form-checkbox mr-2" name="privacy_policy" id="privacy_policy" required>
                                    <span>{{ ctrans('texts.i_agree') }}
                                        <a class="button-link text-blue-600" target="_blank" href="https://www.invoiceninja.com/self-hosting-privacy-data-control/">{{ ctrans('texts.privacy_policy') }}</a>
                                    </span>
                                </label>
                            </div>

                            <button type="submit" class="button button-primary bg-blue-600 w-1/2 my-4">{{ ctrans('texts.submit') }}</button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </form>
</div>
@endsection

@push('footer')
    @vite('resources/js/setup/setup.js')
@endpush
