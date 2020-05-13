@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.recurring_invoices'))

@section('header')
    <div class="bg-white shadow rounded mb-4" translate>
        <div class="px-4 py-5 sm:p-6">
            <div class="sm:flex sm:items-start sm:justify-between">
                <div>
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        {{ ctrans('texts.recurring_invoices') }}
                    </h3>
                    <div class="mt-2 max-w-xl text-sm leading-5 text-gray-500">
                        <p>
                            {{ ctrans('texts.list_of_recurring_invoices') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('body')
    <div class="flex flex-col">
        @livewire('recurring-invoices-table')
    </div>
@endsection
