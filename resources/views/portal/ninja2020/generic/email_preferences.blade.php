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
                {{ ctrans('texts.email_preferences') }}
            </h1>

            <form class="my-4 flex flex-col items-center text-center" method="post">
                @csrf @method('put') 
                
                @if($receive_emails)
                    <p>{{ ctrans('texts.subscribe_help') }}</p>
                    
                    <button
                        name="action"
                        value="unsubscribe"
                        class="button button-secondary mt-4"
                    >
                        {{ ctrans('texts.unsubscribe') }}
                    </button>
                @else
                    <p>{{ ctrans('texts.unsubscribe_help') }}</p>

                    <button
                        name="action"
                        value="subscribe"
                        class="button button-secondary mt-4"
                    >
                        {{ ctrans('texts.subscribe') }}
                    </button>
                @endif
            </form>
        </div>
    </div>
</div>
@stop
