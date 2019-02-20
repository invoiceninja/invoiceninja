<template>

<div class="row" style="background:#fff; padding:20px;">

	<div class="col-2" style="border: 0px; border-style:solid;">

		<affix class="menu sidebar-menu" relative-element-selector="#example-content" :offset="{ top: 50, bottom:100 }" :scroll-affix="false" style="width: 200px">
          <div class="menu-label">
            <h3 style="color:#5d5d5d;">{{ trans('texts.settings') }}</h3>
          </div>
          <scrollactive 
          	class="menu-list" 
          	active-class="is-active"
          	:offset="50"
		  	:duration="800"
          	:exact="true"
          	>
          	<ul class="list-inline justify-content-left">
                <li class="menu-li"><a href="#intro" class="scrollactive-item" >{{trans('t.client_settings')}}</a></li>
                <li class="menu-li"><a href="#standard-affix" class="scrollactive-item" >{{trans('texts.messages')}}</a></li>
                <li class="menu-li"><a href="#scroll-affix" class="scrollactive-item" >{{trans('texts.classify')}}</a></li>
        	</ul>
          </scrollactive>
        </affix>

	</div>

	<div class="col-10">

		<div id="example-content">

          <section id="intro">
                <div class="card">
                    <div class="card-header bg-primary2">{{ trans('t.client_settings') }}</div>
                        <div class="card-body px-3">
						    <div class="form-group row client_form">
						        <label for="name" class="col-sm-5 text-left">
						        	<div>{{ trans('texts.currency') }}</div>
									<div style="margin-top:1px; line-height:1.4; color:#939393;">{{ trans('help.client_currency') }}</div>
						        </label>
						        <div class="col-sm-7">
						            <multiselect v-model="settings_currency_id" :options="options_currency" label="name" track-by="id" :placeholder="placeHolderCurrency()" :allow-empty="true"></multiselect>
						        </div>
						    </div>
						    <div class="form-group row client_form d-flex justify-content-center">
								<div class="form-check form-check-inline">
									<input class="form-check-input" id="inline-radio1" type="radio" name="symbol" value="1" v-model="settings_show_currency_symbol" @click="setCurrencySymbol()">
									<label class="form-check-label" for="show_currency_symbol-radio1">{{ trans('texts.currency_symbol') }}:</label>
								</div>
								<div class="form-check form-check-inline">
									<input class="form-check-input" id="inline-radio2" type="radio" name="code" value="1" v-model="settings_show_currency_code" @click="setCurrencyCode()">
									<label class="form-check-label" for="show_currency_code">{{ trans('texts.currency_code') }}:</label>
								</div>
							</div>
						    <div class="form-group row client_form">
						        <label for="language" class="col-sm-5 text-left">
						        	<div>{{ trans('texts.language') }}</div>
									<div style="margin-top:1px; line-height:1.4; color:#939393;">{{ trans('help.client_language')}}</div>
						        </label>
						        <div class="col-sm-7">
						            <multiselect v-model="settings_language_id" :options="options_language" :placeholder="placeHolderLanguage()" label="name" track-by="id" :allow-empty="true"></multiselect>
						        </div>
						    </div>
						    <div class="form-group row client_form">
						        <label for="payment_terms" class="col-sm-5 text-left">
						        	<div>{{ trans('texts.payment_terms') }}</div>
									<div style="margin-top:1px; line-height:1.4; color:#939393;">{{ trans('help.client_payment_terms')}}</div>
						        </label>
						        <div class="col-sm-7">
						            <multiselect v-model="settings_payment_terms" :options="options_payment_term" :placeholder="placeHolderPaymentTerm()" label="name" track-by="num_days" :allow-empty="true"></multiselect>
						        </div>
						    </div>
						    
						    <div class="form-group row client_form">
						        <label for="name" class="col-sm-5 col-form-label text-left">
						        <div>{{ trans('texts.task_rate') }}</div>
								<div style="margin-top:1px; line-height:1.4; color:#939393;">{{ trans('texts.task_rate_help')}}</div>
						    	</label>
						        <div class="col-sm-7">
						            <input type="text" :placeholder="trans('texts.task_rate')" class="form-control" v-model="settings.task_rate">
						                 <div v-if="" class="text-danger" v-text=""></div>
						        </div>
						    </div>
						    <div class="form-group row client_form">
						        <label for="name" class="col-sm-5 col-form-label text-left">{{ trans('texts.send_client_reminders') }}</label>
						        <div class="col-sm-7">
						            <label class="switch switch-label switch-pill switch-info">
									<input class="switch-input" type="checkbox" checked="" v-model="settings_send_reminders">
									<span class="switch-slider" data-checked="✓" data-unchecked="✕"></span>
									</label>
						        </div>
						    </div>
						    <div class="form-group row client_form">
						        <label for="name" class="col-sm-5 col-form-label text-left">{{ trans('texts.show_tasks_in_portal') }}</label>
						        <div class="col-sm-7">
						            <label class="switch switch-label switch-pill switch-info">
									<input class="switch-input" type="checkbox" checked="" v-model="settings_show_tasks_in_portal">
									<span class="switch-slider" data-checked="✓" data-unchecked="✕"></span>
									</label>
						        </div>
						    </div>

						</div>
					
                </div>
          </section>

          <section id="standard-affix">
            <div class="card">
                    <div class="card-header bg-primary2">{{ trans('texts.messages') }}</div>
                        <div class="card-body">
						    <div class="form-group row client_form">
						        <label for="name" class="col-sm-5 col-form-label text-left">
						        	<div>{{ trans('texts.dashboard') }}</div>
									<div style="margin-top:1px; line-height:1.4; color:#939393;">{{ trans('help.client_dashboard')}}</div>
						    	</label>
						        <div class="col-sm-7">
						            <textarea class="form-control" id="textarea-input" label="dashboard" v-model="settings.custom_message_dashboard"rows="9" :placeholder="placeHolderMessage('custom_message_dashboard')"></textarea>
						        </div>
						    </div>
						    <div class="form-group row client_form">
						        <label for="name" class="col-sm-5 col-form-label text-left">
						        	<div>{{ trans('texts.unpaid_invoice') }}</div>
									<div style="margin-top:1px; line-height:1.4; color:#939393;">{{ trans('help.client_unpaid_invoice')}}</div>
								</label>
						        <div class="col-sm-7">
						            <textarea class="form-control" id="textarea-input" label="unpaid_invoice" v-model="settings.custom_message_unpaid_invoice"rows="9" :placeholder="placeHolderMessage('custom_message_unpaid_invoice')"></textarea>
						        </div>
						    </div>
						    <div class="form-group row client_form">
						        <label for="name" class="col-sm-5 col-form-label text-left">
						        	<div>{{ trans('texts.paid_invoice') }}</div>
									<div style="margin-top:1px; line-height:1.4; color:#939393;">{{trans('help.client_paid_invoice')}}</div>
								</label>
						        <div class="col-sm-7">
						            <textarea class="form-control" id="textarea-input" label="paid_invoice"  v-model="settings.custom_message_paid_invoice" rows="9" :placeholder="placeHolderMessage('custom_message_paid_invoice')"></textarea>
						        </div>
						    </div>
						    <div class="form-group row client_form">
								<label class="col-sm-5 col-form-label text-left" for="unapproved_quote">
									<div>{{ trans('texts.unapproved_quote') }}</div>
									<div style="margin-top:1px; line-height:1.4; color:#939393;">{{trans('help.client_unapproved_quote')}}</div>
								</label>
								<div class="col-md-7">
									<textarea class="form-control" id="textarea-input" label="unapproved_quote" v-model="settings.custom_message_unapproved_quote" rows="9" :placeholder="placeHolderMessage('custom_message_unapproved_quote')"></textarea>
								</div>
							</div>

						</div>
					
                </div>
          </section>

          <section id="scroll-affix">
            <div class="card">
                    <div class="card-header bg-primary2">{{ trans('texts.classify') }}</div>
                        <div class="card-body">
						    <div class="form-group row client_form">
						        <label for="name" class="col-sm-5 col-form-label text-left">{{ trans('texts.industry') }}</label>
						        <div class="col-sm-7">
						            <multiselect :options="options_industry" :placeholder="placeHolderIndustry()" label="name" track-by="id" v-model="settings.language_id"></multiselect>
						        </div>
						    </div>
						    <div class="form-group row client_form">
						        <label for="name" class="col-sm-5 col-form-label text-left">{{ trans('texts.size_id') }}</label>
						        <div class="col-sm-7">
						            <multiselect :options="options_size" :placeholder="placeHolderSize()" label="name" track-by="id" v-model="settings.size_id"></multiselect>
						        </div>
						    </div>

						</div>
					
                </div>
          </section>

        </div>
        		
	</div>

