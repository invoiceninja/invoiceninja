@extends('portal.default.layouts.master')
@section('header')
    @parent
    <link href="/vendors/css/bootstrap-combobox.css" rel="stylesheet">
@stop
@section('body')
    <main class="main">
        <div class="container-fluid">

			<div class="row" style="padding-top: 30px;">
			
				<div class="col-sm-3" style="padding-bottom: 10px;">

                    {!! Former::framework('TwitterBootstrap4'); !!}

                    {!! Former::horizontal_open()
                          ->id('update_contact')
                          ->route('client.profile.update', auth()->user()->hashed_id)
                          ->method('PUT');	!!}

                    <div class="card">

                        <div class="card-header">
                        
                            <strong>{{ ctrans('texts.avatar') }}</strong>
                        </div>

                        <div class="card-body">



                        </div>

                    </div>

                </div>

                <div class="col-sm-9" style="padding-bottom: 10px;">
                    
                    <div class="card">

                        <div class="card-header">
                        
                            <strong> {{ ctrans('texts.client_information') }}</strong>

                        </div>

                        <div class="card-body">
                        
                        {!! Former::text('name')->placeholder( ctrans('texts.first_name'))->label('') !!}
                        {!! Former::text('phone')->placeholder( ctrans('texts.phone'))->label('') !!}
                        {!! Former::text('website')->placeholder( ctrans('texts.website'))->label('') !!}

                        {!! Former::text('address1')->placeholder( ctrans('texts.address1'))->label('') !!}
                        {!! Former::text('address2')->placeholder( ctrans('texts.address2'))->label('') !!}
                        {!! Former::text('city')->placeholder( ctrans('texts.city'))->label('') !!}
                        {!! Former::text('state')->placeholder( ctrans('texts.state'))->label('') !!}
                        {!! Former::text('postal_code')->placeholder( ctrans('texts.postal_code'))->label('') !!}

                        {!! Former::select('country_id')
                            ->addOption('','')
                            ->autocomplete('off')
                            ->label('')
                            ->fromQuery($countries, 'name', 'id') !!}

                        {!! Former::text('shipping_address1')->placeholder( ctrans('texts.shipping_address1'))->label('') !!}
                        {!! Former::text('shipping_address2')->placeholder( ctrans('texts.shipping_address2'))->label('') !!}
                        {!! Former::text('shipping_city')->placeholder( ctrans('texts.shipping_city'))->label('') !!}
                        {!! Former::text('shipping_state')->placeholder( ctrans('texts.shipping_state'))->label('') !!}
                        {!! Former::text('shipping_postal_code')->placeholder( ctrans('texts.shipping_postal_code'))->label('') !!}

                        {!! Former::select('shipping_country_id')
                            ->addOption('','')
                            ->autocomplete('off')
                            ->label('')
                            ->fromQuery($countries, 'name', 'id') !!}

                        </div>


                    </div>

                    <div class="card">

                        <div class="card-header">
                        
                            <strong> {{ ctrans('texts.user_details') }}</strong>

                        </div>

                        <div class="card-body">
                        

                        {!! Former::text('first_name')->placeholder( ctrans('texts.first_name'))->label('') !!}

                        {!! Former::text('last_name')->placeholder( ctrans('texts.last_name'))->label('') !!}

                        {!! Former::text('email')->placeholder( ctrans('texts.email'))->label('') !!}

                        {!! Former::text('phone')->placeholder( ctrans('texts.phone'))->label('') !!}

                        </div>


                    </div>

                    {!! Former::close() !!}

                </div>

			</div>

        </div>
    </main>
</body>
@endsection
@push('scripts')
    <script src="/vendors/js/bootstrap-combobox.js"></script>
@endpush

@section('footer')

<script>

$(function() {
    $('#country_id, #shipping_country_id').combobox();

    });

$(document).ready(function() {

});  

</script>

@endsection