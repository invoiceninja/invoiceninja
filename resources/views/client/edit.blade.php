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
	    	this.client = {!! $client !!}
        },
	    beforeMount: function () {
	    	console.log('before mount')
	    },
	    created:function() {
	    	console.dir('created')
	    },
	    updated:function() {
	        console.dir('updated')
	    },
	    methods:{
            remove(contact){
                let index = this.client.contacts.indexOf(contact)
                this.client.contacts.splice(index, 1)
            },
            add(){
                console.dir('i will add a contact here')
                this.client.contacts.push({first_name: '', last_name: '', email: '', phone: ''})
                window.scrollTo(0, document.body.scrollHeight || document.documentElement.scrollHeight)
                this.$nextTick(() => {
                         let index = this.client.contacts.length - 1
                         let input = this.$refs.first_name[index]
                         input.focus();
                      });
            },
            submit() {
                this.errors = {};
                
                axios.put('/clients/' + {{ $client->present()->id }}, this.client).then(response => {
                    this.client = response.data;
                }).catch(error => {
                    if (error.response.status === 422) {
                    this.errors = error.response.data.errors || {};
                    }
                    else if(error.response.status === 419) {
                        //csrf token has expired, we'll need to force a page reload
                    }
                });
            },
	    	copy(type) {
	            if(type.includes('copy_billing')){
	                this.client.shipping_address1 = this.client.address1; 
	                this.client.shipping_address2 = this.client.address2; 
	                this.client.shipping_city = this.client.city; 
	                this.client.shipping_state = this.client.state; 
	                this.client.shipping_postal_code = this.client.postal_code; 
	                this.client.shipping_country_id = this.client.country_id; 
	            	}else {
	                this.client.address1 = this.client.shipping_address1; 
	                this.client.address2 = this.client.shipping_address2; 
	                this.client.city = this.client.shipping_city; 
	                this.client.state = this.client.shipping_state; 
	                this.client.postal_code = this.client.shipping_postal_code; 
	                this.client.country_id = this.client.shipping_country_id; 
	            }
	        }
		}
    });
</script>

</main>

@endsection