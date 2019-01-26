<template>

	<div class="container-fluid">

		<div class="row">
	    <div class="col" style="padding: 0px;">
	    
		    <div class="float-right">

				<div class="btn-group ml-2">
			      <button type="button" class="btn btn-lg btn-secondary">{{ trans('texts.edit_client') }}</button>
			      <button type="button" class="btn btn-lg btn-secondary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
			        <span class="sr-only">Toggle Dropdown</span>
			      </button>
			      <div class="dropdown-menu" x-placement="top-start" style="position: absolute; transform: translate3d(189px, -2px, 0px); top: 0px; left: 0px; will-change: transform;">
			        <a class="dropdown-item" href="#">Action</a>
			        <a class="dropdown-item" href="#">Another action</a>
			        <a class="dropdown-item" href="#">Something else here</a>
			        <div class="dropdown-divider"></div>
			        <a class="dropdown-item" href="#">Separated link</a>
			      </div>
			    </div>

				<div class="btn-group ml-2">
			      <button type="button" class="btn btn-lg btn-primary">{{ trans('texts.view_statement') }}</button>
			      <button type="button" class="btn btn-lg btn-primary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
			        <span class="sr-only">Toggle Dropdown</span>
			      </button>
			      <div class="dropdown-menu" x-placement="top-start" style="position: absolute; transform: translate3d(189px, -2px, 0px); top: 0px; left: 0px; will-change: transform;">
			        <a class="dropdown-item" href="#">Action</a>
			        <a class="dropdown-item" href="#">Another action</a>
			        <a class="dropdown-item" href="#">Something else here</a>
			        <div class="dropdown-divider"></div>
			        <a class="dropdown-item" href="#">Separated link</a>
			      </div>
			    </div>

		    </div>

		</div>
		</div>

		<div class="card">
			<div class="card-body">
				<div class="row">

					<div class="col-sm">
						<h3> {{ trans('texts.details') }} </h3>
						<p v-if="client.id_number && client.id_number.length >= 1"><b>{{ trans('texts.id_number') }}:</b> {{ client.id_number }}</p>
						<p v-if="client.vat_number && client.vat_number.length >= 1"><b>{{ trans('texts.vat_number') }}:</b> {{ client.vat_number }}</p>
						<p v-if="client.custom_value1 && client.custom_value1.length >= 1"><b>{{ company.custom_client_label1 }}:</b> {{ client.custom_value1 }}</p>
						<p v-if="client.custom_value2 && client.custom_value2.length >= 1"><b>{{ company.custom_client_label2 }}:</b> {{ client.custom_value2 }}</p>
						<p v-if="client.custom_value3 && client.custom_value3.length >= 1"><b>{{ company.custom_client_label3 }}:</b> {{ client.custom_value3 }}</p>
						<p v-if="client.custom_value4 && client.custom_value4.length >= 1"><b>{{ company.custom_client_label4 }}:</b> {{ client.custom_value4 }}</p>
					</div>

					<div class="col-sm">
						<ul>
							<li><h3> {{ trans('texts.address') }} </h3></li>
							<li><b> {{ trans('texts.billing_address') }}</b></li>
							<li v-if="client.address1 && client.address1.length >=1">{{ client.address1 }} <br></li>
							<li v-if="client.address2 && client.address2.length >=1">{{ client.address2 }} <br></li>
							<li v-if="client.city && client.city.length >=1">{{ client.city }} <br></li>
							<li v-if="client.state && client.state.length >=1" >{{ client.state}} {{client.postal_code}}<br></li>
							<li v-if="client.country && client.country.name.length >=1">{{ client.country.name }}<br></li>
						</ul>

						<ul v-if="client.shipping_address1 && client.shipping_address1.length >=1">
							<li><b> {{ trans('texts.shipping_address') }}</b></li>
							<li v-if="client.shipping_address1 && client.shipping_address1.length >=1">{{ client.shipping_address1 }} <br></li>
							<li v-if="client.shipping_address2 && client.shipping_address2.length >=1">{{ client.shipping_address2 }} <br></li>
							<li v-if="client.shipping_city && client.shipping_city.length >=1">{{ client.shipping_city }} <br></li>
							<li v-if="client.shipping_state && client.shipping_state.length >=1" >{{ client.shipping_state}} {{client.shipping_postal_code}}<br></li>
							<li v-if="client.shipping_country && client.shipping_country.name.length >=1">{{ client.shipping_country.name }}<br></li>
						</ul>
					</div>

					<div class="col-sm">
						<h3> {{ trans('texts.contacts') }} </h3>
					</div>

					<div class="col-sm">
						<h3> {{ trans('texts.standing') }} </h3>
					</div>

				</div>
			</div>
		</div>
		


		</div>

	</div>

</template>

<script lang="ts">

export default {
    props: ['client', 'company']
}

</script>

<style>
.card { margin-top:50px; }

li { list-style: none }
</style>