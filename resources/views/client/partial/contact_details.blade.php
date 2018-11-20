<div class="card-body">
    <div class="form-group row">
        <label for="name" class="col-sm-3 col-form-label text-right">@lang('texts.first_name')</label>
        <div class="col-sm-9">
            {{ html()->input('first_name')->placeholder(__('texts.first_name'))->value($contact->first_name)->class('form-control')->id('first_name') }}
        </div>
    </div>

    <div class="form-group row">
        <label for="name" class="col-sm-3 col-form-label text-right">@lang('texts.last_name')</label>
        <div class="col-sm-9">
            {{ html()->input('last_name')->placeholder(__('texts.last_name'))->value($contact->last_name)->class('form-control')->id('last_name') }}
        </div>
    </div>

    <div class="form-group row">
        <label for="name" class="col-sm-3 col-form-label text-right">@lang('texts.email')</label>
        <div class="col-sm-9">
            {{ html()->input('email')->placeholder(__('texts.email'))->value($contact->first_name)->class('form-control')->id('email') }}
        </div>
    </div>

    <div class="form-group row">
        <label for="name" class="col-sm-3 col-form-label text-right">@lang('texts.phone')</label>
        <div class="col-sm-9">
            {{ html()->input('phone')->placeholder(__('texts.phone'))->value($contact->phone)->class('form-control')->id('phone') }}
        </div>
    </div>

    <div class="form-group row">
        <label for="name" class="col-sm-3 col-form-label text-right">@lang('texts.custom_value1')</label>
        <div class="col-sm-9">
            {{ html()->input('custom_value1')->placeholder(__('texts.custom_value1'))->value($contact->custom_value1)->class('form-control')->id('custom_value1') }}
        </div>
    </div>

    <div class="form-group row">
        <label for="name" class="col-sm-3 col-form-label text-right">@lang('texts.custom_value2')</label>
        <div class="col-sm-9">
            {{ html()->input('custom_value2')->placeholder(__('texts.custom_value2'))->value($contact->custom_value2)->class('form-control')->id('custom_value2') }}
        </div>
    </div>
</div>


