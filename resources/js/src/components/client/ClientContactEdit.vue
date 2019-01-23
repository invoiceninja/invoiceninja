<template>
    <div class="card-body">
        <div class="form-group row">
            <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.first_name') }}</label>
            <div class="col-sm-9">
                <input ref="first_name" name="first_name" type="text" :placeholder="trans('texts.first_name')" v-model="contact.first_name" class="form-control">
                <div v-if="form.errors.has('contacts.'+error_index+'.first_name')" class="text-danger" v-text="form.errors.get('contacts.'+error_index+'.first_name')"></div>
            </div>
        </div>

        <div class="form-group row">
            <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.last_name') }}</label>
            <div class="col-sm-9">
                <input type="text" :placeholder="trans('texts.last_name')" v-model="contact.last_name" class="form-control">
                <div v-if="form.errors.has('contacts.'+error_index+'.last_name')" class="text-danger" v-text="form.errors.get('contacts.'+error_index+'.last_name')"></div>
            </div>
        </div>

        <div class="form-group row">
            <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.email') }}</label>
            <div class="col-sm-9">
                <input type="email" :placeholder="trans('texts.email')" v-model="contact.email" class="form-control">
                <div v-if="form.errors.has('contacts.'+error_index+'.email')" class="text-danger" v-text="form.errors.get('contacts.'+error_index+'.email')"></div>
            </div>
        </div>

        <div class="form-group row">
            <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.phone') }}</label>
            <div class="col-sm-9">
                <input type="text" :placeholder="trans('texts.phone')" v-model="contact.phone" class="form-control">
                <div v-if="form.errors.has('contacts.'+error_index+'.phone')" class="text-danger" v-text="form.errors.get('contacts.'+error_index+'.phone')"></div>
            </div>
        </div>

        <div class="form-group row" v-if="company.custom_client_contact_label1">
            <label for="name" class="col-sm-3 col-form-label text-right">{{ company.custom_client_contact_label1 }}</label>
            <div class="col-sm-9">
                <input type="text" :placeholder="trans('texts.custom_value1')" v-model="contact.custom_value1" class="form-control">
                <div v-if="form.errors.has('contacts.'+error_index+'.custom_value1')" class="text-danger" v-text="form.errors.get('contacts.'+error_index+'.custom_value1')"></div>
            </div>
        </div>

        <div class="form-group row" v-if="company.custom_client_contact_label2">
            <label for="name" class="col-sm-3 col-form-label text-right">{{ company.custom_client_contact_label2 }}</label>
            <div class="col-sm-9">
                <input type="text" :placeholder="trans('texts.custom_value1')" v-model="contact.custom_value2" class="form-control">
                <div v-if="form.errors.has('contacts.'+error_index+'.custom_value2')" class="text-danger" v-text="form.errors.get('contacts.'+error_index+'.custom_value2')"></div>
            </div>
        </div>

        <div class="form-group row" v-if="company.custom_client_contact_label3">
            <label for="name" class="col-sm-3 col-form-label text-right">{{ company.custom_client_contact_label3 }}</label>
            <div class="col-sm-9">
                <input type="text" :placeholder="trans('texts.custom_value1')" v-model="contact.custom_value3" class="form-control">
                <div v-if="form.errors.has('contacts.'+error_index+'.custom_value3')" class="text-danger" v-text="form.errors.get('contacts.'+error_index+'.custom_value3')"></div>
            </div>
        </div>

        <div class="form-group row" v-if="company.custom_client_contact_label4">
            <label for="name" class="col-sm-3 col-form-label text-right">{{ company.custom_client_contact_label4 }}</label>
            <div class="col-sm-9">
                <input type="text" :placeholder="trans('texts.custom_value1')" v-model="contact.custom_value4" class="form-control">
                <div v-if="form.errors.has('contacts.'+error_index+'.custom_value4')" class="text-danger" v-text="form.errors.get('contacts.'+error_index+'.custom_value4')"></div>
            </div>
        </div>
        <div class="float-right">
            <button type="button" class="btn btn-danger" v-on:click="$emit('remove',contact)"> {{ trans('texts.remove_contact') }}</button>
        </div>
    </div>
</template>

<script>
    export default {
        props: ['company', 'contact', 'form', 'error_index']
    }
</script>
