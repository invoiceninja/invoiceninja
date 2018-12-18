<template>
	<div>

      <vuetable ref="vuetable"
	    api-url="/clients/data"
	    :fields="fields"
  		pagination-path=""
      	@vuetable:pagination-data="onPaginationData"
    	></vuetable>
			
		<div class="vuetable-pagination ui basic segment grid">
		    <vuetable-pagination-bootstrap ref="pagination"
		      @vuetable-pagination:change-page="onChangePage"
		    ></vuetable-pagination-bootstrap>
		</div>

	</div>
</template>

<script lang="ts">


export default {

    data () {
        return {
            fields: [
		        'name', 'balance'
		      ]
        }
    },
    props: ['list'],
    beforeMount: function () {
    },
    methods: {
	    onPaginationData (paginationData) {
	      this.$refs.pagination.setPaginationData(paginationData)
	      this.$refs.paginationInfo.setPaginationData(paginationData) 
	    },
	    onChangePage (page) {
	      this.$refs.vuetable.changePage(page)
	    }
	  },
    created:function() {
        
        
    },
    updated:function() {
        
    }
    
}
</script>