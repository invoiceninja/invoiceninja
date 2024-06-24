@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.view_invoice'))

@push('head')
@endpush

@section('body')

    @if($invoice->isPayable() && $client->getSetting('custom_message_unpaid_invoice'))
        @component('portal.ninja2020.components.message')
            <pre>{{ $client->getSetting('custom_message_unpaid_invoice') }}</pre>
        @endcomponent
    @elseif($invoice->status_id === 4 && $client->getSetting('custom_message_paid_invoice'))
        @component('portal.ninja2020.components.message')
            <pre>{{ $client->getSetting('custom_message_paid_invoice') }}</pre>
        @endcomponent
    @endif

    @if($invoice->isPayable())
        @livewire('invoice-pay', ['invitation_id' => $invitation->id, 'db' => $invoice->company->db])
    @endif

    @include('portal.ninja2020.components.entity-documents', ['entity' => $invoice])

@endsection

@section('footer')
    <!-- @include('portal.ninja2020.invoices.includes.required-fields') -->
    <!-- @include('portal.ninja2020.invoices.includes.signature') -->
    <!-- @include('portal.ninja2020.invoices.includes.terms', ['entities' => [$invoice], 'variables' => $variables, 'entity_type' => ctrans('texts.invoice')]) -->
@endsection

@push('head')
@endpush
