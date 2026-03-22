@extends('layouts.ninja')
@section('meta_title', ctrans('texts.success'))

@section('body')
<div class="flex flex-col justify-center items-center mt-10">
    <div class="mb-4">
        <svg height="60" viewBox="0 0 56 56" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect width="56" height="56" rx="8" fill="#006AFF"/>
            <path d="M36.65 14H19.35C16.39 14 14 16.39 14 19.35V36.65C14 39.61 16.39 42 19.35 42H36.65C39.61 42 42 39.61 42 36.65V19.35C42 16.39 39.61 14 36.65 14ZM37 34.2C37 35.75 35.75 37 34.2 37H21.8C20.25 37 19 35.75 19 34.2V21.8C19 20.25 20.25 19 21.8 19H34.2C35.75 19 37 20.25 37 21.8V34.2Z" fill="white"/>
        </svg>
    </div>

    <p>Connecting your account using Square has been successfully completed.</p>
    <span>Click <a class="font-semibold hover:underline" href="{{ url($url ?? '/#/settings/company_gateways') }}">here</a> to
        continue.</span>
</div>
@endsection