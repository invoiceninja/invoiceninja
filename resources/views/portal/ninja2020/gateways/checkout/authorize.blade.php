@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.checkout_com'))

@section('body')
    <div class="container mx-auto">
        <div class="grid grid-cols-6 gap-4">
            <div class="col-span-6 md:col-start-2 md:col-span-4">
                <div class="alert alert-failure mb-4" hidden id="errors"></div>
                <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                    <div class="bg-white px-4 py-5 sm:px-6 flex items-center">
                        <dt class="text-sm leading-5 font-medium text-gray-500 mr-4">
                            {{ ctrans('texts.checkout_authorize_label') }}
                        </dt>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection