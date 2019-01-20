const state = {
  statuses: ['active'],
  filter_text: '',
  bulk_count : 0
}

// getters
const getters = {
  getBulkCount: state => {

    return state.bulk_count
  
  }, 
	getFilterText: state => {

		return state.filter_text

	},
  getQueryStringObject: state => {

    var values = state.statuses.map(function (state, index, array) {
         return state.value; 
    });

    var queryObj = {
      filter: state.filter_text,
      status: [].concat.apply([], values).join(",")
    }

    return queryObj
  }
}

// actions
const actions = {

}

// mutations
const mutations = {
	setFilterText(state, text) {

    state.filter_text = text

	},
  setStatusArray(state, statuses) {
    
    state.statuses = statuses

  },
  setBulkCount(state, count) {

    state.bulk_count = count

  }
}

export default {

  namespaced: true,
  state,
  getters,
  actions,
  mutations
  
}