<template>

	<div class="row">

		<div class="btn-group">
            <button type="button" class="btn btn-primary btn-lg">Archive</button>
            <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
              <span class="sr-only">Toggle Dropdown</span>
            </button>
            <div class="dropdown-menu" x-placement="bottom-start" style="position: absolute; will-change: transform; top: 0px; left: 0px; transform: translate3d(81px, 38px, 0px);">
              <a class="dropdown-item" href="#">Archive</a>
              <a class="dropdown-item" href="#">Delete</a>
            </div>
        </div>	

        <vuetable-multi-select></vuetable-multi-select>

		<vuetable-query-filter></vuetable-query-filter>
            
        <button class="btn btn-primary btn-lg ">{{ trans('texts.new_client') }}</button>

	</div>

</template>

<script lang="ts">
	
</script>

<style>

</style>