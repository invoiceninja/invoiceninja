<template>
  
        <div class="input-group">

          <input type="text" v-model="filterText" class="form-control" @keyup.enter="doFilter" placeholder="search">
          <button class="btn btn-primary" @click="doFilter">Go</button>
          <!--<button class="btn btn-light" @click="resetFilter">Reset</button>-->

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

        this.$store.commit('client_list/setFilterText', this.filterText)
        this.$events.fire('filter-set','')

      },
      resetFilter () {

        this.$store.commit('client_list/setFilterText', '')
        this.$events.fire('filter-set','')

      }
    }
  }
</script>

<style>

  .form-inline > * {
     margin:5px 10px;
  }
  .form-control {
    min-height: 40px;
  }

</style>