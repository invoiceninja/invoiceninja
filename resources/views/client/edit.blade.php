@extends('layouts.master', ['header' => $header])

@section('body')
<main class="main" id="app">
    <!-- Breadcrumb-->
    {{ Breadcrumbs::render('clients.edit', $client) }}

<form>
    <div class="container-fluid">
        <div class="row form-group">
            <div class="col-md-12">
                <span class="float-right">
                    <div class="btn-group ml-2">
                        <button class="btn btn-lg btn-success" type="button"><i class="fa fa-save"></i> {{ trans('texts.save') }}</button>
                        <button class="btn btn-lg btn-success dropdown-toggle dropdown-toggle-split" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <span class="sr-only">Toggle Dropdown</span>
                        </button>
                        <div class="dropdown-menu">
                            <a class="dropdown-item" href="#"><i class="fa fa-plus-circle"></i> {{ trans('texts.add_contact') }}</a>
                                <div class="dropdown-divider"></div>
                                    <a class="dropdown-item" href="#">{{ trans('texts.archive_client') }}</a>
                                    <a class="dropdown-item" href="#">{{ trans('texts.delete_client') }}</a>
                        </div>
                    </div>            
                </span>
            </div>
        </div>
        
        <div class="row" id="client_edit">
            <!-- Client Details and Address Column -->
            <div class="col-md-6">
                				
				@include('client.partial.client_details', $client)                               
<!--
                <div class="card">
                    <div class="card-header bg-primary2">{{ trans('texts.address') }}</div>
                    	<div>
						<ul class="nav nav-tabs" role="tablist">
							<li class="nav-item">
								<a class="nav-link active" data-toggle="tab" href="#billing" role="tab" aria-controls="billing">{{ trans('texts.billing_address') }}</a>
							</li>
							<li class="nav-item">
								<a class="nav-link" data-toggle="tab" href="#shipping" role="tab" aria-controls="shipping">{{ trans('texts.shipping_address') }}</a>
							</li>
						</ul>
						<div class="tab-content">
							<div class="tab-pane active" id="billing" role="tabpanel">
								<button type="button" class="btn btn-sm btn-light" v-on:click="$emit('copy', 'copy_shipping')"> {{ trans('texts.copy_shipping') }}</button>
								@include('client.partial.client_location', ['location' => $client->primary_billing_location])
							</div>
							<div class="tab-pane" id="shipping" role="tabpanel">
								<button type="button" class="btn btn-sm btn-light" v-on:click="$emit('copy',' copy_billing')"> {{ trans('texts.copy_billing') }}</button>
							</div>
						</div>
					</div>	
                </div>
-->
            </div>
            <!-- End Client Details and Address Column -->

            <!-- Contact Details Column -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary2">{{ trans('texts.contact_information') }}
                        <span class="float-right">
                            <button type="button" class="btn btn-primary btn-sm"><i class="fa fa-plus-circle"></i> {{ trans('texts.add_contact') }}</button>
                        </span>
                    </div>
                    <!--
                    @foreach($client->contacts as $contact)
	                    @include('client.partial.contact_details', ['contact' => $contact])
	                @endforeach
	            -->
                </div>    
            </div>     
            <!-- End Contact Details Column --> 
        </div>      
    </div>
</form>

<script>
    new Vue({
        el : '#client_edit',
	    data: function () {
	        return {
	            'client': [],
	            'errors': [],
	        }
	    },
	    mounted() {
            console.log('Component mounted.')
            //this.getItinerary()
	    	this.client = {!! $client !!}
        },
	    beforeMount: function () {
	    	console.log('before mount')
	    },
	    created:function() {
	    	console.dir('created')
	    },
	    updated:function() {
	        console.dir('updated');
	    },
	    methods:{
		}
    });
</script>

</main>

@endsection