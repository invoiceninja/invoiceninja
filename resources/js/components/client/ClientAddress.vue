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
				            <input type="text" name="address1" :placeholder="trans('texts.address1')" v-model="client.address1" class="form-control" id="address1">
				                <div v-if="errors && errors.address1" class="text-danger">{{ errors.address1[0] }}</div>
				        </div>
				    </div>

				    <div class="form-group row">
				        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.address2') }}</label>
				        <div class="col-sm-9">
				            <input type="text" name="address2" :placeholder="trans('texts.address2')" v-model="client.address2" class="form-control" id="address2">
				            <div v-if="errors && errors.address2" class="text-danger">{{ errors.address2[0] }}</div>
				        </div>
				    </div>

				    <div class="form-group row">
				        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.city') }}</label>
				        <div class="col-sm-9">
				            <input type="text" name="city" :placeholder="trans('texts.city')" v-model="client.city" class="form-control" id="city">
				            <div v-if="errors && errors.city" class="text-danger">{{ errors.city[0] }}</div>
				        </div>
				    </div>

				    <div class="form-group row">
				        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.state') }}</label>
				        <div class="col-sm-9">
				            <input type="text" name="state" :placeholder="trans('texts.state')" v-model="client.state" class="form-control" id="state">
				            <div v-if="errors && errors.state" class="text-danger">{{ errors.state[0] }}</div>
				        </div>
				    </div>

				    <div class="form-group row">
				        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.postal_code') }}</label>
				        <div class="col-sm-9">
				            <input type="text" name="postal_code" :placeholder="trans('texts.postal_code')" v-model="client.postal_code" class="form-control" id="postal_code">
				            <div v-if="errors && errors.postal_code" class="text-danger">{{ errors.postal_code[0] }}</div>
				        </div>
				    </div>

				    <div class="form-group row">
				        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.country') }}</label>
				        <div class="col-sm-9">
				            <input type="text" name="country" :placeholder="trans('texts.country')" v-model="client.country" class="form-control" id="country">
				            <div v-if="errors && errors.country" class="text-danger">{{ errors.country[0] }}</div>
				        </div>
				    </div>
				</div>	
			</div>
			<div class="tab-pane" id="shipping" role="tabpanel">
				<button type="button" class="btn btn-sm btn-light" v-on:click="$emit('copy',' copy_billing')"> {{ trans('texts.copy_billing') }}</button>
				<div class="form-group row">
				        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.address1') }}</label>
				        <div class="col-sm-9">
				            <input type="text" name="address1" :placeholder="trans('texts.address1')" v-model="client.shipping_address1" class="form-control" id="address1">
				                <div v-if="errors && errors.address1" class="text-danger">{{ errors.address1[0] }}</div>
				        </div>
				    </div>

				    <div class="form-group row">
				        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.address2') }}</label>
				        <div class="col-sm-9">
				            <input type="text" name="address2" :placeholder="trans('texts.address2')" v-model="client.shipping_address2" class="form-control" id="address2">
				            <div v-if="errors && errors.address2" class="text-danger">{{ errors.address2[0] }}</div>
				        </div>
				    </div>

				    <div class="form-group row">
				        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.city') }}</label>
				        <div class="col-sm-9">
				            <input type="text" name="city" :placeholder="trans('texts.city')" v-model="client.shipping_city" class="form-control" id="city">
				            <div v-if="errors && errors.city" class="text-danger">{{ errors.city[0] }}</div>
				        </div>
				    </div>

				    <div class="form-group row">
				        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.state') }}</label>
				        <div class="col-sm-9">
				            <input type="text" name="state" :placeholder="trans('texts.state')" v-model="client.shipping_state" class="form-control" id="state">
				            <div v-if="errors && errors.state" class="text-danger">{{ errors.state[0] }}</div>
				        </div>
				    </div>

				    <div class="form-group row">
				        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.postal_code') }}</label>
				        <div class="col-sm-9">
				            <input type="text" name="postal_code" :placeholder="trans('texts.postal_code')" v-model="client.shipping_postal_code" class="form-control" id="postal_code">
				            <div v-if="errors && errors.postal_code" class="text-danger">{{ errors.postal_code[0] }}</div>
				        </div>
				    </div>

				    <div class="form-group row">
				        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.country') }}</label>
				        <div class="col-sm-9">
				            <input type="text" name="country" :placeholder="trans('texts.country')" v-model="client.shipping_country" class="form-control" id="country">
				            <div v-if="errors && errors.country" class="text-danger">{{ errors.country[0] }}</div>
				        </div>
				    </div>
				</div>	
			</div>
		</div>
	</div>	
</template>

<script>
export default {
        props: ['client']
    }
</script>