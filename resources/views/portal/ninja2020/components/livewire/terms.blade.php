<div>
    <div class="bg-color: white">
        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
            <h3 class="text-xl leading-6 font-medium text-gray-900">
                {{ ctrans('texts.terms') }}
            </h3>
            <div class="mt-4 h-64 overflow-y-auto">
                    <div class="mb-4">
                        <p class="text-sm leading-6 font-medium text-gray-500">{{ ctrans('texts.invoice') }} {{ $invoice->number }}:</p>
                        @if($variables && $invoice->terms)
                            <h5 data-ref="entity-terms">{!! $invoice->parseHtmlVariables('terms', $variables) !!}</h5>
                        @elseif($invoice->terms)
                            <h5 data-ref="entity-terms" class="text-sm leading-5 text-gray-900">{!! $invoice->terms !!}</h5>
                        @else
                            <i class="text-sm leading-5 text-gray-500">{{ ctrans('texts.not_specified') }}</i>
                        @endif
                    </div>
            </div>
        </div>
    </div>
    <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
        <div class="flex w-full rounded-md shadow-sm sm:ml-3 sm:w-auto" x-data>
            <button id="accept-terms-button" class="button button-primary bg-primary hover:bg-primary-darken inline-flex items-center">{{ ctrans('texts.next') }}</button>
        </div>
    </div>
    @script
    <script>
            
        document.addEventListener('DOMContentLoaded', function () {
            
            document.getElementById('accept-terms-button').addEventListener('click', function() {          
                $wire.dispatch('terms-accepted');
            });
             
        });

    </script>
    @endscript

</div>