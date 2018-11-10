
/**
 * First we will load all of this project's JavaScript dependencies which
 * includes Vue and other libraries. It is a great starting point when
 * building robust, powerful web applications using Vue and Laravel.
 */

require('./bootstrap');
window.Vue = require('vue');
/* Development only*/
Vue.config.devtools = true;

window.axios = require('axios');
window.axios.defaults.headers.common = {
    'X-Requested-With': 'XMLHttpRequest',
    'X-CSRF-TOKEN' : document.querySelector('meta[name="csrf-token"]').getAttribute('content')
};

/* Allows us to use our native translation easily using {{ trans() }} syntax */
const _ = require('lodash');
Vue.prototype.trans = string => _.get(window.i18n, string);

/**
 * Next, we will create a fresh Vue application instance and attach it to
 * the page. Then, you may begin adding components to this application
 * or customize the JavaScript scaffolding to fit your unique needs.
 */

Vue.component('example-component', require('./components/ExampleComponent.vue'));
Vue.component('client-edit', require('./components/client/ClientEdit.vue'));
Vue.component('client-edit-form', require('./components/client/ClientEditForm.vue'));
Vue.component('contact-edit', require('./components/client/ClientContactEdit.vue'));

window.onload = function () {

	const app = new Vue({
	    el: '#app'
	});

}
