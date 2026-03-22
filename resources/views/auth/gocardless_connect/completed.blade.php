@extends('layouts.ninja')
@section('meta_title', ctrans('texts.success'))

@section('body')
<div class="flex flex-col justify-center items-center mt-10">
    <div class="mb-4">
        <svg height="60" version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg"
            xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 1000 1000"
            style="enable-background:new 0 0 1000 1000;" xml:space="preserve">
            <style type="text/css">
                .st0 {
                    fill: #F1F252;
                }

                .st1 {
                    fill: #1C1B18;
                }
            </style>
            <circle class="st0" cx="500" cy="500" r="500" />
            <path class="st1" d="M507.9,242.1c55.2,0,86.2,9,86.2,9l91.7,187.2l-0.8,0.8l-118-70.4c-68.4-40.7-118-62.1-158.4-60.5
	c-42.7,0.8-68.3,35.2-68.3,85c1.5,127.5,122.7,284.5,243.1,284.5c49.1,0,74.7-15.8,89.7-34.9L494.8,447.3v-0.8h244.8
	c3.3,17.5,5.2,35.3,5.4,53.1c0,143.1-109.5,258.3-244.6,258.3C364.7,757.9,255,642.7,255,499.6C254.8,357.3,364.3,242.1,507.9,242.1
	z" />
        </svg>
    </div>

    <p>Connecting your account using GoCardless has been successfully completed.</p>
    <span>Click <a class="font-semibold hover:underline" href="{{ url('/#/settings/company_gateways') }}">here</a> to
        continue.</span>
</div>
@endsection