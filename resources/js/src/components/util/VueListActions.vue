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

    <div class="mr-auto p-2">
      <vuetable-multi-select></vuetable-multi-select>
    </div>

    <div class="ml-auto p-2">
    	<vuetable-query-filter></vuetable-query-filter>
    </div>

    <div class="p-2">
      <button class="btn btn-primary btn-lg " v-on:click="$emit('bulk','poota')">{{ trans('texts.new_client') }}</button>
    </div>

	</div>

</template>

<script lang="ts">

  export default {
    methods: {
      archive () {
        this.$events.fire('bulk-action', 'archive') 
      },
      del () {
        this.$events.fire('bulk-action', 'delete')
      },
      getBulkCount() {
        return this.$store.getters['client_list/getBulkCount']
      }
    }

  }
</script>

<style>

</style>