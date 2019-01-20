<template>
  <div style="width:300px;">
    <multiselect v-model="value" 
    :options="options" 
    :multiple="true"
    :placeholder="trans('texts.status')"
    :preselect-first="true"
    label="name"
    track-by="name"
    @input="onChange"
    ></multiselect>
  </div>
</template>

<script lang="ts">

  import Multiselect from 'vue-multiselect'

  export default {
    components: { Multiselect },
    props:['select_options'],
    data () {
      return {
        value : [],
        options: this.select_options
      }
    },
    mounted() {

      this.$events.fire('multi-select', '')
    
    },
    methods: {
      onChange (value) {

        this.$store.commit('client_list/setStatusArray', value)
        this.$events.fire('multi-select', '')

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
