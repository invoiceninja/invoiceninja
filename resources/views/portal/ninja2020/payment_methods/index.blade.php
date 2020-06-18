@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.payment_methods'))

@section('header')
    <div class="mt-5 sm:mt-0 sm:ml-6 sm:flex-shrink-0 sm:flex sm:items-center">
        <div class="inline-flex rounded-md shadow-sm">
            
        </div>
    </div>
@endsection

@section('body')
    <div class="flex flex-col">
        @livewire('payment-methods-table', ['client' => $client])
    </div>
@endsection