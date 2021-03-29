@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.subscriptions'))

@section('body')
    <div class="flex flex-col">
        @livewire('subscription-invoices-table')
        @livewire('subscription-recurring-invoices-table')
    </div>
@endsection
