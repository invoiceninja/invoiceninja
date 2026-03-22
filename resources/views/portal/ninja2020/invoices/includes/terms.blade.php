<div x-data="{ open: true }" style="display: none;" id="displayTermsModal" class="fixed bottom-0 inset-x-0 px-4 pb-4 sm:inset-0 sm:flex sm:items-center sm:justify-center">
    <div x-show="open" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 transition-opacity">
        <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
    </div>

    <div x-show="open" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="bg-white rounded-lg px-4 pt-5 pb-4 overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full sm:p-6">
        <div class="">
            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                <h3 class="text-xl leading-6 font-medium text-gray-900">
                    {{ ctrans('texts.terms') }}
                </h3>
                <div class="mt-4 h-64 overflow-y-auto">
                    @foreach($entities as $entity)
                        <div class="mb-4">
                            <p class="text-sm leading-6 font-medium text-gray-500">{{ $entity_type }} {{ $entity->number }}:</p>
                            @if($variables && $entity->terms)
                                <h5 data-ref="entity-terms">{!! $entity->parseHtmlVariables('terms', $variables) !!}</h5>
                            @elseif($entity->terms)
                                <h5 data-ref="entity-terms" class="text-sm leading-5 text-gray-900">{!! $entity->terms !!}</h5>
                            @else
                                <i class="text-sm leading-5 text-gray-500">{{ ctrans('texts.not_specified') }}</i>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
            <div class="flex w-full rounded-md shadow-sm sm:ml-3 sm:w-auto" x-data>
                <button
                    type="button"
                    id="accept-terms-button"
                    onclick="setTimeout(() => this.disabled = true, 0); setTimeout(() => this.disabled = false, 5000); return true;"
                    class="button button-primary bg-primary">
                    {{ ctrans('texts.i_agree') }}
                </button>
            </div>
            <div class="mt-3 flex w-full rounded-md shadow-sm sm:mt-0 sm:w-auto" x-data>
                <button @click="document.getElementById('displayTermsModal').style.display = 'none';" type="button" class="button button-secondary" id="close-terms-button">
                    {{ ctrans('texts.close') }}
                </button>
            </div>
        </div>
    </div>
</div>

@push('footer')
    @vite('resources/js/clients/linkify-urls.js')
@endpush
