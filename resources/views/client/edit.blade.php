@extends('layouts.master', ['header' => $header])

@section('body')
<main class="main" id="client_edit">
    
    <!-- Breadcrumb-->
    {{ Breadcrumbs::render('clients.edit', $client) }}
    
    <vue-toastr ref="toastr"></vue-toastr>

    <div class="container-fluid">

        <div class="col" style="padding: 0px;">
            <ul class="nav nav-pills mb-1" id="pills-tab" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active show" id="pills-home-tab" data-toggle="pill" href="#pills-home" role="tab" aria-controls="pills-home" aria-selected="true"><i class="icon-user"></i> {{ trans('texts.client') }}</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" id="pills-settings-tab" data-toggle="pill" href="#pills-settings" role="tab" aria-controls="pills-settings" aria-selected="false"><i class="icon-settings"></i> {{ trans('texts.settings') }}</a>
                </li>

                @foreach($pills as $pill)
                    
                    <li class="nav-item">
                        <a class="nav-link" id="pills-{{ $pill['alias'] }}-tab" data-toggle="pill" href="#pills-{{ $pill['alias'] }}" role="tab" aria-controls="pills-{{ $pill['alias'] }}" aria-selected="false"><i class="icon-{{$pill['icon'] }}"></i> {{ $pill['name'] }}</a>
                    </li>

                @endforeach
            </ul>

        <div class="tab-content" id="pills-tabContent">

            <div class="tab-pane fade active show" id="pills-home" role="tabpanel" aria-labelledby="pills-home-tab" style="background-color: #e4e5e6; padding: 0px;">

                <client-edit-form :clientdata="{{ $client }}" :hashed_id="'{{ $hashed_id }}'"></client-edit-form>

            </div>

            @foreach($pills as $pill)

                <div class="tab-pane fade" id="pills-{{ $pill['alias'] }}" role="tabpanel" aria-labelledby="pills-{{ $pill['alias'] }}-tab">
                    {{$pill['name']}}
                </div>
            
            @endforeach

            <div class="tab-pane fade" id="pills-settings" role="tabpanel" aria-labelledby="pills-settings-tab">


            </div>

        </div>

    </div>

</main>

<script defer src=" {{ mix('/js/client_edit.min.js') }}"></script>

@endsection