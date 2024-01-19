@extends('portal.ninja2020.layout.clean') @section('meta_title',
ctrans('texts.preferences')) @section('body')
<div class="flex h-screen">
    <div class="m-auto md:w-1/3 lg:w-1/5">
        <div class="flex flex-col items-center">
            <img
                src="{{ $company->present()->logo() }}"
                class="border-gray-100 h-18 pb-4"
                alt="{{ $company->present()->name() }}"
            />
            <h1 class="text-center text-2xl mt-10">
                {{ ctrans('texts.email_settings') }}
            </h1>

            <form class="my-4" method="post">
                @csrf @method('put')

                <label for="receive_emails">
                    <input type="checkbox" name="receive_emails"
                    id="receive_emails"
                    {{ $receive_emails ? 'checked' : '' }} />

                    <span>
                        {{ ctrans('texts.resubscribe') }}
                    </span>
                </label>

                <div class="block my-4">
                    <button class="button button-secondary button-block">
                        {{ ctrans('texts.save') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@stop
