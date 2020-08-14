@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.downloads'))

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
    @livewire('downloads-table')
@endsection