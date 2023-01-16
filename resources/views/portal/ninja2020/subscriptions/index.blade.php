@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.subscriptions'))

@section('body')
    <div class="flex flex-col">
        @livewire('subscriptions-table', ['company' => $company])
    </div>
@endsection
