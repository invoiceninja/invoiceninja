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
						            <multiselect :options="options_currency" :placeholder="placeHolderCurrency()" label="name" track-by="id" @input="onChangeCurrency"></multiselect>
						        </div>
						    </div>
						    <div class="form-group row client_form d-flex justify-content-center">
								<div class="form-check form-check-inline">
									<input class="form-check-input" id="inline-radio1" type="radio" value="option1" name="inline-radios">
									<label class="form-check-label" for="inline-radio1">{{ trans('texts.currency_symbol') }}:</label>
								</div>
								<div class="form-check form-check-inline">
									<input class="form-check-input" id="inline-radio2" type="radio" value="option2" name="inline-radios">
									<label class="form-check-label" for="inline-radio2">{{ trans('texts.currency_code') }}:</label>
								</div>
							</div>
						    <div class="form-group row client_form">
						        <label for="name" class="col-sm-5 text-left">
						        	<div>{{ trans('texts.language') }}</div>
									<div style="margin-top:1px; line-height:1.4; color:#939393;">{{ trans('help.client_language')}}</div>
						        </label>
						        <div class="col-sm-7">
						            <multiselect :options="options_language" :placeholder="placeHolderLanguage()" label="name" track-by="id" @input="onChangeLanguage"></multiselect>
						        </div>
						    </div>
						    <div class="form-group row client_form">
						        <label for="name" class="col-sm-5 text-left">
						        	<div>{{ trans('texts.payment_terms') }}</div>
									<div style="margin-top:1px; line-height:1.4; color:#939393;">{{ trans('help.client_payment_terms')}}</div>
						        </label>
						        <div class="col-sm-7">
						            <multiselect :options="options_payment_term" :placeholder="placeHolderPaymentTerm()" label="name" track-by="id" @input="onChangePaymentTerm"></multiselect>
						        </div>
						    </div>
						    
						    <div class="form-group row client_form">
						        <label for="name" class="col-sm-5 col-form-label text-left">
						        <div>{{ trans('texts.task_rate') }}</div>
								<div style="margin-top:1px; line-height:1.4; color:#939393;">{{ trans('texts.task_rate_help')}}</div>
						    	</label>
						        <div class="col-sm-7">
						            <input type="text" :placeholder="trans('texts.task_rate')" class="form-control">
						                 <div v-if="" class="text-danger" v-text=""></div>
						        </div>
						    </div>
						    <div class="form-group row client_form">
						        <label for="name" class="col-sm-5 col-form-label text-left">{{ trans('texts.send_client_reminders') }}</label>
						        <div class="col-sm-7">
						            <label class="switch switch-label switch-pill switch-info">
									<input class="switch-input" type="checkbox" checked="">
									<span class="switch-slider" data-checked="✓" data-unchecked="✕"></span>
									</label>
						        </div>
						    </div>
						    <div class="form-group row client_form">
						        <label for="name" class="col-sm-5 col-form-label text-left">{{ trans('texts.show_tasks_in_portal') }}</label>
						        <div class="col-sm-7">
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
						        <label for="name" class="col-sm-5 col-form-label text-left">
						        	<div>{{ trans('texts.dashboard') }}</div>
									<div style="margin-top:1px; line-height:1.4; color:#939393;">{{ trans('help.client_dashboard')}}</div>
						    	</label>
						        <div class="col-sm-7">
						            <textarea class="form-control" id="textarea-input" name="dashboard" rows="9" placeholder=""></textarea>
						        </div>
						    </div>
						    <div class="form-group row client_form">
						        <label for="name" class="col-sm-5 col-form-label text-left">
						        	<div>{{ trans('texts.unpaid_invoice') }}</div>
									<div style="margin-top:1px; line-height:1.4; color:#939393;">{{ trans('help.client_unpaid_invoice')}}</div>
								</label>
						        <div class="col-sm-7">
						            <textarea class="form-control" id="textarea-input" name="unpaid_invoice" rows="9" placeholder=""></textarea>
						        </div>
						    </div>
						    <div class="form-group row client_form">
						        <label for="name" class="col-sm-5 col-form-label text-left">
						        	<div>{{ trans('texts.paid_invoice') }}</div>
									<div style="margin-top:1px; line-height:1.4; color:#939393;">{{trans('help.client_paid_invoice')}}</div>
								</label>
						        <div class="col-sm-7">
						            <textarea class="form-control" id="textarea-input" name="paid_invoice" rows="9" placeholder=""></textarea>
						        </div>
						    </div>
						    <div class="form-group row client_form">
								<label class="col-sm-5 col-form-label text-left" for="unapproved_quote">
									<div>{{ trans('texts.unapproved_quote') }}</div>
									<div style="margin-top:1px; line-height:1.4; color:#939393;">{{trans('help.client_unapproved_quote')}}</div>
								</label>
								<div class="col-md-7">
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
						        <label for="name" class="col-sm-5 col-form-label text-left">{{ trans('texts.industry') }}</label>
						        <div class="col-sm-7">
						            <multiselect :options="options_industry" :placeholder="placeHolderIndustry()" label="name" track-by="id" @input="onChangeIndustry"></multiselect>
						        </div>
						    </div>
						    <div class="form-group row client_form">
						        <label for="name" class="col-sm-5 col-form-label text-left">{{ trans('texts.size_id') }}</label>
						        <div class="col-sm-7">
						            <multiselect :options="options_size" :placeholder="placeHolderSize()" label="name" track-by="id" @input="onChangeSize"></multiselect>
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
	      options_size: this.sizes
	    }
	  },
    props: ['settings', 'currencies', 'languages', 'payment_terms','industries','sizes','company'],
    mounted() {

    },
    methods: {
	  onItemChanged(event, currentItem, lastActiveItem) {
	    // your logic
	  },
	  onChangeCurrency(){

	  },
	  onChangeLanguage(){

	  },
	  onChangePaymentTerm(){

	  },
	  onChangeIndustry(){

	  },
	  onChangeSize(){

	  },
	  placeHolderCurrency(){
	  	return 'System Default Currency will appear here'
	  },		
	  placeHolderPaymentTerm(){

		var payment_term = this.payment_terms.filter(obj => {
		  return obj.id == this.company.settings.payment_terms
		})

		if(payment_term.length >= 1)
			return payment_term[0].name
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
		  return obj.id == this.company.settings.language_id
		})

			if(language.length >= 1)
				return language[0].name
			else
				return  Vue.prototype.trans('texts.language_id') 

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