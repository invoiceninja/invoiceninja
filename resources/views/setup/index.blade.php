@extends('portal.ninja2020.layout.clean')
@section('meta_title', ctrans('texts.setup'))

@section('body')
<div class="container mx-auto mt-4 mb-10">
    <form action="{{ url('/setup') }}" method="post">
        @csrf

        <div class="grid grid-cols-12 px-6">
            <div class="col-span-12 mt-4 md:col-start-4 md:col-span-6 md:mt-10">
                <h1 class="text-2xl font-semibold text-center">Invoice Ninja Setup</h1>
                <p class="text-sm text-center">{{ ctrans('texts.if_you_need_help') }}
                    <a href="https://www.invoiceninja.com/forums/forum/support/" class="button-link">{{ ctrans('texts.support_forum') }}</a>
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
                    <div class="mt-4 alert alert-failure">
                        <span class="font-bold">{{ ctrans('texts.error_title') }}:</span>
                        <pre class="p-3 mt-2 overflow-y-scroll bg-white rounded">{{ session('setup_error') }}</pre>
                    </div>
                @endif

                @if($check['system_health'] === false)
                    @include('setup._issues')
                @else

                    @include('setup._application')
                    @include('setup._database')
                    @include('setup._mail')
                    @include('setup._account')

                    <p class="mt-4 text-sm">{{ ctrans('texts.setup_steps_notice') }}</p>

                    <div class="flex justify-center hidden mt-4" id="submit-wrapper">
                        <div class="flex flex-col">
                            <div class="mt-4 text-sm">
                                <input type="checkbox" class="mr-2 form-checkbox" name="terms_of_service" required>
                                <span>{{ ctrans('texts.i_agree') }}
                                    <a class="button-link" href="https://www.invoiceninja.com/self-hosting-terms-service/">{{ ctrans('texts.terms_of_service') }}</a>
                                </span>
                            </div>
                            <div class="mt-2 text-sm">
                                <input type="checkbox" class="mr-2 form-checkbox" name="privacy_policy" required>
                                <span>{{ ctrans('texts.i_agree') }}
                                    <a class="button-link" href="https://www.invoiceninja.com/self-hosting-privacy-data-control/">{{ ctrans('texts.privacy_policy') }}</a>
                                </span>
                            </div>

                            <button type="submit" class="w-1/2 my-4 bg-blue-600 button button-primary">{{ ctrans('texts.submit') }}</button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </form>
</div>
@endsection

@push('footer')
    <script src="{{ asset('js/setup/setup.js') }}"></script>
@endpush
