<template>
    <div class="card-body">
        <div class="form-group row">
            <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.first_name') }}</label>
            <div class="col-sm-9">
                <input type="text" name="first_name" :placeholder="trans('texts.first_name')" v-model="contact.first_name" class="form-control" id="first_name">
            </div>
        </div>

        <div class="form-group row">
            <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.last_name') }}</label>
            <div class="col-sm-9">
                <input type="text" name="last_name" :placeholder="trans('texts.last_name')" v-model="contact.last_name" class="form-control" id="last_name">
            </div>
        </div>

        <div class="form-group row">
            <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.email') }}</label>
            <div class="col-sm-9">
                <input type="email" name="email" :placeholder="trans('texts.email')" v-model="contact.email" class="form-control" id="email">
            </div>
        </div>

        <div class="form-group row">
            <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.phone') }}</label>
            <div class="col-sm-9">
                <input type="text" name="phone" :placeholder="trans('texts.phone')" v-model="contact.phone" class="form-control" id="phone">
            </div>
        </div>

        <div class="form-group row" v-if="contact.custom_value1">
            <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.custom_value1') }}</label>
            <div class="col-sm-9">
                <input type="text" name="custom_value1" :placeholder="trans('texts.custom_value1')" v-model="contact.custom_value1" class="form-control" id="custom_value1">
            </div>
        </div>

        <div class="form-group row" v-if="contact.custom_value1">
            <label for="name" class="col-sm-3 col-form-label text-right">{{ trans('texts.custom_value2') }}</label>
            <div class="col-sm-9">
                <input type="text" name="custom_value2" :placeholder="trans('texts.custom_value2')" v-model="contact.custom_value2" class="form-control" id="custom_value2">
            </div>
        </div>

        <div class="float-right">
            <button type="button" class="btn btn-danger" v-on:click="$emit('remove',contact.id)"> {{ trans('texts.remove_contact') }}</button>
        </div>
    </div>
</template>

<script>
    export default {
        props: ['contact']
    }
</script>
