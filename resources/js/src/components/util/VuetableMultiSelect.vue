<!-- Vue component -->
<template>
  <div style="width:300px;">
    <multiselect v-model="value" 
    :options="options" 
    :multiple="true"
    :placeholder="trans('texts.status')"
    @input="onChange"
    ></multiselect>
  </div>
</template>

<script lang="ts">

  import Multiselect from 'vue-multiselect'

  export default {
    // OR register locally
    components: { Multiselect },
    data () {
      return {
        value: 'active',
        options: ['active', 'archived', 'deleted']
      }
    },
    methods: {
      onChange (value) {
        console.dir(this.value)
        this.value = value
        if (value.indexOf('Reset me!') !== -1) this.value = []
      },
      onSelect (option) {
        if (option === 'Disable me!') this.isDisabled = true
      }
    }
  }
</script>

<style src="vue-multiselect/dist/vue-multiselect.min.css"></style>

<style>
</style>
