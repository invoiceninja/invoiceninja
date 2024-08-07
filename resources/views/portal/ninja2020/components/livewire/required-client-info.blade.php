<div wire:ignore.self class="@unless($form_only) container mx-auto grid grid-cols-12 @endunless mb-4 transition ease-out duration-1000  h-500" data-ref="required-fields-container">
    <div class="col-span-12 lg:col-span-6 lg:col-start-4 overflow-hidden @unless($form_only) bg-white shadow rounded-lg @endunless">
        @unless($form_only)
            <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                <h3 class="text-lg font-medium leading-6 text-gray-900">
                    {{ ctrans('texts.required_payment_information') }}
                </h3>

                <p class="max-w-2xl mt-1 text-sm leading-5 text-gray-500">
                    {{ ctrans('texts.required_payment_information_more') }}
                </p>
            </div>
        @endunless  

        <form id="required-client-info-form" x-on:submit.prevent="$wire.handleSubmit(Object.fromEntries(new FormData(document.getElementById('required-client-info-form'))))">
            @foreach($fields as $field)
                @if(!array_key_exists('filled', $field))
                    @component('portal.ninja2020.components.general.card-element', ['title' => $field['label']])
                        @if($field['name'] == 'client_country_id' || $field['name'] == 'client_shipping_country_id')
                            <select id="client_country" class="input w-full form-select bg-white" name="{{ $field['name'] }}" wire:model="{{ $field['name'] }}">
                                <option value="none"></option>

                                @foreach($countries as $country)
                                    <option value="{{ $country->id }}">
                                        {{ $country->iso_3166_2 }} ({{ $country->getName() }})
                                    </option>
                                @endforeach
                            </select>
                        @else
                            <input class="input w-full" type="{{ $field['type'] ?? 'text' }}" name="{{ $field['name'] }}" wire:model="{{ $field['name'] }}">
                        @endif

                        @if(session()->has('validation_errors') && array_key_exists($field['name'], session('validation_errors')))
                            <p class="mt-2 text-gray-900 border-red-300 px-2 py-1 bg-gray-100">{{ session('validation_errors')[$field['name']][0] }}</p>
                        @endif
                    @endcomponent
                @endif
            @endforeach

            @if($this->showCopyBillingCheckbox())
                @component('portal.ninja2020.components.general.card-element-single')
                    <div class="flex justify-end">
                        <button type="button" class="bg-gray-100 px-2 py-1 text-sm rounded" wire:click="handleCopyBilling">
                            {{ ctrans('texts.copy_billing') }}
                        </button>
                    </div>
                @endcomponent
            @endif

            @if($show_terms)

                @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.terms_of_service') ])
                <div x-data="{ open: false }">
                <input
                    wire:click="toggleTermsAccepted()"
                    id="terms"
                    name="terms_accepted"
                    type="checkbox"
                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                  />
                <a href="#" class="group relative inline-block ml-4 text-blue-500 hover:text-red-500 duration-300 no-underline" @click="open = true">{{ ctrans('texts.agree_to_terms', ['terms' => ctrans('texts.terms')]) }}</a>



<div x-show="open" class="fixed bottom-0 inset-x-0 px-4 pb-4 sm:inset-0 sm:flex sm:items-center sm:justify-center z-50"
     style="display:none; background: rgba(0,0,0); background: transparent; z-index: 100;">
    <div x-show="open" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="fixed inset-0 transition-opacity" style="display:none;">
        <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
    </div>

    <div x-show="open"
         class="bg-white rounded-lg px-4 pt-5 pb-4 overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full sm:p-6">
        <div class="sm:flex sm:items-start">
            <div
                class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                <svg class="h-6 w-6 text-red-600" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                <h3 class="text-lg leading-6 font-medium text-gray-900" translate>
                    {{ ctrans('texts.terms_of_service' )}}
                </h3>
                <div class="mt-2">
                    <p class="text-sm leading-5 text-gray-500 bg-opacity-100">
                        {!! nl2br($invoice_terms) !!}
                    </p>
                </div>
            </div>
        </div>
        <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
            <div class="mt-3 flex w-full rounded-md shadow-sm sm:mt-0 sm:w-auto">
                <button @click="open = false" type="button" class="button button-secondary button-block">
                    {{ ctrans('texts.close') }}
                </button>
            </div>
        </div>
    </div>
</div>



                </div>

                @endcomponent


            @endif

            @component('portal.ninja2020.components.general.card-element-single')
                <div class="flex flex-col items-end">
                    <button class="button button-primary bg-primary"  {{ $terms_accepted ? '' : 'disabled' }}>
                        {{ trans('texts.continue') }}
                    </button>
                    <small class="mt-1 text-gray-800">{{ ctrans('texts.required_client_info_save_label') }}</small>
                </div>
            @endcomponent
        </form>
    </div>

    @if(!$show_form)
        <script>
            document.addEventListener("DOMContentLoaded", function () {
                // document.querySelector('div[data-ref="required-fields-container"]').classList.add('hidden');
                document.querySelector('div[data-ref="gateway-container"]').classList.remove('opacity-25');
                document.querySelector('div[data-ref="gateway-container"]').classList.remove('pointer-events-none');
            });
        </script>
    @endif

</div>
