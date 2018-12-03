<div class="card">
    <div class="card-header bg-primary2">@lang('texts.edit_client')</div>
    <div class="card-body">
        <div class="form-group row">
            <label for="name" class="col-sm-3 col-form-label text-right">@lang('texts.client_name')</label>
            <div class="col-sm-9">
                <input name="name" placeholder="@lang('texts.name')" class="form-control" v-model="form.name" value="{{ $client->name }}">
                
            </div>
        </div>

        <div class="form-group row">
            <label for="name" class="col-sm-3 col-form-label text-right">@lang('texts.id_number')</label>
            <div class="col-sm-9">
                <input name="id_number" placeholder="@lang('texts.id_number')" class="form-control" v-model="form.id_number">
            </div>
        </div>

        <div class="form-group row">
            <label for="name" class="col-sm-3 col-form-label text-right">@lang('texts.vat_number')</label>
            <div class="col-sm-9">
                <input name="vat_number" placeholder="@lang('texts.vat_number')" class="form-control" v-model="form.vat_number">
            </div>
        </div>

        <div class="form-group row">
            <label for="name" class="col-sm-3 col-form-label text-right">@lang('texts.website')</label>
            <div class="col-sm-9">
                <input name="website" placeholder="@lang('texts.website')" class="form-control" v-model="form.website">
            </div>
        </div>

        <div class="form-group row">
            <label for="name" class="col-sm-3 col-form-label text-right">@lang('texts.custom_value1')</label>
            <div class="col-sm-9">
                <input name="custom_value1" placeholder="@lang('texts.custom_value1')" class="form-control" v-model="form.custom_value1">
            </div>
        </div>

        <div class="form-group row">
            <label for="name" class="col-sm-3 col-form-label text-right">@lang('texts.custom_value2')</label>
            <div class="col-sm-9">
                <input name="custom_value2" placeholder="@lang('texts.custom_value2')" class="form-control" v-model="form.custom_value2">
            </div>
        </div>
    </div>
</div>