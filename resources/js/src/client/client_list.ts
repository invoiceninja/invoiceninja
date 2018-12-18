//import * as Vue from 'vue';
import Vue from 'vue';
import axios from 'axios';
import Vuetable from 'vuetable-2'

Vue.use(Vuetable)

Vue.component('client-list', require('../components/client/ClientList.vue'));
Vue.component('vuetable', require('vuetable-2/src/components/Vuetable'));
Vue.component('vuetable-pagination', require('vuetable-2/src/components/VuetablePagination'));
Vue.component('vuetable-pagination-bootstrap', require('../components/VuetablePaginationBootstrap'));

window.onload = function () {

    const app = new Vue({
        el: '#client_list'
    });

}
