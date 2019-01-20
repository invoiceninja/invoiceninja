<template>

	<div class="dropdown">
		<button class="btn btn-secondary dropdown-toggle" type="button" id="dropdownMenu" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
		{{ trans('texts.select') }}
		</button>
			<div class="dropdown-menu" aria-labelledby="dropdownMenu">
				<a class="dropdown-item" :href="action.url" v-for="action in rowData.actions">{{ action.name }}</a>
        <div class="dropdown-divider"></div>
        <a class="dropdown-item" href="#" @click="itemAction('archive', rowData, rowIndex)" v-if="rowData.deleted_at == null">{{ trans('texts.archive') }}</a>
        <a class="dropdown-item" href="#" @click="itemAction('restore', rowData, rowIndex)" v-if="rowData.is_deleted == 1 || rowData.deleted_at != null">{{ trans('texts.restore') }}</a>
        <a class="dropdown-item" href="#" @click="itemAction('delete', rowData, rowIndex)" v-if="rowData.is_deleted == 0">{{ trans('texts.delete') }}</a>
			</div>
	</div>

</template>

<script>
  export default {
    props: {
      rowData: {
        type: Object,
        required: true
      },
      rowIndex: {
        type: Number
      }
    },
    methods: {
      itemAction (action, data, index) {

        this.$events.fire('single-action', {'action': action, 'ids': [data.id]})
      
      }
    }
  }
</script>

<style>
	.custom-actions button.ui.button {
	  padding: 8px 8px;
	}
	.custom-actions button.ui.button > i.icon {
	  margin: auto !important;
	}
  .dropdown-item {
    outline:0px; 
    border:0px; 
    font-weight: bold;
  }

</style>