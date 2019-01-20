<template>

	<div>

      <vuetable ref="vuetable"
	      api-url="/clients"
	      :fields="fields"
      	:per-page="perPage"
      	:sort-order="sortOrder"
      	:append-params="moreParams"
        :css="css.table"
  		  pagination-path=""
        @vuetable:checkbox-toggled="toggledCheckBox()"
        @vuetable:checkbox-toggled-all="toggledCheckBox()"
      	@vuetable:pagination-data="onPaginationData"></vuetable>

  		<div class="vuetable-pagination ui basic segment grid">

        	<vuetable-pagination-info ref="paginationInfo"></vuetable-pagination-info>

        	<vuetable-pagination ref="pagination"
        	:css="css.pagination"
        	@vuetable-pagination:change-page="onChangePage"></vuetable-pagination>

    	</div>

  </div>

</template>

<script lang="ts">

import Vuetable from 'vuetable-2/src/components/Vuetable.vue'
import VuetablePagination from 'vuetable-2/src/components/VuetablePagination.vue'
import VuetablePaginationInfo from 'vuetable-2/src/components/VuetablePaginationInfo.vue'
import Vue from 'vue'
import VueEvents from 'vue-events'
import VuetableCss from '../util/VuetableCss'
import axios from 'axios'

Vue.use(VueEvents)

declare var bulk_count : number;

export default {

	components: {
        	Vuetable,
	      	VuetablePagination,
	      	VuetablePaginationInfo
	},
  data: function () {
      return {
          css: VuetableCss,
          perPage: this.datatable.per_page,
          sortOrder: this.datatable.sort_order,
          moreParams: this.$store.getters['client_list/getQueryStringObject'],
          fields: this.datatable.fields
      }
  },
  props: ['datatable'],
  mounted() {

    this.$events.$on('filter-set', eventData => this.onFilterSet())
    this.$events.$on('bulk-action', eventData => this.bulkAction(eventData))
    this.$events.$on('multi-select', eventData => this.multiSelect(eventData))
    this.$events.$on('single-action', eventData => this.singleAction(eventData))
    this.$events.$on('perpage_action', eventData => this.onPerPageUpdate(eventData))

  },
  methods: {

    onPaginationData (paginationData : any) {

      this.$refs.pagination.setPaginationData(paginationData)
      this.$refs.paginationInfo.setPaginationData(paginationData) 

    },
    onChangePage (page : any) {

		this.$refs.vuetable.changePage(page)

    },
	  onFilterSet () {

      this.moreParams = this.$store.getters['client_list/getQueryStringObject']
			Vue.nextTick( () => this.$refs.vuetable.refresh())

	  },
    onPerPageUpdate(per_page){

      this.perPage = Number(per_page)
      Vue.nextTick( () => this.$refs.vuetable.refresh())

    },
    bulkAction (action){

      var dataObj = {
        'action' : action,
        'ids' : this.$refs.vuetable.selectedTo
      }

      this.postBulkAction(dataObj)

    },
    singleAction(dataObj) {

      this.postBulkAction(dataObj)

    },
    postBulkAction(dataObj) {

      axios.post('/clients/bulk', dataObj)
      .then((response) => {
        this.$root.$refs.toastr.s( Vue.prototype.trans('texts.'+dataObj.action+'d_client') )
        this.$store.commit('client_list/setBulkCount', 0)
        this.$refs.vuetable.selectedTo = []
        this.$refs.vuetable.refresh()
//        console.dir(response)
      })
      .catch(function (error) {
        this.$root.$refs.toastr.e( "A error occurred" )
      });

    },
    toggledCheckBox(){
      this.$store.commit('client_list/setBulkCount', this.$refs.vuetable.selectedTo.length)
    },
    multiSelect(value)
    {
      this.moreParams = this.$store.getters['client_list/getQueryStringObject']
      Vue.nextTick( () => this.$refs.vuetable.refresh())
    }

 }
}
</script>

<style type="text/css">

  .pagination {
    margin: 0;
    float: right;
  }
  .pagination a.page {
    border: 1px solid lightgray;
    border-radius: 3px;
    padding: 5px 10px;
    margin-right: 2px;
  }
  .pagination a.page.active {
    color: white;
    background-color: #337ab7;
    border: 1px solid lightgray;
    border-radius: 3px;
    padding: 5px 10px;
    margin-right: 2px;
  }
  .pagination a.btn-nav {
    border: 1px solid lightgray;
    border-radius: 3px;
    padding: 5px 7px;
    margin-right: 2px;
  }
  .pagination a.btn-nav.disabled {
    color: lightgray;
    border: 1px solid lightgray;
    border-radius: 3px;
    padding: 5px 7px;
    margin-right: 2px;
    cursor: not-allowed;
  }
  .pagination-info {
    float: left;
  }
  th {
    background: #777777;
    color: #fff;
  }

</style>