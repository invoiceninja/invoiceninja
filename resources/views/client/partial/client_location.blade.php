
    <div class="form-group row">
        <label for="name" class="col-lg-3 col-form-label text-right">@lang('texts.address1')</label>
        <div class="col-lg-9">
            {{ html()->input('address1')->placeholder(__('texts.address1'))->value($location->address1)->class('form-control')->id('address1') }}
        </div>
    </div>

    <div class="form-group row">
        <label for="name" class="col-lg-3 col-form-label text-right">@lang('texts.address2')</label>
        <div class="col-lg-9">
            {{ html()->input('address2')->placeholder(__('texts.address2'))->value($location->address2)->class('form-control')->id('address2') }}
        </div>
    </div>

    <div class="form-group row">
        <label for="name" class="col-lg-3 col-form-label text-right">@lang('texts.city')</label>
        <div class="col-lg-9">
            {{ html()->input('city')->placeholder(__('texts.city'))->value($location->city)->class('form-control')->id('city') }}
        </div>
    </div>


    <div class="form-group row">
        <label for="name" class="col-lg-3 col-form-label text-right">@lang('texts.state')</label>
        <div class="col-lg-9">
            {{ html()->input('state')->placeholder(__('texts.state'))->value($location->state)->class('form-control')->id('state') }}
        </div>
    </div>


    <div class="form-group row">
        <label for="name" class="col-lg-3 col-form-label text-right">@lang('texts.postal_code')</label>
        <div class="col-lg-9">
            {{ html()->input('postal_code')->placeholder(__('texts.postal_code'))->value($location->postal_code)->class('form-control')->id('postal_code') }}
        </div>
    </div>

    <div class="form-group row">
        <label for="name" class="col-lg-3 col-form-label text-right">@lang('texts.country')</label>
        <div class="col-lg-9">
            {{ html()->input('country')->placeholder(__('texts.country'))->value($location->country)->class('form-control')->id('country') }}
        </div>
    </div>

