<div class="card">
    <div class="card-header bg-primary2">{{ trans('texts.address') }}</div>
        <div>
        <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="tab" href="#billing" role="tab" aria-controls="billing">{{ trans('texts.billing_address') }}</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#shipping" role="tab" aria-controls="shipping">{{ trans('texts.shipping_address') }}</a>
            </li>
        </ul>
        <div class="tab-content">
            <div class="tab-pane active" id="billing" role="tabpanel">
                <button type="button" class="btn btn-sm btn-light" @click="copy('copy_shipping')"> {{ trans('texts.copy_shipping') }}</button>
                <div class="card-body">
                    <div class="form-group row">
                        <label for="name" class="col-lg-3 col-form-label text-right">trans('texts.address1')</label>
                        <div class="col-lg-9">
                            <input name="address1" placeholder="trans('texts.address1')" class="form-control" v-model="form.address1">
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="name" class="col-lg-3 col-form-label text-right">trans('texts.address2')</label>
                        <div class="col-lg-9">
                            <input name="address2" placeholder="trans('texts.address2')" class="form-control" v-model="form.address2" id="address2">
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="name" class="col-lg-3 col-form-label text-right">trans('texts.city')</label>
                        <div class="col-lg-9">
                            <input name="city" placeholder="trans('texts.city')" class="form-control" v-model="form.city" id="city">
                        </div>
                    </div>


                    <div class="form-group row">
                        <label for="name" class="col-lg-3 col-form-label text-right">trans('texts.state')</label>
                        <div class="col-lg-9">
                            <input name="state" placeholder="trans('texts.state')" class="form-control" v-model="form.state" id="state">
                        </div>
                    </div>


                    <div class="form-group row">
                        <label for="name" class="col-lg-3 col-form-label text-right">trans('texts.postal_code')</label>
                        <div class="col-lg-9">
                            <input name="postal_code" placeholder="trans('texts.postal_code')" class="form-control" v-model="form.postal_code" id="postal_code">
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="name" class="col-lg-3 col-form-label text-right">trans('texts.country')</label>
                        <div class="col-lg-9">
                            <input name="country_id" placeholder="trans('texts.country')" class="form-control" v-model="form.country_id" id="country">
                        </div>
                    </div>
                </div>
            </div>
            <div class="tab-pane" id="shipping" role="tabpanel">
                <button type="button" class="btn btn-sm btn-light" @click="copy('copy_billing')"> {{ trans('texts.copy_billing') }}</button>
                <div class="card-body">
                    <div class="form-group row">
                        <label for="name" class="col-lg-3 col-form-label text-right">trans('texts.address1')</label>
                        <div class="col-lg-9">
                            <input name="shipping_address1" placeholder="trans('texts.address1')" class="form-control" v-model="form.shipping_address1">
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="name" class="col-lg-3 col-form-label text-right">trans('texts.address2')</label>
                        <div class="col-lg-9">
                            <input name="shipping_address2" placeholder="trans('texts.address2')" class="form-control" v-model="form.shipping_address2" id="address2">
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="name" class="col-lg-3 col-form-label text-right">trans('texts.city')</label>
                        <div class="col-lg-9">
                            <input name="shipping_city" placeholder="trans('texts.city')" class="form-control" v-model="form.shipping_city" id="city">
                        </div>
                    </div>


                    <div class="form-group row">
                        <label for="name" class="col-lg-3 col-form-label text-right">trans('texts.state')</label>
                        <div class="col-lg-9">
                            <input name="shipping_state" placeholder="trans('texts.state')" class="form-control" v-model="form.shipping_state" id="state">
                        </div>
                    </div>


                    <div class="form-group row">
                        <label for="name" class="col-lg-3 col-form-label text-right">trans('texts.postal_code')</label>
                        <div class="col-lg-9">
                            <input name="shipping_postal_code" placeholder="trans('texts.postal_code')" class="form-control" v-model="form.shipping_postal_code" id="postal_code">
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="name" class="col-lg-3 col-form-label text-right">trans('texts.country')</label>
                        <div class="col-lg-9">
                            <input name="shipping_country_id" placeholder="trans('texts.country')" class="form-control" v-model="form.shipping_country_id" id="country">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>  
</div>

