@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.quotes'))

@section('header')
    @if($errors->any())
        <div class="alert alert-failure mb-4">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif
@endsection

@section('body')
    <div class="flex justify-between items-center">
        <form action="{{ route('client.quotes.bulk') }}" method="post" id="bulkActions">
            @csrf
            <button type="submit" class="button button-primary bg-primary" name="action"
                    value="download">{{ ctrans('texts.download') }}</button>
            <button type="submit" class="button button-primary bg-primary" name="action"
                    value="approve">{{ ctrans('texts.approve') }}</button>
        </form>
    </div>
    <div class="flex flex-col mt-4">
        @livewire('quotes-table')
    </div>
@endsection