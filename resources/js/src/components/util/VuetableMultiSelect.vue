<!-- Vue component -->
<template>
  <div style="width:300px;">
    <multiselect v-model="value" 
    :options="options" 
    :multiple="true"
    :placeholder="trans('texts.status')"
    :preselect-first="true"
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
        value : [],
        options: ['active', 'archived', 'deleted']
      }
    },
    mounted(){
     console.dir('mounted')
      this.$events.fire('multi-select', this.value)
    },
    methods: {
      onChange (value) {
        this.value = value
        console.dir(this.value)
        this.$events.fire('multi-select', this.value)

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
