@extends('migration.layout.main', [
    'step_title' => 'Companies',
    'step_description' => 'Let\'s see what to do with your data.'
])

@section('body')
    <div class="flex justify-center my-5">
        <div class="w-1/2">

            @include('migration.includes.message')

            Companies page

        </div>
    </div>
@stop
