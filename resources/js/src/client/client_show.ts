/* Allows us to use our native translation easily using {{ trans() }} syntax */
//const _ = require('lodash');

require('../bootstrap');

/* Must be declare in every child view*/
declare var i18n;

import Vue from 'vue';

Vue.component('client-edit', require('../components/client/ClientEdit.vue'));

 
window.onload = function () {

    const app = new Vue({
        el: '#client_show'
    });

}
