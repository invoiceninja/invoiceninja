@extends('layouts.master', ['header' => $header])

@section('body')
<main class="main" id="client_edit">

    <!-- Breadcrumb-->
    {{ Breadcrumbs::render('clients.edit', $client) }}

<form @submit.prevent="submit">
    <div class="container-fluid">
        
        <div class="row">
            <!-- Client Details and Address Column -->
            <div class="col-md-6">
                				
				@include('client.partial.client_details', $client)                               

                @include('client.partial.client_location')

            </div>
            <!-- End Client Details and Address Column -->

            <!-- Contact Details Column -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary2">{{ trans('texts.contact_information') }}
                        <span class="float-right">
                            <button type="button" class="btn btn-primary btn-sm" @click="add()"><i class="fa fa-plus-circle"></i> {{ trans('texts.add_contact') }}</button>
                        </span>
                    </div>
                    
                    <template v-for="contact in client.contacts">
	                    @include('client.partial.contact_details')
                    </template>
	            
                </div>    
            </div>     
            <!-- End Contact Details Column --> 
        </div> 

        <div class="row"> 
            <div class="col-md-12 text-center">
                <button class="btn btn-lg btn-success" type="button" @click="submit"><i class="fa fa-save"></i> {{ trans('texts.save') }}</button>
            </div>
        </div>    

    </div>
</form>


</main>
<script>
    var client_object = {!! $client !!};
    var hashed_id = '{{ $hashed_id }}';
</script>

<script defer src=" {{ mix('/js/client_edit.min.js') }}"></script>
@endsection