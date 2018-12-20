<template>
	<div>

      <vuetable ref="vuetable"
	    api-url="/clients/data"
	    :fields="fields"
  		pagination-path=""
      	@vuetable:pagination-data="onPaginationData"
    	></vuetable>
			
		<div class="vuetable-pagination ui basic segment grid">
		    <vuetable-pagination ref="pagination"
        :css="css.pagination"
        @vuetable-pagination:change-page="onChangePage"
      ></vuetable-pagination>
		</div>

	</div>
</template>

<script lang="ts">

import Vuetable from 'vuetable-2/src/components/Vuetable.vue'
import VuetablePagination from 'vuetable-2/src/components/VuetablePagination.vue'
import VuetablePaginationInfo from 'vuetable-2/src/components/VuetablePaginationInfo.vue'
import Vue from 'vue'

export default {
	components: {
        Vuetable,
	      VuetablePagination,
	      VuetablePaginationInfo
	    },
    data () {
        return {
            fields: [
		        'name', 'balance'
		      ],
          css: {
            table: {
              tableClass: 'table table-striped table-bordered table-hovered',
              loadingClass: 'loading',
              ascendingIcon: 'glyphicon glyphicon-chevron-up',
              descendingIcon: 'glyphicon glyphicon-chevron-down',
              handleIcon: 'glyphicon glyphicon-menu-hamburger',
            },
            pagination: {
              infoClass: 'pull-left',
              wrapperClass: 'vuetable-pagination pull-right',
              activeClass: 'btn-primary',
              disabledClass: 'disabled',
              pageClass: 'btn btn-border',
              linkClass: 'btn btn-border',
              icons: {
                first: '',
                prev: '',
                next: '',
                last: '',
              },
            }
          }
        }
    },
    props: ['list'],
    beforeMount: function () {
    },
    methods: {
	    onPaginationData (paginationData : any) {
	      this.$refs.pagination.setPaginationData(paginationData)
	      //this.$refs.paginationInfo.setPaginationData(paginationData) 
	    },
	    onChangePage (page : any) {
	      this.$refs.vuetable.changePage(page)
	    }
	  },
    created:function() {
        
        
    },
    updated:function() {
        
    },

    
}
</script>

<style>
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
</style>