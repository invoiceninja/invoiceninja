/* Allows us to use our native translation easily using {{ trans() }} syntax */
//const _ = require('lodash');

require('../bootstrap');

/* Must be declare in every child view*/
declare var i18n;

import Vue from 'vue';

Vue.component('client-edit', require('../components/client/ClientEdit.vue'));
Vue.component('client-address', require('../components/client/ClientAddress.vue'));
Vue.component('generic-address', require('../components/generic/Address.vue'));
Vue.component('client-edit-form', require('../components/client/ClientEditForm.vue'));
Vue.component('contact-edit', require('../components/client/ClientContactEdit.vue'));
Vue.component('client-settings', require('../components/client/ClientSettings.vue'));

window.onload = function () {

    const app = new Vue({
        el: '#client_edit'
    });

}
