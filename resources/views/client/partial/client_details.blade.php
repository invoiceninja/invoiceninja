<div class="card">
    <div class="card-header bg-primary2">trans('texts.edit_client')</div>
    <div class="card-body">
        <div class="form-group row">
            <label for="name" class="col-sm-3 col-form-label text-right">trans('texts.client_name')</label>
            <div class="col-sm-9">
                <input name="name" placeholder="trans('texts.name')" class="form-control" v-model="form.name">
                <div v-if="form.errors.has('name')" class="text-danger" v-text="form.errors.get('name')"></div>
            </div>
        </div>

        <div class="form-group row">
            <label for="name" class="col-sm-3 col-form-label text-right">trans('texts.id_number')</label>
            <div class="col-sm-9">
                <input name="id_number" placeholder="trans('texts.id_number')" class="form-control" v-model="form.id_number">
                <div v-if="form.errors.has('id_number')" class="text-danger" v-text="form.errors.get('id_number')"></div>
            </div>
        </div>

        <div class="form-group row">
            <label for="name" class="col-sm-3 col-form-label text-right">trans('texts.vat_number')</label>
            <div class="col-sm-9">
                <input name="vat_number" placeholder="trans('texts.vat_number')" class="form-control" v-model="form.vat_number">
                <div v-if="form.errors.has('vat_number')" class="text-danger" v-text="form.errors.get('vat_number')"></div>
            </div>
        </div>

        <div class="form-group row">
            <label for="name" class="col-sm-3 col-form-label text-right">trans('texts.website')</label>
            <div class="col-sm-9">
                <input name="website" placeholder="trans('texts.website')" class="form-control" v-model="form.website">
                <div v-if="form.errors.has('website')" class="text-danger" v-text="form.errors.get('website')"></div>
            </div>
        </div>

        <div class="form-group row">
            <label for="name" class="col-sm-3 col-form-label text-right">trans('texts.custom_value1')</label>
            <div class="col-sm-9">
                <input name="custom_value1" placeholder="trans('texts.custom_value1')" class="form-control" v-model="form.custom_value1">
                <div v-if="form.errors.has('custom_value1')" class="text-danger" v-text="form.errors.get('custom_value1')"></div>
            </div>
        </div>

        <div class="form-group row">
            <label for="name" class="col-sm-3 col-form-label text-right">trans('texts.custom_value2')</label>
            <div class="col-sm-9">
                <input name="custom_value2" placeholder="trans('texts.custom_value2')" class="form-control" v-model="form.custom_value2">
                <div v-if="form.errors.has('custom_value2')" class="text-danger" v-text="form.errors.get('custom_value2')"></div>
            </div>
        </div>
    </div>
</div>