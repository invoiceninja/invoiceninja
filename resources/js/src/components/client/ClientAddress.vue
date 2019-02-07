<template>
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
				<div class="card-body">
				    <div class="form-group row">
				        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.address1') }}</label>
				        <div class="col-sm-9">
				            <input type="text" :placeholder="trans('texts.address1')" v-model="client.address1" class="form-control">
                 			<div v-if="client.errors.has('address1')" class="text-danger" v-text="client.errors.get('address1')"></div>
				        </div>
				    </div>

				    <div class="form-group row">
				        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.address2') }}</label>
				        <div class="col-sm-9">
				            <input type="text":placeholder="trans('texts.address2')" v-model="client.address2" class="form-control">
                 			<div v-if="client.errors.has('address2')" class="text-danger" v-text="client.errors.get('address2')"></div>
				        </div>
				    </div>

				    <div class="form-group row">
				        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.city') }}</label>
				        <div class="col-sm-9">
				            <input type="text":placeholder="trans('texts.city')" v-model="client.city" class="form-control">
                 			<div v-if="client.errors.has('city')" class="text-danger" v-text="client.errors.get('city')"></div>
				        </div>
				    </div>

				    <div class="form-group row">
				        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.state') }}</label>
				        <div class="col-sm-9">
				            <input type="text" :placeholder="trans('texts.state')" v-model="client.state" class="form-control">
                 			<div v-if="client.errors.has('state')" class="text-danger" v-text="client.errors.get('state')"></div>
				        </div>
				    </div>

				    <div class="form-group row">
				        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.postal_code') }}</label>
				        <div class="col-sm-9">
				            <input type="text" :placeholder="trans('texts.postal_code')" v-model="client.postal_code" class="form-control">
                 			<div v-if="client.errors.has('postal_code')" class="text-danger" v-text="client.errors.get('postal_code')"></div>
				        </div>
				    </div>

				    <div class="form-group row">
				        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.country') }}</label>
				        <div class="col-sm-9">
			            	<multiselect v-model="billingCountry" :options="options" :placeholder="trans('texts.country')" label="name" track-by="id" @input="onChangeBilling"></multiselect>
                 			<div v-if="client.errors.has('country_id')" class="text-danger" v-text="client.errors.get('country_id')"></div>
				        </div>
				    </div>
				</div>	
			</div>
			<div class="tab-pane" id="shipping" role="tabpanel">
				<button type="button" class="btn btn-sm btn-light" v-on:click="$emit('copy',' copy_billing')"> {{ trans('texts.copy_billing') }}</button>
				<div class="form-group row">
				        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.address1') }}</label>
				        <div class="col-sm-9">
				            <input type="text" :placeholder="trans('texts.address1')" v-model="client.shipping_address1" class="form-control">
                 			<div v-if="client.errors.has('shipping_address1')" class="text-danger" v-text="client.errors.get('shipping_address1')"></div>
				        </div>
				    </div>

				    <div class="form-group row">
				        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.address2') }}</label>
				        <div class="col-sm-9">
				            <input type="text" :placeholder="trans('texts.address2')" v-model="client.shipping_address2" class="form-control">
                 			<div v-if="client.errors.has('shipping_address2')" class="text-danger" v-text="client.errors.get('shipping_address2')"></div>
				        </div>
				    </div>

				    <div class="form-group row">
				        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.city') }}</label>
				        <div class="col-sm-9">
				            <input type="text" :placeholder="trans('texts.city')" v-model="client.shipping_city" class="form-control">
                 			<div v-if="client.errors.has('shipping_city')" class="text-danger" v-text="client.errors.get('shipping_city')"></div>
				        </div>
				    </div>

				    <div class="form-group row">
				        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.state') }}</label>
				        <div class="col-sm-9">
				            <input type="text" :placeholder="trans('texts.state')" v-model="client.shipping_state" class="form-control">
                 			<div v-if="client.errors.has('shipping_state')" class="text-danger" v-text="client.errors.get('shipping_state')"></div>
				        </div>
				    </div>

				    <div class="form-group row">
				        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.postal_code') }}</label>
				        <div class="col-sm-9">
				            <input type="text" :placeholder="trans('texts.postal_code')" v-model="client.shipping_postal_code" class="form-control">
                 			<div v-if="client.errors.has('shipping_postal_code')" class="text-danger" v-text="client.errors.get('shipping_postal_code')"></div>
				        </div>
				    </div>

				    <div class="form-group row">
				        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.country') }}</label>
				        <div class="col-sm-9">
			            	<multiselect v-model="shippingCountry" :options="options" :placeholder="trans('texts.country')" label="name" track-by="id" @input="onChangeShipping"></multiselect>
             				<div v-if="client.errors.has('shipping_country_id')" class="text-danger" v-text="client.errors.get('shipping_country_id')"></div>
				        </div>
				    </div>
				</div>	
			</div>
		</div>
	</div>	
</template>

<script>

	import Multiselect from 'vue-multiselect'

	export default {
		components: {
		    Multiselect
		  },
        props: ['client', 'countries'],
        mounted() {
        	console.dir(this.countries)
        },
        data () {
		    return {
		      options: Object.keys(this.countries).map(i => this.countries[i]),
		      countryArray: Object.keys(this.countries).map(i => this.countries[i])
		    }
		  },
		computed: {
	        shippingCountry: {
	            set: function() {
	            
	                return this.client.shipping_country_id
	            
	            },
	            get: function(value) {


	            	return this.countryArray.filter(obj => {
					  return obj.id === this.client.shipping_country_id
					})

	            }
	        },
	        billingCountry: {
	            set: function() {
	            
	                return this.client.country_id
	            
	            },
	            get: function(value) {

	            	return this.countryArray.filter(obj => {
					  return obj.id === this.client.country_id
					})

	            }
	        }

	    },
	  	methods: {
	  		onChangeShipping(value) {
	  			this.client.shipping_country_id = value.id
	  		},
	  		onChangeBilling(value) {
	  			this.client.country_id = value.id
	  		}
	  	}
	}

</script>

<style src="vue-multiselect/dist/vue-multiselect.min.css"></style>
