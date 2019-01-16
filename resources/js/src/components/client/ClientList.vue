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
            moreParams: {},
            fields: this.datatable.fields
        }
    },
    props: ['datatable'],
    mounted() {

      this.$events.$on('filter-set', eventData => this.onFilterSet(eventData))
      this.$events.$on('filter-reset', e => this.onFilterReset())
      this.$events.$on('bulkAction', eventData => this.bulk(eventData))
      //this.$events.$on('vuetable:checkbox-toggled-all', eventData => this.checkboxToggled(eventData))

    },
    beforeMount: function () {

    },
    methods: {

	    onPaginationData (paginationData : any) {

	      this.$refs.pagination.setPaginationData(paginationData)
	      this.$refs.paginationInfo.setPaginationData(paginationData) 

	    },
	    onChangePage (page : any) {

			this.$refs.vuetable.changePage(page)

	    },
		  onFilterSet (filterText) {

  			this.moreParams = {
  			    'filter': filterText
  			}
  			Vue.nextTick( () => this.$refs.vuetable.refresh())

		  },
  		onFilterReset () {
  		  	this.moreParams = {}
  		  	Vue.nextTick( () => this.$refs.vuetable.refresh())
  		},
      bulk (eventData){
        //console.log(eventData)
        //console.dir(this.$refs.vuetable.selectedTo)
      },
      toggledCheckBox(){
        console.log(this.$refs.vuetable.selectedTo.length +' Checkboxes checked')
        this.$events.fire('bulk-count', this.$refs.vuetable.selectedTo.length)
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