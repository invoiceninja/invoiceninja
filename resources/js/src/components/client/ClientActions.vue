<template>

	<div class="dropdown">
		<button class="btn btn-secondary dropdown-toggle" type="button" id="dropdownMenu" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
		Select
		</button>
			<div class="dropdown-menu" aria-labelledby="dropdownMenu">
				<a class="dropdown-item" href="#">Item</a>
				<a class="dropdown-item" href="#">Another item</a>
				<a class="dropdown-item" href="#" @click="itemAction('view-item', rowData, rowIndex)">One more item</a>
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
        console.log('custom-actions: ' + action, data.name, index)
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
</style>