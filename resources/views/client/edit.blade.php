@extends('layouts.master')

@section('head')
    <link rel="stylesheet" href="//cdn.datatables.net/1.10.18/css/dataTables.bootstrap4.min.css">
    <script src="//cdn.datatables.net/1.10.18/js/jquery.dataTables.min.js"></script>
    <script src="//cdn.datatables.net/1.10.18/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://maps.googleapis.com/maps/api/js?key={{ config('ninja.google_maps_api_key') }}"></script>
@endsection

@section('header')
    @include('header', $header)

    @parent
@endsection


@section('sidebar')
    @include('sidebar')
@endsection

@section('body')
    <main class="main" >
        <!-- Breadcrumb-->
        {{ Breadcrumbs::render('clients.edit', $client) }}

        <div class="container-fluid">

            {{ html()->form('POST', route('signup.submit'))->open() }}

            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link rounded-0 active" data-toggle="tab" href="#tab1" role="tab">
                        @lang('texts.details')
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-0" data-toggle="tab" href="#tab2" role="tab">
                        @lang('texts.contacts')
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-0" data-toggle="tab" href="#tab3" role="tab">
                        @lang('texts.locations')
                    </a>
                </li>
            </ul>

            <!-- Tab panes-->
            <div class="tab-content">

                <!-- Client Details-->
                <div class="tab-pane p-3 active" id="tab1" role="tabpanel">

                    <div class="row">

                        @include('client.partial.client_details', $client)

                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header">@lang('texts.billing_address')</div>

                                <div class="card-body">

                                    @include('client.partial.client_location', ['location' => $client->primary_billing_location->first()])

                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        @include('client.partial.map', ['location' => $client->primary_billing_location->first()])
                    </div>
                </div>
                <!-- Client Details-->

                <!-- Contact Details-->
                <div class="tab-pane p-3" id="tab2" role="tabpanel">
                    @foreach($client->contacts as $contact)
                        @include('client.partial.contact_details', ['contact' => $contact])
                    @endforeach
                </div>
                <!-- Contact Details-->

                <!-- Client Locations -->
                <div class="tab-pane p-3" id="tab3" role="tabpanel">

                    @foreach($client->locations as $location)
                        @include('client.partial.client_location',['location' => $location])
                    @endforeach
                </div>
                <!-- Client Locations -->

            </div>

        </div>

        {{ html()->form()->close() }}
    </main>



    @include('dashboard.aside')

@endsection

@section('footer')
    @include('footer')

@endsection











