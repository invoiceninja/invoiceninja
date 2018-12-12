
import Vue from 'vue';
import VueSelect from 'vue-select';

Vue.component('v-select', VueSelect.VueSelect)

new Vue({
  el: '#localization',
  data: {
    options: ['jim','bob','frank'],
    selected: 'frank',
  }
})