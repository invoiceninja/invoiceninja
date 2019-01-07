//import * as Vue from 'vue';
import Vue from 'vue';
import axios from 'axios';

Vue.component('client-list', require('../components/client/ClientList.vue'));
Vue.component('client-actions', require('../components/client/ClientActions.vue'));
Vue.component('vuetable', require('vuetable-2/src/components/Vuetable'));
Vue.component('vuetable-pagination', require('vuetable-2/src/components/VuetablePagination'));
Vue.component('vuetable-pagination-bootstrap', require('../components/util/VuetablePaginationBootstrap'));
Vue.component('vuetable-filter-bar', require('../components/util/VuetableFilterBar'));

window.onload = function () {

    const app = new Vue({
        el: '#client_list'
    });

}
