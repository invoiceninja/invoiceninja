@extends('portal.ninja2020.layout.clean')
@section('meta_title', ctrans('texts.setup'))

@section('body')
<div class="container mx-auto mb-10">
    <form action="#" method="post">
        @csrf

        <div class="grid grid-cols-12 px-6">
            <div class="col-span-12 md:col-start-4 md:col-span-6 mt-4 md:mt-10">
                <h1 class="text-center text-2xl font-semibold">Invoice Ninja Setup</h1>
                <p class="text-sm text-center">If you need help you can either post to our
                    <a href="https://www.invoiceninja.com/forums/forum/support/" class="button-link">support forum</a>
                    or email us at <a href="mailto:contact@invoiceninja.com" class="button-link">contact@invoiceninja.com</a>.
                </p>

                @include('setup._application')
                @include('setup._database')
                @include('setup._mail')
                @include('setup._account')

                <div class="flex justify-center mt-4">
                    <div class="flex flex-col">
                        <div class="mt-4">
                            <input type="checkbox" class="form-checkbox" name="terms" required>
                            <span>I agree to
                                <a class="button-link" href="https://www.invoiceninja.com/self-hosting-terms-service/">{{ ctrans('texts.terms_of_service') }}</a>
                            </span>
                        </div>
                        <div class="mt-2">
                            <input type="checkbox" class="form-checkbox" name="privacy" required>
                            <span>I agree to
                                <a class="button-link" href="https://www.invoiceninja.com/self-hosting-privacy-data-control/">{{ ctrans('texts.privacy_policy') }}</a>
                            </span>
                        </div>
                        <button type="submit" class="button button-primary w-1/2 my-4">{{ ctrans('texts.submit') }}</button>
                    </div>
                </div>

            </div>
        </div>
    </form>
</div>
@endsection