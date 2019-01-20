<template>

	<div class="d-flex justify-content-start">

    <div class="p-2">

    		<div class="btn-group">
          <button type="button" class="btn btn-primary btn-lg" @click="archive" :disabled="getBulkCount() == 0">{{ trans('texts.archive') }} <span v-if="getBulkCount() > 0">({{ getBulkCount() }})</span></button>
          <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" :disabled="getBulkCount() == 0">
            <span class="sr-only">Toggle Dropdown</span>
          </button>
          <div class="dropdown-menu" x-placement="bottom-start" style="position: absolute; will-change: transform; top: 0px; left: 0px; transform: translate3d(81px, 38px, 0px);">
            <a class="dropdown-item" @click="archive" href="#">{{ trans('texts.archive') }}</a>
            <a class="dropdown-item" @click="del" href="#">{{ trans('texts.delete') }}</a>
          </div>
        </div>	
            
    </div>

    <div class="p-2">
      <vuetable-multi-select :select_options="listaction.multi_select"></vuetable-multi-select>
    </div>

    <div class="mr-auto p-2">
      <div class="input-group mb-3">

        <select class="custom-select" id="per_page" v-model="per_page" @change="updatePerPage()">
          <option value="10">10</option>
          <option value="25">25</option>
          <option value="50">50</option>
          <option value="100">100</option>
        </select>
        
        <div class="input-group-append">
          <label class="input-group-text" for="per_page">{{trans('texts.rows')}}</label>
        </div>

      </div>
    </div>

    <div class="ml-auto p-2">
    	<vuetable-query-filter></vuetable-query-filter>
    </div>

    <div class="p-2">
      <button class="btn btn-primary btn-lg " @click="goToUrl(listaction.create_entity.url)" :disabled="isDisabled">{{ trans('texts.new_client') }}</button>
    </div>

	</div>

</template>

<script lang="ts">

  export default {
    props: {
      listaction: {
        type: Object,
        required: true
      },
      per_page_prop: {
        type: Number,
        required: true
      }
    },  
    data () {
      return {

        per_page: this.per_page_prop

      }
    },
    methods: {
      archive () {

        this.$events.fire('bulk-action', 'archive') 

      },
      del () {

        this.$events.fire('bulk-action', 'delete')

      },
      getBulkCount() {

        return this.$store.getters['client_list/getBulkCount']

      },
      goToUrl: function (url) {

        location.href=url

      },
      updatePerPage() {

        this.$events.fire('perpage_action', this.per_page)
      
      }
    },
   computed: {
      isDisabled() :any
      {
        return !this.listaction.create_entity.create_permission;
      }
   }

  }
</script>

<style>
select.custom-select {
    height: 42px;
  }
</style>