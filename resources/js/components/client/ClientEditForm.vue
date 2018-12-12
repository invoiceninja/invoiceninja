<template>
    <form @submit.prevent="onSubmit" @keydown="form.errors.clear($event.target.name)">
        <div class="container-fluid">
            
            <div class="row">
                <!-- Client Details and Address Column -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-primary2">{{ trans('texts.edit_client') }}</div>
                            <client-edit :client="form"></client-edit>
                    </div>

                    <div class="card">
                        <div class="card-header bg-primary2">{{ trans('texts.address') }}</div>
                            <client-address v-bind:client="form" @copy="copy"></client-address>
                    </div>
                </div>
                <!-- End Client Details and Address Column -->

                <!-- Contact Details Column -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-primary2">{{ trans('texts.contact_information') }}
                            <span class="float-right">
                                <button type="button" class="btn btn-primary btn-sm" @click="add"><i class="fa fa-plus-circle"></i> {{ trans('texts.add_contact') }}</button>
                            </span>
                        </div>
                            <contact-edit   v-for="(contact, key, index) in form.contacts" 
                                            :contact="contact" 
                                            :form="form"
                                            :key="contact.id"
                                            :error_index="key"
                                            @remove="remove"></contact-edit>
                    </div>    
                </div>     
                <!-- End Contact Details Column --> 
            </div>     

            <div class="row"> 

                <div class="col-md-12 text-center">

                    <button class="btn btn-lg btn-success" type="button" @click="onSubmit"><i class="fa fa-save"></i> {{ trans('texts.save') }}</button>

                </div>

            </div>   

        </div>

    </form>
    
</template>

<script lang="ts">


import Vue from 'vue';
import axios from 'axios';
import Form from '../../src/utils/form';
import Client from '../../src/models/client-model';

export default {
    data: function () {
        return {
            form: new Form(<Client>this.clientdata)
        }
    },
    props: ['hashed_id', 'clientdata'],
    beforeMount: function () {
    },
    methods:{
        remove(this:any, contact:any){
            let index = this.form.contacts.indexOf(contact);
            this.form.contacts.splice(index, 1);
        },
        add(this: any){
            this.form.contacts.push({first_name: '', last_name: '', email: '', phone: '', id: 0});
            window.scrollTo(0, document.body.scrollHeight || document.documentElement.scrollHeight);
            this.$nextTick(() => {
                     let index = this.form.contacts.length - 1;
                     //this.$refs.first_name[index].$el.focus();
                     //this.$refs.first_name[index].focus();
                  });
        },
        onSubmit() {
            this.form.put('/clients/' + this.hashed_id)
                .then(response => this.$root.$refs.toastr.s("Saved client"))
                .catch(error => {

                    this.$root.$refs.toastr.e("Error saving client");

                });
        },
        copy(type: any) {
            if(type.includes('copy_billing')){
                this.form.shipping_address1 = this.form.address1; 
                this.form.shipping_address2 = this.form.address2; 
                this.form.shipping_city = this.form.city; 
                this.form.shipping_state = this.form.state; 
                this.form.shipping_postal_code = this.form.postal_code; 
                this.form.shipping_country_id = this.form.country_id; 
                }else {
                this.form.address1 = this.form.shipping_address1; 
                this.form.address2 = this.form.shipping_address2; 
                this.form.city = this.form.shipping_city; 
                this.form.state = this.form.shipping_state; 
                this.form.postal_code = this.form.shipping_postal_code; 
                this.form.country_id = this.form.shipping_country_id; 
            }
        }
    },
    created:function() {
        
        
    },
    updated:function() {
        
    }
    
}
</script>
