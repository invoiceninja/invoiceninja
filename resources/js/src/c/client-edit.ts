/* Allows us to use our native translation easily using {{ trans() }} syntax */
//const _ = require('lodash');
import * as _ from "lodash"
declare var i18n;

import Vue from 'vue';
import axios from 'axios';
import Form from '../utils/form';
import Client from '../models/client-model';

// import Toastr
import Toastr from 'vue-toastr';
// import toastr scss file: need webpack sass-loader
require('vue-toastr/src/vue-toastr.scss');
// Register vue component
Vue.component('vue-toastr',Toastr);

Vue.prototype.trans = string => _.get(i18n, string);

/**
 * Next, we will create a fresh Vue application instance and attach it to
 * the page. Then, you may begin adding components to this application
 * or customize the JavaScript scaffolding to fit your unique needs.
*/
Vue.component('example-component', require('../../components/ExampleComponent.vue'));
Vue.component('client-edit', require('../../components/client/ClientEdit.vue'));
Vue.component('client-address', require('../../components/client/ClientAddress.vue'));
Vue.component('generic-address', require('../../components/generic/Address.vue'));
Vue.component('client-edit-form', require('../../components/client/ClientEditForm.vue'));
Vue.component('contact-edit', require('../../components/client/ClientContactEdit.vue'));

 
window.onload = function () {

	const app = new Vue({
	    el: '#client_e'
	});

}
