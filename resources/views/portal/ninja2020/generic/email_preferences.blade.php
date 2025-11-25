@extends('portal.ninja2020.layout.clean') @section('meta_title',
ctrans('texts.preferences')) @section('body')
<div class="flex h-screen" x-data="{ modalOpen: false, actionType: '' }">
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

            <form id="preferencesForm" class="my-4 flex flex-col items-center text-center" method="post">
                @csrf @method('put')
                <input type="hidden" name="action" id="actionInput">

                @if($receive_emails)
                    <p>{{ ctrans('texts.subscribe_help') }}</p>

                    <button
                        type="button"
                        @click="modalOpen = true; actionType = 'unsubscribe'"
                        class="button button-secondary mt-4"
                    >
                        {{ ctrans('texts.unsubscribe') }}
                    </button>
                @else
                    <p>{{ ctrans('texts.unsubscribe_help') }}</p>

                    <button
                        type="button"
                        @click="modalOpen = true; actionType = 'subscribe'"
                        class="button button-secondary mt-4"
                    >
                        {{ ctrans('texts.subscribe') }}
                    </button>
                @endif
            </form>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div x-show="modalOpen" class="fixed bottom-0 inset-x-0 px-4 pb-4 sm:inset-0 sm:flex sm:items-center sm:justify-center"
         style="display:none;">
        <div x-show="modalOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="fixed inset-0 transition-opacity" style="display:none;">
            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
        </div>

        <div x-show="modalOpen" x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             class="bg-white rounded-lg px-4 pt-5 pb-4 overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full sm:p-6">
            <div class="sm:flex sm:items-start">
                <div
                    class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                    <svg class="h-6 w-6 text-blue-600" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        <span x-show="actionType === 'unsubscribe'">{{ ctrans('texts.confirm') }}</span>
                        <span x-show="actionType === 'subscribe'">{{ ctrans('texts.confirm') }}</span>
                    </h3>
                    <div class="mt-2">
                        <p class="text-sm leading-5 text-gray-500">
                            <span>{{ ctrans('texts.are_you_sure') }}</span>
                        </p>
                    </div>
                </div>
            </div>
            <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                <button @click="document.getElementById('actionInput').value = actionType; document.getElementById('preferencesForm').submit();"
                        type="button" class="button button-danger sm:ml-3 sm:w-auto">
                    <span x-show="actionType === 'unsubscribe'">{{ ctrans('texts.unsubscribe') }}</span>
                    <span x-show="actionType === 'subscribe'">{{ ctrans('texts.subscribe') }}</span>
                </button>
                <button @click="modalOpen = false" type="button" class="button button-secondary button-block sm:mt-0 sm:w-auto sm:ml-3">
                    {{ ctrans('texts.cancel') }}
                </button>
            </div>
        </div>
    </div>
</div>
@stop
