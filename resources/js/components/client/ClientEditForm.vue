<template>
    <form @submit.prevent="submit">
        <div class="container-fluid">
            <div class="row form-group">
                <div class="col-md-12">
                    <span class="float-right">
                        <div class="btn-group ml-2">
                            <button class="btn btn-lg btn-success" type="button" @click="submit"><i class="fa fa-save"></i> {{ trans('texts.save') }}</button>
                            <button class="btn btn-lg btn-success dropdown-toggle dropdown-toggle-split" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <span class="sr-only">Toggle Dropdown</span>
                            </button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item" href="#"><i class="fa fa-plus-circle"></i> {{ trans('texts.add_contact') }}</a>
                                    <div class="dropdown-divider"></div>
                                        <a class="dropdown-item" href="#">{{ trans('texts.archive_client') }}</a>
                                        <a class="dropdown-item" href="#">{{ trans('texts.delete_client') }}</a>
                            </div>
                        </div>            
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
            </div>
        </div>
    </form>
</template>

<script>
export default {
    data: function () {
        return {
            'client': [],
            'errors': [],
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
            

            axios.put('/clients/' + this.client.hash_id, this.client).then(response => {
                this.client = response.data;
                console.dir(response);
            }).catch(error => {
                if (error.response.status === 422) {
                this.errors = error.response.data.errors || {};
                }
            });
        },
      
    },
    created:function() {
        //console.dir('created');
        
    },
    updated:function() {
        //console.dir('updated');
    }
    
}
</script>
