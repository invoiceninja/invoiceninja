<template>
    <div class="container">
      <div class="row">
        <div class="col-md-9 pull-right" style="padding:10px;">
          <div class="input-group">
            <input type="text" v-model="filterText" class="form-control" @keyup.enter="doFilter" placeholder="search">
            <button class="btn btn-primary" @click="doFilter">Go</button>
            <button class="btn btn-light" @click="resetFilter">Reset</button>
          </div>
        </div>
      </div>
    </div>
  </template>

  <script lang="ts">
  
  import Vue from 'vue'

  export default {
    data () {
      return {
        filterText: ''
      }
    },
    methods: {
      doFilter () {
        this.$events.fire('filter-set', this.filterText)
      },
      resetFilter () {
        this.filterText = ''  // clear the text in text input
        this.$events.fire('filter-reset')
      }
    }
  }
</script>
<style>
  .form-inline > * {
     margin:5px 10px;
  }
</style>