import Vue from 'vue'
import Vuex from 'vuex'
import client_list from './modules/client_list'

Vue.use(Vuex)

const debug = process.env.NODE_ENV !== 'production'

const store =  new Vuex.Store({
  modules: {
    client_list
  },
  strict: debug,
})

export default store