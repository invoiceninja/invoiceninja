<div class="col-lg-5">
    <div class="card">
        <div class="card-header">@lang('texts.edit_client')</div>

        <div class="card-body">
            <div class="form-group row">
                <label for="name" class="col-sm-3 col-form-label text-right">@lang('texts.client_name')</label>
                <div class="col-sm-9">
                    {{ html()->input('name')->placeholder(__('texts.client_name'))->value($client->present()->name)->class('form-control')->id('name') }}
                </div>
            </div>

            <div class="form-group row">
                <label for="name" class="col-sm-3 col-form-label text-right">@lang('texts.id_number')</label>
                <div class="col-sm-9">
                    {{ html()->input('id_number')->placeholder(__('texts.id_number'))->value($client->id_number)->class('form-control')->id('id_number') }}
                </div>
            </div>

            <div class="form-group row">
                <label for="name" class="col-sm-3 col-form-label text-right">@lang('texts.vat_number')</label>
                <div class="col-sm-9">
                    {{ html()->input('vat_number')->placeholder(__('texts.vat_number'))->value($client->vat_number)->class('form-control')->id('vat_number') }}
                </div>
            </div>

            <div class="form-group row">
                <label for="name" class="col-sm-3 col-form-label text-right">@lang('texts.website')</label>
                <div class="col-sm-9">
                    {{ html()->input('website')->placeholder(__('texts.website'))->value($client->website)->class('form-control')->id('website') }}
                </div>
            </div>

            <div class="form-group row">
                <label for="name" class="col-sm-3 col-form-label text-right">@lang('texts.custom_value1')</label>
                <div class="col-sm-9">
                    {{ html()->input('custom_value1')->placeholder(__('texts.custom_value1'))->value($client->custom_value1)->class('form-control')->id('custom_value1') }}
                </div>
            </div>

            <div class="form-group row">
                <label for="name" class="col-sm-3 col-form-label text-right">@lang('texts.custom_value2')</label>
                <div class="col-sm-9">
                    {{ html()->input('custom_value2')->placeholder(__('texts.custom_value2'))->value($client->custom_value2)->class('form-control')->id('custom_value2') }}
                </div>
            </div>
        </div>
    </div>
</div>