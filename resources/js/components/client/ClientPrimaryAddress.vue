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
				<generic-address :data="client.primary_billing_location ? client.primary_billing_location : 'null'"></generic-address>
			</div>
			<div class="tab-pane" id="shipping" role="tabpanel">
				<button type="button" class="btn btn-sm btn-light" v-on:click="$emit('copy',' copy_billing')"> {{ trans('texts.copy_billing') }}</button>
				<generic-address :data="client.primary_shipping_location ? client.primary_shipping_location : 'null'"></generic-address>
			</div>
		</div>
	</div>	
</template>

<script>
export default {
        props: ['client']
    }
</script>