@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.invoices'))

@section('header')

    @if($errors->any())
        <div class="alert alert-failure mb-4">
            @foreach($errors->all() as $error)
            <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <div class="bg-white shadow rounded mb-4">
        <div class="px-4 py-5 sm:p-6">
            <div class="sm:flex sm:items-start sm:justify-between">
                <div>
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        {{ ctrans('texts.invoices') }}
                    </h3>
                    <div class="mt-2 max-w-xl text-sm leading-5 text-gray-500">
                        <p>
                            {{ ctrans('texts.list_of_invoices') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('body')
    <div class="flex justify-end items-center">
        <span class="text-sm mr-2">{{ ctrans('texts.with_selected') }}:</span>
        <form action="{{ route('client.invoices.bulk') }}" method="post" id="bulkActions">
            @csrf
            <button type="submit" class="button button-primary" name="action" value="download">{{ ctrans('texts.download') }}</button>
            <button type="submit" class="button button-primary" name="action" value="payment">{{ ctrans('texts.pay_now') }}</button>
        </form>
    </div>
    <div class="flex flex-col mt-4">
        @livewire('invoices-table')
    </div>
@endsection
