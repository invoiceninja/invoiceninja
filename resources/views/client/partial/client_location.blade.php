<div class="card-body">
    <div class="form-group row">
        <label for="name" class="col-lg-3 col-form-label text-right">@lang('texts.address1')</label>
        <div class="col-lg-9">
            <input name="address1" placeholder="@lang('texts.address1')" class="form-control" v-model="client.primary_billing_location.address1" id="address1">
        </div>
    </div>

    <div class="form-group row">
        <label for="name" class="col-lg-3 col-form-label text-right">@lang('texts.address2')</label>
        <div class="col-lg-9">
            <input name="address2" placeholder="@lang('texts.address2')" class="form-control" v-model="client.primary_billing_location.address2" id="address2">
        </div>
    </div>

    <div class="form-group row">
        <label for="name" class="col-lg-3 col-form-label text-right">@lang('texts.city')</label>
        <div class="col-lg-9">
            <input name="city" placeholder="@lang('texts.city')" class="form-control" v-model="client.primary_billing_location.city" id="city">
        </div>
    </div>


    <div class="form-group row">
        <label for="name" class="col-lg-3 col-form-label text-right">@lang('texts.state')</label>
        <div class="col-lg-9">
            <input name="state" placeholder="@lang('texts.state')" class="form-control" v-model="client.primary_billing_location.state" id="state">
        </div>
    </div>


    <div class="form-group row">
        <label for="name" class="col-lg-3 col-form-label text-right">@lang('texts.postal_code')</label>
        <div class="col-lg-9">
            <input name="postal_code" placeholder="@lang('texts.postal_code')" class="form-control" v-model="client.primary_billing_location.postal_code" id="postal_code">
        </div>
    </div>

    <div class="form-group row">
        <label for="name" class="col-lg-3 col-form-label text-right">@lang('texts.country')</label>
        <div class="col-lg-9">
            <input name="country" placeholder="@lang('texts.country')" class="form-control" v-model="client.primary_billing_location.country" id="country">
        </div>
    </div>
</div>