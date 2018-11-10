<template>
    <form @submit.prevent="submit">
        <div class="container-fluid">

            <div class="row form-group">
                <div class="col-md-12">
                    <span class="float-right">
                            <div class="btn-group ml-2 show">
                                <button class="btn btn-lg btn-success" type="button"><i class="fa fa-save"></i> {{ trans('texts.save') }}</button>
                                <button class="btn btn-lg btn-success dropdown-toggle dropdown-toggle-split" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true">
                                <span class="sr-only">Toggle Dropdown</span>
                                </button>
                                <div class="dropdown-menu show" x-placement="bottom-start" style="position: absolute; will-change: transform; top: 0px; left: 0px; transform: translate3d(171px, 44px, 0px);">
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
              
              axios.put('/clients', this.fields).then(response => {
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
