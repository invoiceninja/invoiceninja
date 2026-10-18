@component('portal.ninja2020.components.dialog', ['id' => 'displayTermsModal', 'title' => ctrans('texts.terms')])
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
    @slot('actions')
        <button type="button" id="accept-terms-button" class="button button-primary bg-primary">{{ ctrans('texts.i_agree') }}</button>
        <button type="button" data-dialog-close id="close-terms-button" class="button button-secondary">{{ ctrans('texts.close') }}</button>
    @endslot
@endcomponent

@push('footer')
    @vite('resources/js/clients/linkify-urls.js')
@endpush
