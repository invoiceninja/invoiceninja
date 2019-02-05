<template>


<div class="row">

		<div class="col-2">

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
	                <li class="menu-li"><a href="#intro" class="scrollactive-item" title="Intro">Intro</a></li>
	                <li class="menu-li"><a href="#standard-affix" class="scrollactive-item" title="Standard Affix">Standard Affix</a></li>
	                <li class="menu-li"><a href="#scroll-affix" class="scrollactive-item" title="Scroll Affix">Scroll Affix</a></li>
	                <li class="menu-li"><a href="#markup-1" class="scrollactive-item" title="Markup 1">Markup 1</a></li>
	                <li class="menu-li"><a href="#markup-2" class="scrollactive-item" title="Markup 2">Markup 2</a></li>
	                <li class="menu-li"><a href="#markup-3" class="scrollactive-item" title="Markup 3">Markup 3</a></li>
            	</ul>
              </scrollactive>
            </affix>

		</div>

		<div class="col-10">

			<div id="example-content">

              <section id="intro">
                    <div class="card">
                        <div class="card-header bg-primary2">{{ trans('texts.edit_client') }}</div>
                            <div class="card-body">
							    <div class="form-group row">
							        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.client_name') }}</label>
							        <div class="col-sm-9">
							            <input type="text" :placeholder="trans('texts.client_name')" class="form-control">
							                 <div v-if="" class="text-danger" v-text=""></div>
							        </div>
							    </div>
							    <div class="form-group row">
							        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.client_name') }}</label>
							        <div class="col-sm-9">
							            <input type="text" :placeholder="trans('texts.client_name')" class="form-control">
							                 <div v-if="" class="text-danger" v-text=""></div>
							        </div>
							    </div>
							    <div class="form-group row">
							        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.client_name') }}</label>
							        <div class="col-sm-9">
							            <input type="text" :placeholder="trans('texts.client_name')" class="form-control">
							                 <div v-if="" class="text-danger" v-text=""></div>
							        </div>
							    </div>
							    <div class="form-group row">
							        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.client_name') }}</label>
							        <div class="col-sm-9">
							            <input type="text" :placeholder="trans('texts.client_name')" class="form-control">
							                 <div v-if="" class="text-danger" v-text=""></div>
							        </div>
							    </div>
							    <div class="form-group row">
							        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.client_name') }}</label>
							        <div class="col-sm-9">
							            <input type="text" :placeholder="trans('texts.client_name')" class="form-control">
							                 <div v-if="" class="text-danger" v-text=""></div>
							        </div>
							    </div>
							    <div class="form-group row">
							        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.client_name') }}</label>
							        <div class="col-sm-9">
							            <input type="text" :placeholder="trans('texts.client_name')" class="form-control">
							                 <div v-if="" class="text-danger" v-text=""></div>
							        </div>
							    </div>

							</div>
						
                    </div>
              </section>

              <section id="standard-affix">
                <div class="card">
                        <div class="card-header bg-primary2">{{ trans('texts.edit_client') }}</div>
                            <div class="card-body">
							    <div class="form-group row">
							        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.client_name') }}</label>
							        <div class="col-sm-9">
							            <input type="text" :placeholder="trans('texts.client_name')" class="form-control">
							                 <div v-if="" class="text-danger" v-text=""></div>
							        </div>
							    </div>
							    <div class="form-group row">
							        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.client_name') }}</label>
							        <div class="col-sm-9">
							            <input type="text" :placeholder="trans('texts.client_name')" class="form-control">
							                 <div v-if="" class="text-danger" v-text=""></div>
							        </div>
							    </div>
							    <div class="form-group row">
							        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.client_name') }}</label>
							        <div class="col-sm-9">
							            <input type="text" :placeholder="trans('texts.client_name')" class="form-control">
							                 <div v-if="" class="text-danger" v-text=""></div>
							        </div>
							    </div>
							    <div class="form-group row">
							        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.client_name') }}</label>
							        <div class="col-sm-9">
							            <input type="text" :placeholder="trans('texts.client_name')" class="form-control">
							                 <div v-if="" class="text-danger" v-text=""></div>
							        </div>
							    </div>
							    <div class="form-group row">
							        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.client_name') }}</label>
							        <div class="col-sm-9">
							            <input type="text" :placeholder="trans('texts.client_name')" class="form-control">
							                 <div v-if="" class="text-danger" v-text=""></div>
							        </div>
							    </div>
							    <div class="form-group row">
							        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.client_name') }}</label>
							        <div class="col-sm-9">
							            <input type="text" :placeholder="trans('texts.client_name')" class="form-control">
							                 <div v-if="" class="text-danger" v-text=""></div>
							        </div>
							    </div>

							</div>
						
                    </div>
              </section>

              <section id="scroll-affix">
                <div class="card">
                        <div class="card-header bg-primary2">{{ trans('texts.edit_client') }}</div>
                            <div class="card-body">
							    <div class="form-group row">
							        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.client_name') }}</label>
							        <div class="col-sm-9">
							            <input type="text" :placeholder="trans('texts.client_name')" class="form-control">
							                 <div v-if="" class="text-danger" v-text=""></div>
							        </div>
							    </div>
							    <div class="form-group row">
							        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.client_name') }}</label>
							        <div class="col-sm-9">
							            <input type="text" :placeholder="trans('texts.client_name')" class="form-control">
							                 <div v-if="" class="text-danger" v-text=""></div>
							        </div>
							    </div>
							    <div class="form-group row">
							        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.client_name') }}</label>
							        <div class="col-sm-9">
							            <input type="text" :placeholder="trans('texts.client_name')" class="form-control">
							                 <div v-if="" class="text-danger" v-text=""></div>
							        </div>
							    </div>
							    <div class="form-group row">
							        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.client_name') }}</label>
							        <div class="col-sm-9">
							            <input type="text" :placeholder="trans('texts.client_name')" class="form-control">
							                 <div v-if="" class="text-danger" v-text=""></div>
							        </div>
							    </div>
							    <div class="form-group row">
							        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.client_name') }}</label>
							        <div class="col-sm-9">
							            <input type="text" :placeholder="trans('texts.client_name')" class="form-control">
							                 <div v-if="" class="text-danger" v-text=""></div>
							        </div>
							    </div>
							    <div class="form-group row">
							        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.client_name') }}</label>
							        <div class="col-sm-9">
							            <input type="text" :placeholder="trans('texts.client_name')" class="form-control">
							                 <div v-if="" class="text-danger" v-text=""></div>
							        </div>
							    </div>

							</div>
						
                    </div>
              </section>

              <section id="markup-1">
                <div class="card">
                        <div class="card-header bg-primary2">{{ trans('texts.edit_client') }}</div>
                            <div class="card-body">
							    <div class="form-group row">
							        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.client_name') }}</label>
							        <div class="col-sm-9">
							            <input type="text" :placeholder="trans('texts.client_name')" class="form-control">
							                 <div v-if="" class="text-danger" v-text=""></div>
							        </div>
							    </div>
							    <div class="form-group row">
							        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.client_name') }}</label>
							        <div class="col-sm-9">
							            <input type="text" :placeholder="trans('texts.client_name')" class="form-control">
							                 <div v-if="" class="text-danger" v-text=""></div>
							        </div>
							    </div>
							    <div class="form-group row">
							        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.client_name') }}</label>
							        <div class="col-sm-9">
							            <input type="text" :placeholder="trans('texts.client_name')" class="form-control">
							                 <div v-if="" class="text-danger" v-text=""></div>
							        </div>
							    </div>
							    <div class="form-group row">
							        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.client_name') }}</label>
							        <div class="col-sm-9">
							            <input type="text" :placeholder="trans('texts.client_name')" class="form-control">
							                 <div v-if="" class="text-danger" v-text=""></div>
							        </div>
							    </div>
							    <div class="form-group row">
							        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.client_name') }}</label>
							        <div class="col-sm-9">
							            <input type="text" :placeholder="trans('texts.client_name')" class="form-control">
							                 <div v-if="" class="text-danger" v-text=""></div>
							        </div>
							    </div>
							    <div class="form-group row">
							        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.client_name') }}</label>
							        <div class="col-sm-9">
							            <input type="text" :placeholder="trans('texts.client_name')" class="form-control">
							                 <div v-if="" class="text-danger" v-text=""></div>
							        </div>
							    </div>

							</div>
						
                    </div>
                </section>

              <section id="markup-2">
                <div class="card">
                        <div class="card-header bg-primary2">{{ trans('texts.edit_client') }}</div>
                            <div class="card-body">
							    <div class="form-group row">
							        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.client_name') }}</label>
							        <div class="col-sm-9">
							            <input type="text" :placeholder="trans('texts.client_name')" class="form-control">
							                 <div v-if="" class="text-danger" v-text=""></div>
							        </div>
							    </div>
							    <div class="form-group row">
							        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.client_name') }}</label>
							        <div class="col-sm-9">
							            <input type="text" :placeholder="trans('texts.client_name')" class="form-control">
							                 <div v-if="" class="text-danger" v-text=""></div>
							        </div>
							    </div>
							    <div class="form-group row">
							        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.client_name') }}</label>
							        <div class="col-sm-9">
							            <input type="text" :placeholder="trans('texts.client_name')" class="form-control">
							                 <div v-if="" class="text-danger" v-text=""></div>
							        </div>
							    </div>
							    <div class="form-group row">
							        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.client_name') }}</label>
							        <div class="col-sm-9">
							            <input type="text" :placeholder="trans('texts.client_name')" class="form-control">
							                 <div v-if="" class="text-danger" v-text=""></div>
							        </div>
							    </div>
							    <div class="form-group row">
							        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.client_name') }}</label>
							        <div class="col-sm-9">
							            <input type="text" :placeholder="trans('texts.client_name')" class="form-control">
							                 <div v-if="" class="text-danger" v-text=""></div>
							        </div>
							    </div>
							    <div class="form-group row">
							        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.client_name') }}</label>
							        <div class="col-sm-9">
							            <input type="text" :placeholder="trans('texts.client_name')" class="form-control">
							                 <div v-if="" class="text-danger" v-text=""></div>
							        </div>
							    </div>

							</div>
						
                    </div>
              </section>

              <section id="markup-3">
                <div class="card">
                        <div class="card-header bg-primary2">{{ trans('texts.edit_client') }}</div>
                            <div class="card-body">
							    <div class="form-group row">
							        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.client_name') }}</label>
							        <div class="col-sm-9">
							            <input type="text" :placeholder="trans('texts.client_name')" class="form-control">
							                 <div v-if="" class="text-danger" v-text=""></div>
							        </div>
							    </div>
							    <div class="form-group row">
							        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.client_name') }}</label>
							        <div class="col-sm-9">
							            <input type="text" :placeholder="trans('texts.client_name')" class="form-control">
							                 <div v-if="" class="text-danger" v-text=""></div>
							        </div>
							    </div>
							    <div class="form-group row">
							        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.client_name') }}</label>
							        <div class="col-sm-9">
							            <input type="text" :placeholder="trans('texts.client_name')" class="form-control">
							                 <div v-if="" class="text-danger" v-text=""></div>
							        </div>
							    </div>
							    <div class="form-group row">
							        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.client_name') }}</label>
							        <div class="col-sm-9">
							            <input type="text" :placeholder="trans('texts.client_name')" class="form-control">
							                 <div v-if="" class="text-danger" v-text=""></div>
							        </div>
							    </div>
							    <div class="form-group row">
							        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.client_name') }}</label>
							        <div class="col-sm-9">
							            <input type="text" :placeholder="trans('texts.client_name')" class="form-control">
							                 <div v-if="" class="text-danger" v-text=""></div>
							        </div>
							    </div>
							    <div class="form-group row">
							        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.client_name') }}</label>
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
Vue.use(VueScrollactive);

	export default {
		components: {
			Affix,
		},
	    props: ['settings'],
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