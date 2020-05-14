@extends('layouts.master', ['header' => $header])

@section('body')

<main class="main" id="client_edit">
    
    <vue-toastr ref="toastr"></vue-toastr>

    <div class="container-fluid">

        <div class="col" style="padding: 0px;">
            <ul class="nav nav-pills mb-1" id="pills-tab" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active show" id="pills-home-tab" data-toggle="pill" href="#pills-home" role="tab" aria-controls="pills-home" aria-selected="true"><i class="icon-user"></i> {{ ctrans('texts.client') }}</a>
                </li>

                @foreach($pills as $pill)
                    
                    <li class="nav-item">
                        <a class="nav-link" id="pills-{{ $pill['alias'] }}-tab" data-toggle="pill" href="#pills-{{ $pill['alias'] }}" role="tab" aria-controls="pills-{{ $pill['alias'] }}" aria-selected="false"><i class="icon-{{$pill['icon'] }}"></i> {{ $pill['name'] }}</a>
                    </li>

                @endforeach

                <li class="nav-item">
                    <a class="nav-link" id="pills-settings-tab" data-toggle="pill" href="#pills-settings" role="tab" aria-controls="pills-settings" aria-selected="false"><i class="icon-settings"></i> {{ ctrans('texts.settings') }}</a>
                </li>

            </ul>

        <div class="tab-content" id="pills-tabContent" style="margin-top:20px; background:#fff;">

            <div class="tab-pane fade active show" id="pills-home" role="tabpanel" aria-labelledby="pills-home-tab" style="background-color: #fff; padding: 0px;">

                <client-edit-form  :company="{{ $company }}" :clientdata="{{ $client }}" :hashed_id="'{{ $hashed_id }}'" :countries="{{ $countries }}"></client-edit-form>

            </div>

            @foreach($pills as $pill)

                <div class="tab-pane fade" id="pills-{{ $pill['alias'] }}" role="tabpanel" aria-labelledby="pills-{{ $pill['alias'] }}-tab">

                    @include($pill['alias'] . '::.edit')

                </div>
            
            @endforeach

            <div class="tab-pane fade" id="pills-settings" role="tabpanel" aria-labelledby="pills-settings-tab" style="background-color: #fff; padding: 0px;">

                <client-settings 
                    :client_settings="{{ $settings }}" 
                    :currencies="{{ $currencies }}"
                    :languages="{{ $languages }}"
                    :payment_terms="{{ $payment_terms }}"
                    :industries="{{ $industries }}"
                    :sizes="{{ $sizes }}"
                    :company="{{ $company }}"
                  >  
                </client-settings>


            </div>

        </div>

    </div>

</main>

<script src=" {{ mix('/js/client_edit.min.js') }}"></script>

@endsection