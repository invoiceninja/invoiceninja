<div class="container mx-auto grid grid-cols-12 mb-4" data-ref="required-fields-container">
    <div class="col-span-12 lg:col-span-6 lg:col-start-4 overflow-hidden bg-white shadow rounded-lg">
        <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
            <h3 class="text-lg font-medium leading-6 text-gray-900">
                {{ ctrans('texts.required_payment_information') }}
            </h3>

            <p class="max-w-2xl mt-1 text-sm leading-5 text-gray-500">
                {{ ctrans('texts.required_payment_information_more') }}
            </p>
        </div>

        <form wire:submit.prevent="handleSubmit(Object.fromEntries(new FormData($event.target)))">
            @foreach($fields as $field)
                @component('portal.ninja2020.components.general.card-element', ['title' => $field['label']])
                    <input class="input w-full" type="{{ $field['type'] }}" name="{{ $field['name'] }}">

                    @if(session()->has('validation_errors') && array_key_exists($field['name'], session('validation_errors')))
                        <p class="mt-2 text-gray-900 border-red-300 px-2 py-1 bg-gray-100">{{ session('validation_errors')[$field['name']][0] }}</p>
                    @endif
                @endcomponent
            @endforeach

            @component('portal.ninja2020.components.general.card-element-single')
                <div class="flex flex-col items-end">
                    <button class="button button-primary bg-primary">
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
                document.querySelector('div[data-ref="required-fields-container"]').classList.add('hidden');

                document.querySelector('div[data-ref="gateway-container"]').classList.remove('opacity-25');
                document.querySelector('div[data-ref="gateway-container"]').classList.remove('pointer-events-none');
            });
        </script>
    @endif
</div>
