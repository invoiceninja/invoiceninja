import Vue from 'vue'
import Vuex from 'vuex'
import client from './modules/client'

Vue.use(Vuex)

const debug = process.env.NODE_ENV !== 'production'

const store =  new Vuex.Store({
  modules: {
    client
  },
  strict: debug,
})

export default store