/* Allows us to use our native translation easily using {{ trans() }} syntax */
//const _ = require('lodash');

require('../bootstrap');

/* Must be declare in every child view*/
declare var i18n;

import Vue from 'vue';

/**
 * Next, we will create a fresh Vue application instance and attach it to
 * the page. Then, you may begin adding components to this application
 * or customize the JavaScript scaffolding to fit your unique needs.
*/
Vue.component('client-edit', require('../components/client/ClientEdit.vue'));
Vue.component('client-address', require('../components/client/ClientAddress.vue'));
Vue.component('generic-address', require('../components/generic/Address.vue'));
Vue.component('client-edit-form', require('../components/client/ClientEditForm.vue'));
Vue.component('contact-edit', require('../components/client/ClientContactEdit.vue'));
 
window.onload = function () {

    const app = new Vue({
        el: '#client_edit'
    });

}
