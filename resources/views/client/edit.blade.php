@extends('layouts.master')

@section('head')
    <link rel="stylesheet" href="//cdn.datatables.net/1.10.18/css/dataTables.bootstrap4.min.css">
    <script src="//cdn.datatables.net/1.10.18/js/jquery.dataTables.min.js"></script>
    <script src="//cdn.datatables.net/1.10.18/js/dataTables.bootstrap4.min.js"></script>
    <script async defer src="https://maps.googleapis.com/maps/api/js?key={{ config('ninja.google_maps_api_key') }}&callback=initMap" type="text/javascript"></script>
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

        <div class="row">
        <div class="col-lg-12">
        {{ html()->form('POST', route('signup.submit'))->open() }}

        <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link rounded-0 active" data-toggle="tab" href="#tab1" role="tab">
                    <i class="icon-calculator"></i> @lang('texts.details')
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link rounded-0" data-toggle="tab" href="#tab5" role="tab">
                    <i class="icon-microchip"></i> Details 2
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link rounded-0" data-toggle="tab" href="#tab2" role="tab">
                   <i class="icon-user"></i> @lang('texts.contacts')
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link rounded-0" data-toggle="tab" href="#tab3" role="tab">
                    <i class="icon-map"></i> @lang('texts.locations')
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link rounded-0" data-toggle="tab" href="#tab4" role="tab">
                    <i class="fa fa-heart-o"></i> @lang('texts.invoices')
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link rounded-0" data-toggle="tab" href="#tab6" role="tab">
                    <i class="fa fa-coffee"></i> @lang('texts.quotes')
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link rounded-0" data-toggle="tab" href="#tab7" role="tab">
                    @lang('texts.payments')
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link rounded-0" data-toggle="tab" href="#tab8" role="tab">
                    @lang('texts.invoices')
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link rounded-0" data-toggle="tab" href="#tab9" role="tab">
                    @lang('texts.credits')
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link rounded-0" data-toggle="tab" href="#tab10" role="tab">
                    @lang('texts.tasks')
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link rounded-0" data-toggle="tab" href="#tab11" role="tab">
                    @lang('texts.tickets')
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

                        @include('client.partial.client_location', ['location' => $client->primary_billing_location->first(), 'address' => 'Billing'])

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
                    <div class="row">
                        <div class="col-lg-6">
                            @include('client.partial.client_location',['location' => $location, 'address' => 'Shipping'])
                        </div>
                        <div class="col-lg-6">
                            @include('client.partial.map', ['location' => $location, 'address' => 'Shipping'])
                        </div>
                    </div>
                @endforeach
                @include('client.partial.map_js', ['locations' => $client->locations, 'address' => 'Shipping'])

            </div>
            <!-- Client Locations -->


            <div class="tab-pane p-3" id="tab4" role="tabpanel">
            </div>

            <div class="tab-pane p-3" id="tab5" role="tabpanel">
                <div class="row">

                    @include('client.partial.client_details', $client)

                    @include('client.partial.client_stats')

                    @include('client.partial.client_meta', $client)

                </div>

            </div>

        </div>
        </div>
    </div>
</div>

    {{ html()->form()->close() }}
</main>



    @include('dashboard.aside')

@endsection

@section('footer')
    @include('footer')


@endsection











