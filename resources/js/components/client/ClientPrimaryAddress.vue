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
				<generic-address :data="client.primary_billing_location ? client.primary_billing_location : 'null'"></generic-address>
			</div>
			<div class="tab-pane" id="shipping" role="tabpanel">
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