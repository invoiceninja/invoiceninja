@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.view_invoice'))

@push('head')
@endpush

@section('body')

    @livewire('flow2.invoice-pay', ['invoices' => $invoices, 'invitation_id' => $invitation->id, 'db' => $db, 'variables' => $variables])

@endsection

@section('footer')
@endsection

@push('head')
@endpush