</div>



</template>


<script lang="ts">

import Vue from 'vue';
import { Affix } from 'vue-affix';
var VueScrollactive = require('vue-scrollactive');

import Multiselect from 'vue-multiselect'


Vue.use(VueScrollactive);

export default {
	components: {
		Affix,
	    Multiselect,
	},
	data () {
	    return {
			options_currency: Object.keys(this.currencies).map(i => this.currencies[i]),
			options_language: Object.keys(this.languages).map(i => this.languages[i]),
			options_payment_term: Object.keys(this.payment_terms).map(i => this.payment_terms[i]),
			options_industry: Object.keys(this.industries).map(i => this.industries[i]),
			options_size: this.sizes,
			settings: this.client_settings
	    }
	  },
    props: ['client_settings', 'currencies', 'languages', 'payment_terms', 'industries', 'sizes', 'company'],
    mounted() {

    	if(!!this.settings.show_currency_symbol)
			this.settings.show_currency_symbol = this.company.settings_object.show_currency_symbol
		else if(!!this.settings.show_currency_code)
			this.settings.show_currency_code = this.company.settings_object.show_currency_code

	},
    computed: {
    	settings_currency_id: {
    		set: function(value){

    			this.setObjectValue('currency_id', value.id)

    		},
    		get: function(){
				return this.options_currency.filter(obj => {
					return obj.id == this.settings.currency_id
				})
    		}
    	},
		settings_language_id: {
			set: function(value) {

    			this.setObjectValue('language_id', value.id)

			},
			get: function() {
				return this.options_language.filter(obj => {
					return obj.id == this.settings.language_id
				})
			}
		},
		settings_payment_terms: {
			set: function(value) {
				
				if(value === null)
					this.setObjectValue('payment_terms', null)
				else
    				this.setObjectValue('payment_terms', value.num_days)

			},
			get: function() {
				return this.options_payment_term.filter(obj => {
					return obj.num_days == this.settings.payment_terms
				})
			}
		},
		settings_show_tasks_in_portal:{
			set: function(value) {

				if(this.settings.show_tasks_in_portal == this.company.settings.show_tasks_in_portal)
					this.settings.show_tasks_in_portal = null
				else
    				this.settings.show_tasks_in_portal = value;

			},
			get: function() {

				if(this.settings.show_tasks_in_portal)
					return this.settings.show_tasks_in_portal
				else
					return this.company.settings_object.show_tasks_in_portal

			}
		},
		settings_send_reminders: {
			set: function(value) {

				if(this.settings.send_reminders == this.company.settings.send_reminders)
					this.settings.send_reminders = null
				else
    				this.settings.send_reminders = value;

			},
			get: function() {

				if(this.settings.send_reminders)
					return this.settings.send_reminders
				else
					return this.company.settings_object.send_reminders

			}
		},
		settings_show_currency_symbol: {

			get: function() {

					return this.settings.show_currency_symbol
				
			},
			set: function(value) {
				this.settings.show_currency_symbol = 1
				this.settings.show_currency_code = !value
			}
			
		},
		settings_show_currency_code: {

			get: function() {

				return this.settings.show_currency_code

			},
			set: function(value) {
				this.settings.show_currency_code = 1
				this.settings.show_currency_symbol = !value
			}

		}
    },
    methods: {
		onItemChanged(event, currentItem, lastActiveItem) {
		// your logic
		},
		setObjectValue(key, value){

			if(value === null)
				this.settings[key] = null
			else
				this.settings[key] = value

		},
		placeHolderCurrency(){

			var currency = this.options_currency.filter(obj => {
				return obj.id == this.company.settings_object.currency_id
			})

			if(currency.length >= 1)
				return currency[0].name
			else
				return  Vue.prototype.trans('texts.currency_id') 	

		},		
		placeHolderPaymentTerm(){

			var payment_terms = this.payment_terms.filter(obj => {
			  return obj.num_days == this.company.settings_object.payment_terms
			})

			if(payment_terms.length >= 1)
				return payment_terms[0].name
			else
				return  Vue.prototype.trans('texts.payment_terms') 	

		},
		placeHolderIndustry(){

			return  Vue.prototype.trans('texts.industry_id') 

		},
		placeHolderSize(){

			return  Vue.prototype.trans('texts.size_id') 	

		},
		placeHolderLanguage(){

			var language = this.languages.filter(obj => {
			  return obj.id == this.company.settings_object.language_id
			})

			if(language.length >= 1)
				return language[0].name
			else
				return  Vue.prototype.trans('texts.language_id') 

		},
		placeHolderMessage(message_setting : string) {

			if(this.company.settings_object[message_setting] && this.company.settings_object[message_setting].length >=1) {

				return this.company.settings_object[message_setting]
				
			}

		},
		setCurrencyCode() {
			this.settings.show_currency_symbol = false;
			this.settings.show_currency_code = true;

			this.updateCurrencyExample()

		},
		setCurrencySymbol() {

			this.settings.show_currency_symbol = true;
			this.settings.show_currency_code = false;

			this.updateCurrencyExample()

		},
		updateCurrencyExample() {
			//get currency
			//get symbol or code
			// format example $1000
		}

	
	}
	
}


</script>




<style>

#example-content {
}

.client_form {
	border-bottom: 0px;
	border-bottom-style: solid;
    border-bottom-color: #167090;
}

.menu-li {
	list-style: none;
  	padding-left:5px;
  	width:200px;
  	line-height:1.4;
  	margin-top:10px;

}

a.scrollactive-item.is-active  {
  color: #027093;
  font-family: helvetica;
  text-decoration: none;
  border-left-style: solid;
  border-left-color: #027093;
  padding-left:10px;

}

a.scrollactive-item.is-active:hover {
  text-decoration: none;

  color: #027093;
  padding-left:10px;

}

a.scrollactive-item.is-active:active {
  color: #027093;
  padding-left:10px;

}


.menu-list a {
  color: #939393;

  font-family: helvetica;
  text-decoration: none;
}

.menu-list a:hover {
  text-decoration: none;

  color: #027093;
  padding-left:5px;

}

.menu-list a:active {
  color: #027093;
  text-decoration: none;
      padding-left:5px;

}


</style>