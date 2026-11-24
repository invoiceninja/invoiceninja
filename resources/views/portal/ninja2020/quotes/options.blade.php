@extends('portal.ninja2020.layout.app')

@section('meta_title', ctrans('texts.quote') . ' ' . $quote->number)

@section('body')
    <div x-data="portalDialog" class="flex flex-col gap-4">
        <template x-teleport="body">
            <dialog x-ref="dialog" class="portal-dialog portal-dialog-pdf" id="dialogPdf" aria-modal="true" aria-label="{{ ctrans('texts.view_quote') }}" data-error-message="{{ ctrans('texts.an_error_occurred_try_again') }}">
                <div class="portal-dialog-body">
                    @livewire('pdf-slot', ['entity_type' => 'quote', 'entity_key' => $quote->hashed_id, 'db' => $quote->company->db, 'with_close_button' => 'dialog#dialogPdf'])
                </div>
            </dialog>
        </template>

        <div class="flex justify-end">
            <button @click="open($event)" type="button" class="button button-primary bg-primary">
                {{ ctrans('texts.view_quote') }}
            </button>
        </div>

        @livewire('quote-options', ['quote_id' => $quote->hashed_id])
    </div>
@endsection
