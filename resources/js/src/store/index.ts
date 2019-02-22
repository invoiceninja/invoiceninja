import Vue from 'vue'
import Vuex from 'vuex'
import client_list from './modules/client_list'
import client_settings from './modules/client_settings'

Vue.use(Vuex)

const debug = process.env.NODE_ENV !== 'production'

const store =  new Vuex.Store({
  modules: {
    client_list,
    client_settings
  },
  strict: debug,
})

export default store