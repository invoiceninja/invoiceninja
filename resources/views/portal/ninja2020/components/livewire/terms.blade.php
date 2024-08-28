<div class="pb-8">
    <div class="bg-white p-6 rounded-lg shadow-lg">
        <h2 class="text-xl text-center py-0 px-4">{{ ctrans('texts.terms') }}</h2>
        <div class="mt-0 h-64 overflow-y-auto">
            <div class="py-0">
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
        <div class="flex justify-end items-center px-4 py-4">
            <button id="accept-terms-button" class="button button-primary bg-primary hover:bg-primary-darken float-end">{{ ctrans('texts.next') }}</button>
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