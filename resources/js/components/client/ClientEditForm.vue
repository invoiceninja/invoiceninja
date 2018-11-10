<template>
<div class="container-fluid">  
<div class="row">
    <!-- Client Details -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-primary">{{ trans('texts.edit_client') }}</div>

            <client-edit v-bind:client="client"></client-edit>

        </div>
    </div>

    <!-- End Client Details -->

    <!-- Contact Details Array -->
    <div class="col-md-6">

        <div class="card">
            <div class="card-header bg-primary">{{ trans('texts.contact_information') }}</div>

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
<!-- End Contact Details -->
</div>
</div>
</template>

<script>
    import ClientContactEdit from './ClientContactEdit';

export default {
    data: function () {
        return {
            'client': []
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
    components: { ClientContactEdit },
    methods: {
        remove (itemId) {
            this.client.contacts = this.client.contacts.filter(function (item) {
                return itemId != item.id;
            });
        }
    },
    created:function() {
        console.dir('created');
    },
    updated:function() {
        console.dir('updated');
    }
}
</script>
