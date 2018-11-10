<template>
<div class="container-fluid">  
<div class="row">
    <!-- Client Details -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-primary">{{ trans('texts.edit_client') }}</div>

            <div class="card-body">
                <div class="form-group row">
                    <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.client_name') }}</label>
                    <div class="col-sm-9">
                        <input type="text" name="name" :placeholder="trans('texts.client_name')" v-model="client.name" class="form-control" id="name">
                    </div>
                </div>

                <div class="form-group row">
                    <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.id_number') }}</label>
                    <div class="col-sm-9">
                        <input type="text" name="id_number" :placeholder="trans('texts.id_number')" v-model="client.id_number" class="form-control" id="id_number">
                    </div>
                </div>

                <div class="form-group row">
                    <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.vat_number') }}</label>
                    <div class="col-sm-9">
                        <input type="text" name="vat_number" :placeholder="trans('texts.vat_number')" v-model="client.vat_number" class="form-control" id="vat_number">
                    </div>
                </div>

                <div class="form-group row">
                    <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.website') }}</label>
                    <div class="col-sm-9">
                        <input type="text" name="website" :placeholder="trans('texts.website')" v-model="client.website" class="form-control" id="websites">
                    </div>
                </div>

                <div class="form-group row">
                    <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.custom_value1') }}</label>
                    <div class="col-sm-9">
                        <input type="text" name="custom_value1" :placeholder="trans('texts.custom_value1')" v-model="client.custom_value1" class="form-control" id="custom_value1">
                    </div>
                </div>

                <div class="form-group row">
                    <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.custom_value2') }}</label>
                    <div class="col-sm-9">
                        <input type="text" name="custom_value2" :placeholder="trans('texts.custom_value2')" v-model="client.custom_value2" class="form-control" id="custom_value2">
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- End Client Details -->

    <!-- Contact Details Array -->
    <div class="col-md-6">

        <div class="card">
            <div class="card-header bg-primary">{{ trans('texts.contact_information') }}</div>

                <contact-edit v-for="(contact, index) in client.contacts" v-bind:contact="contact" v-bind:index="index" :key="contact.id" @remove="remove"></contact-edit>

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
