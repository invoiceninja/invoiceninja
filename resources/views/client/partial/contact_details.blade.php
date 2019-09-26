<div class="card-body">
    <div class="form-group row">
        <label for="name" class="col-sm-3 col-form-label text-right">trans('texts.first_name')</label>
        <div class="col-sm-9">
            <input name="id" type="hidden" v-model="contact.client_id" value="{{ $client->present()->id ?: -1}}">
            <input ref="first_name" name="first_name" placeholder="trans('texts.first_name')" class="form-control" v-model="contact.first_name">
            <div v-if="form.errors.has('contacts.'+key+'.first_name')" class="text-danger" v-text="form.errors.get('contacts.'+key+'.first_name')"></div>
        </div>
    </div>

    <div class="form-group row">
        <label for="name" class="col-sm-3 col-form-label text-right">trans('texts.last_name')</label>
        <div class="col-sm-9">
            <input name="last_name" placeholder="trans('texts.last_name')" class="form-control" v-model="contact.last_name">
            <div v-if="form.errors.has('contacts.'+key+'.last_name')" class="text-danger" v-text="form.errors.get('contacts.'+key+'.last_name')"></div>
        </div>
    </div>

    <div class="form-group row">
        <label for="name" class="col-sm-3 col-form-label text-right">trans('texts.email')</label>
        <div class="col-sm-9">
            <input name="email" placeholder="trans('texts.email')" class="form-control" v-model="contact.email">
            <div v-if="form.errors.has('contacts.'+key+'.email')" class="text-danger" v-text="form.errors.get('contacts.'+key+'.email')"></div>
        </div>
    </div>

    <div class="form-group row">
        <label for="name" class="col-sm-3 col-form-label text-right">trans('texts.phone')</label>
        <div class="col-sm-9">
            <input name="phone" placeholder="trans('texts.phone')" class="form-control" v-model="contact.phone">
            <div v-if="form.errors.has('contacts.'+key+'.phone')" class="text-danger" v-text="form.errors.get('contacts.'+key+'.phone')"></div>
        </div>
    </div>

    <div class="form-group row">
        <label for="name" class="col-sm-3 col-form-label text-right">trans('texts.custom_value1')</label>
        <div class="col-sm-9">
            <input name="custom_value1" placeholder="trans('texts.custom_value1')" class="form-control" v-model="contact.custom_value1">
            <div v-if="form.errors.has('contacts.'+key+'.custom_value1')" class="text-danger" v-text="form.errors.get('contacts.'+key+'.custom_value1')"></div>
        </div>
    </div>

    <div class="form-group row">
        <label for="name" class="col-sm-3 col-form-label text-right">trans('texts.custom_value2')</label>
        <div class="col-sm-9">
            <input name="custom_value2" placeholder="trans('texts.custom_value2')" class="form-control" v-model="contact.custom_value2">
            <div v-if="form.errors.has('contacts.'+key+'.custom_value2')" class="text-danger" v-text="form.errors.get('contacts.'+key+'.custom_value2')"></div>
        </div>
    </div>
    <div class="float-right">
        <button type="button" class="btn btn-danger" v-on:click="remove(contact)"> {{ trans('texts.remove_contact') }}</button>
    </div>
</div>


