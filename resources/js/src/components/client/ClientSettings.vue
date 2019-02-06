<template>

<div class="row">

	<div class="col-2" style="background:#fff;">

		<affix class="menu sidebar-menu" relative-element-selector="#example-content" :offset="{ top: 50, bottom:100 }" :scroll-affix="false" style="width: 200px">
          <div class="menu-label">
            <h2></h2>
          </div>
          <scrollactive 
          	class="menu-list" 
          	active-class="is-active"
          	:offset="50"
		  	:duration="800"
          	:exact="true"
          	>
          	<ul>
                <li class="menu-li"><a href="#intro" class="scrollactive-item" title="Intro">{{trans('texts.settings')}}</a></li>
                <li class="menu-li"><a href="#standard-affix" class="scrollactive-item" title="Standard Affix">{{trans('texts.messages')}}</a></li>
                <li class="menu-li"><a href="#scroll-affix" class="scrollactive-item" title="Scroll Affix">{{trans('texts.classify')}}</a></li>
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
									<div style="margin-top:1px; line-height:1.4;">The client currency.</div>
						        </label>
						        <div class="col-sm-7">
						            <multiselect v-model="currency" :options="options" :placeholder="trans('texts.currency')" label="name" track-by="id" @input="onChangeCurrency"></multiselect>
             						<div v-if="errors.has('currency')" class="text-danger" v-text="errors.get('currency')"></div>
						        </div>
						    </div>
						    <div class="form-group row client_form">
						        <label for="name" class="col-sm-3 col-form-label text-left"></label>

								<div class="form-check form-check-inline">
									<input class="form-check-input" id="inline-radio1" type="radio" value="option1" name="inline-radios">
									<label class="form-check-label" for="inline-radio1">{{ trans('texts.currency_symbol') }}</label>
								</div>
								<div class="form-check form-check-inline">
									<input class="form-check-input" id="inline-radio2" type="radio" value="option2" name="inline-radios">
									<label class="form-check-label" for="inline-radio2">{{ trans('texts.currency_code') }}</label>
								</div>
							</div>
						    <div class="form-group row client_form">
						        <label for="name" class="col-sm-3 col-form-label text-left">{{ trans('texts.payment_terms') }}</label>
						        <div class="col-sm-9">
						            <input type="text" :placeholder="trans('texts.payment_terms')" class="form-control">
					                 <div v-if="" class="text-danger" v-text=""></div>
						        </div>
						    </div>
						    <div class="form-group row client_form">
						        <label for="name" class="col-sm-3 col-form-label text-left">{{ trans('texts.task_rate') }}</label>
						        <div class="col-sm-9">
						            <input type="text" :placeholder="trans('texts.task_rate')" class="form-control">
						                 <div v-if="" class="text-danger" v-text=""></div>
						        </div>
						    </div>
						    <div class="form-group row client_form">
						        <label for="name" class="col-sm-3 col-form-label text-left">{{ trans('texts.send_client_reminders') }}</label>
						        <div class="col-sm-9">
						            <label class="switch switch-label switch-pill switch-info">
									<input class="switch-input" type="checkbox" checked="">
									<span class="switch-slider" data-checked="✓" data-unchecked="✕"></span>
									</label>
						        </div>
						    </div>
						    <div class="form-group row client_form">
						        <label for="name" class="col-sm-3 col-form-label text-left">{{ trans('texts.show_tasks_in_portal') }}</label>
						        <div class="col-sm-9">
						            <label class="switch switch-label switch-pill switch-info">
									<input class="switch-input" type="checkbox" checked="">
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
						        <label for="name" class="col-sm-3 col-form-label text-left">{{ trans('texts.dashboard') }}</label>
						        <div class="col-sm-9">
						            <textarea class="form-control" id="textarea-input" name="dashboard" rows="9" placeholder=""></textarea>
						        </div>
						    </div>
						    <div class="form-group row client_form">
						        <label for="name" class="col-sm-3 col-form-label text-left">{{ trans('texts.unpaid_invoice') }}</label>
						        <div class="col-sm-9">
						            <textarea class="form-control" id="textarea-input" name="unpaid_invoice" rows="9" placeholder=""></textarea>
						        </div>
						    </div>
						    <div class="form-group row client_form">
						        <label for="name" class="col-sm-3 col-form-label text-left">{{ trans('texts.paid_invoice') }}</label>
						        <div class="col-sm-9">
						            <textarea class="form-control" id="textarea-input" name="paid_invoice" rows="9" placeholder=""></textarea>
						        </div>
						    </div>
						    <div class="form-group row client_form">
								<label class="col-sm-3 col-form-label text-left" for="unapproved_quote">{{ trans('texts.unapproved_quote') }}</label>
								<div class="col-md-9">
									<textarea class="form-control" id="textarea-input" name="unapproved_quote" rows="9" placeholder=""></textarea>
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
						        <label for="name" class="col-sm-3 col-form-label text-left">{{ trans('texts.industry') }}</label>
						        <div class="col-sm-9">
						            <input type="text" :placeholder="trans('texts.client_name')" class="form-control">
						                 <div v-if="" class="text-danger" v-text=""></div>
						        </div>
						    </div>
						    <div class="form-group row client_form">
						        <label for="name" class="col-sm-3 col-form-label text-left">{{ trans('texts.size_id') }}</label>
						        <div class="col-sm-9">
						            <input type="text" :placeholder="trans('texts.client_name')" class="form-control">
						                 <div v-if="" class="text-danger" v-text=""></div>
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
		      options: this.currencies
		    }
		  },
	    props: ['settings', 'currencies'],
	    mounted() {

	    },
	    methods: {
		  onItemChanged(event, currentItem, lastActiveItem) {
		    // your logic
		  },
		},

	}

</script>




<style>

.client_form {
	border-bottom: 1px;
	border-bottom-style: solid;
    border-bottom-color: #167090;
}

.menu-li {
	list-style: none;
  	padding-left:5px;

}

a.scrollactive-item.is-active  {
  color: #42b983;
  font-family: helvetica;
  text-decoration: none;
  border-left-style: solid;
  border-left-color: #42b983;
  padding-left:10px;
}

a.scrollactive-item.is-active:hover {
  text-decoration: none;
  color: #42b983;
  padding-left:10px;

}

a.scrollactive-item.is-active:active {
  color: #42b983;
  padding-left:10px;

}



.menu-list a {
  color: black;
  font-family: helvetica;
  text-decoration: none;
}

.menu-list a:hover {
  text-decoration: none;
  color: #42b983;
      padding-left:5px;

}

.menu-list a:active {
  color: #42b983;
  text-decoration: none;
      padding-left:5px;

}


</style>