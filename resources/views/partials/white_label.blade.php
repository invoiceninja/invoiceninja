{{ trans('texts.powered_by') }}
{{-- Per our license, please do not remove or modify this section. --}}
{!! link_to('https://www.invoiceninja.com/?utm_source=powered_by', 'InvoiceNinja.com', ['target' => '_blank', 'title' => trans('texts.created_by', ['name' => 'Hillel Coren'])]) !!} -
{!! link_to(RELEASES_URL, 'v' . NINJA_VERSION, ['target' => '_blank', 'title' => trans('texts.trello_roadmap')]) !!} |
@if (Auth::user()->account->hasFeature(FEATURE_WHITE_LABEL))
  {{ trans('texts.white_labeled') }}
@else
  <a href="#" onclick="showWhiteLabelModal()">{{ trans('texts.white_label_link') }}</a>

  <div class="modal fade" id="whiteLabelModal" tabindex="-1" role="dialog" aria-labelledby="whiteLabelModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
          <h4 class="modal-title" id="myModalLabel">{{ trans('texts.white_label_header') }}</h4>
        </div>

        <div class="panel-body">
          <p>{{ trans('texts.white_label_text', ['price' => WHITE_LABEL_PRICE])}}</p>
          <div class="row">
              <div class="col-md-6">
                  <h4>{{ trans('texts.before') }}</h4>
                  <img src="{{ BLANK_IMAGE }}" data-src="{{ asset('images/pro_plan/white_label_before.png') }}" width="100%" alt="before">
              </div>
              <div class="col-md-6">
                  <h4>{{ trans('texts.after') }}</h4>
                  <img src="{{ BLANK_IMAGE }}" data-src="{{ asset('images/pro_plan/white_label_after.png') }}" width="100%" alt="after">
              </div>
          </div><br/>
          <p>{!! trans('texts.reseller_text', ['email' => HTML::mailto('contact@invoiceninja.com')]) !!}</p>
        </div>

        <div class="modal-footer" id="signUpFooter" style="margin-top: 0px">
          <button type="button" class="btn btn-default" data-dismiss="modal">{{ trans('texts.close') }} </button>
          <button type="button" class="btn btn-primary" onclick="buyProduct('{{ WHITE_LABEL_AFFILIATE_KEY }}', '{{ PRODUCT_WHITE_LABEL }}')">{{ trans('texts.buy_license') }} </button>
          <button type="button" class="btn btn-primary" onclick="showApplyLicense()">{{ trans('texts.apply_license') }} </button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="whiteLabelLicenseModal" tabindex="-1" role="dialog" aria-labelledby="whiteLabelLicenseModal" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
            <h4 class="modal-title" id="myModalLabel">{{ trans('texts.white_label_header') }}</h4>
          </div>

          <div class="panel-body">
              {!! Former::open()->rules(['white_label_license_key' => 'required|min:24|max:24']) !!}
              {!! Former::input('white_label_license_key') !!}
              {!! Former::close() !!}
          </div>

          <div class="modal-footer" id="signUpFooter" style="margin-top: 0px">
            <button type="button" class="btn btn-default" data-dismiss="modal">{{ trans('texts.close') }} </button>
            <button type="button" class="btn btn-success" onclick="applyLicense()">{{ trans('texts.submit') }} </button>
          </div>
        </div>
      </div>
  </div>

@endif


<script type="text/javascript">

    function showWhiteLabelModal() {
        loadImages('#whiteLabelModal');
        $('#whiteLabelModal').modal('show');
    }

    function buyProduct(affiliateKey, productId) {
        window.open('{{ Utils::isNinjaDev() ? '' : NINJA_APP_URL }}/license?affiliate_key=' + affiliateKey + '&product_id=' + productId + '&return_url=' + window.location);
    }

    function showApplyLicense() {
        $('#whiteLabelModal').modal('hide');
        $('#whiteLabelLicenseModal').modal('show');
    }

    function applyLicense() {
        var license = $('#white_label_license_key').val();
        window.location = "{{ url('') }}/dashboard?license_key=" + license + "&product_id={{ PRODUCT_WHITE_LABEL }}";
    }

</script>
