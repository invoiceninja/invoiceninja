<template>
    <form @submit.prevent="submit">
        <div class="container-fluid">

            <div class="row form-group">
                <div class="col-md-12">
                    <span class="float-right">
                        <button type="button" class="btn btn-success btn-lg"><i class="fa fa-save"></i> {{ trans('texts.save') }}</button>
                    </span>
                </div>
            </div>

            <div class="row">

                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-primary2">{{ trans('texts.edit_client') }}</div>

                            <client-edit :client="client" :errors="errors"></client-edit>

                        </div>
                    </div>

                    <div class="col-md-6">

                        <div class="card">
                            <div class="card-header bg-primary2">{{ trans('texts.contact_information') }}
                                <span class="float-right">
                                    <button type="button" class="btn btn-primary btn-sm"><i class="fa fa-plus-circle"></i> {{ trans('texts.add_contact') }}</button>
                                </span>
                            </div>

                                <contact-edit   v-for="(contact, index) in client.contacts" 
                                                v-bind:contact="contact" 
                                                v-bind:index="index" 
                                                :key="contact.id" 
                                                @remove="remove"></contact-edit>

                        </div>    

                    </div>

                    <div class="float-right">
                            <button type="button" class="btn btn-primary" v-on:click="addContact()"> {{ trans('texts.add_contact') }}</button>
                    </div>  

          
            </div>
        </div>
    </form>
</template>

<script>
export default {
    data: function () {
        return {
            'client': [],
            'errors': []
        }
    },
    props: {
        clientdata: {
            type: [Object,Array],
            default: () => []
        }
    },
    beforeMount: function () {
        this.client = this.clientdata;
    },
    methods: {
        remove (itemId) {
            this.client.contacts = this.client.contacts.filter(function (item) {
                return itemId != item.id;
            });
        },
        submit() {
            this.errors = {};
              
              axios.post('/clients', this.fields).then(response => {
                alert('Message sent!');
              }).catch(error => {
                    if (error.response.status === 422) {
                    this.errors = error.response.data.errors || {};
                    }
                });
        }
      
    },
    created:function() {
        //console.dir('created');
    },
    updated:function() {
        //console.dir('updated');
    }
}
</script>
