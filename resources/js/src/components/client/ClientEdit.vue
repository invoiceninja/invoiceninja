<template>
<div class="card-body">
    <div class="form-group row">
        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.client_name') }}</label>
        <div class="col-sm-9">
            <input type="text" :placeholder="trans('texts.client_name')" v-model="client.name" class="form-control">
                 <div v-if="client.errors.has('name')" class="text-danger" v-text="client.errors.get('name')"></div>
        </div>
    </div>

    <div class="form-group row">
        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.id_number') }}</label>
        <div class="col-sm-9">
            <input type="text" name="id_number" :placeholder="trans('texts.id_number')" v-model="client.id_number" class="form-control" id="id_number">
            <div v-if="client.errors.has('id_number')" class="text-danger" v-text="client.errors.get('id_number')"></div>
        </div>
    </div>

    <div class="form-group row">
        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.vat_number') }}</label>
        <div class="col-sm-9">
            <input type="text" name="vat_number" :placeholder="trans('texts.vat_number')" v-model="client.vat_number" class="form-control" id="vat_number">
            <div v-if="client.errors.has('vat_number')" class="text-danger" v-text="client.errors.get('vat_number')"></div>
        </div>
    </div>

    <div class="form-group row">
        <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.website') }}</label>
        <div class="col-sm-9">
            <input type="text" name="website" :placeholder="trans('texts.website')" v-model="client.website" class="form-control" id="websites">
            <div v-if="client.errors.has('website')" class="text-danger" v-text="client.errors.get('website')"></div>
        </div>
    </div>

    <div class="form-group row" v-if="company.custom_client_label1 && company.custom_client_label1.length >= 1">
        <label for="name" class="col-sm-3 col-form-label text-right">{{ company.custom_client_label1 }}</label>
        <div class="col-sm-9">
            <input type="text" name="custom_value1" :placeholder="trans('texts.custom_value1')" v-model="client.custom_value1" class="form-control" id="custom_value1">
            <div v-if="client.errors.has('custom_value1')" class="text-danger" v-text="client.errors.get('custom_value1')"></div>
        </div>
    </div>

    <div class="form-group row" v-if="company.custom_client_label2 && company.custom_client_label2.length >= 1">
        <label for="name" class="col-sm-3 col-form-label text-right">{{ company.custom_client_label2 }}</label>
        <div class="col-sm-9">
            <input type="text" name="custom_value2" :placeholder="trans('texts.custom_value1')" v-model="client.custom_value2" class="form-control" id="custom_value2">
            <div v-if="client.errors.has('custom_value2')" class="text-danger" v-text="client.errors.get('custom_value2')"></div>
        </div>
    </div>

    <div class="form-group row" v-if="company.custom_client_label3 && company.custom_client_label3.length >= 1">
        <label for="name" class="col-sm-3 col-form-label text-right">{{ company.custom_client_label3 }}</label>
        <div class="col-sm-9">
            <input type="text" name="custom_value3" :placeholder="trans('texts.custom_value1')" v-model="client.custom_value3" class="form-control" id="custom_value3">
            <div v-if="client.errors.has('custom_value3')" class="text-danger" v-text="client.errors.get('custom_value2')"></div>
        </div>
    </div>

    <div class="form-group row" v-if="company.custom_client_label4 && company.custom_client_label4.length >= 1">
        <label for="name" class="col-sm-3 col-form-label text-right">{{ company.custom_client_label2 }}</label>
        <div class="col-sm-9">
            <input type="text" name="custom_value4" :placeholder="trans('texts.custom_value1')" v-model="client.custom_value4" class="form-control" id="custom_value4">
            <div v-if="client.errors.has('custom_value4')" class="text-danger" v-text="client.errors.get('custom_value4')"></div>
        </div>
    </div>
</div>
</template>
<script>
    export default {
        props: ['client','errors', 'company']
    }
</script>	