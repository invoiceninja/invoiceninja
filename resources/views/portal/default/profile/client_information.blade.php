<div class="row">

  <div class="col-sm-12">

  {!! Former::framework('TwitterBootstrap4'); !!}

  {!! Former::horizontal_open_for_files()
        ->id('update_settings')
        ->route('client.profile.edit_client', auth()->user()->hashed_id)
        ->method('PUT');	!!}
  
  {!! Former::populate(auth()->user()->client) !!}

  @csrf

    <div class="card">

      <div class="card-header">
        <strong> {{ ctrans('texts.client_information') }} </strong>
      </div>

      <div class="card-body">
          
          <div class="row">
            <div class="col-sm-4">
              <div class="card align-items-center">
                <div class="card-body">
                  @if(auth()->user()->client->logo)
                  <img src="{{ auth()->user()->client->logo }}" class="img-fluid">
                  @else
                  <i class="fa fa-user fa-5x"></i>
                  @endif

                  {!! Former::file('logo')
                      ->max(2, 'MB')
                      ->accept('image')
                      ->label('')
                      ->inlineHelp(trans('texts.logo_help')) !!}
                </div>
              </div>
            </div>
            <div class="col-sm-6  pull-left">
              <div class="card card-body">
                {!! Former::text('name')->label( ctrans('texts.name')) !!}
                {!! Former::text('website')->label( ctrans('texts.website')) !!}
              </div>
            </div>
          </div>

                <div class="row">

                  <div class="col-sm-6">
                    <div class="card">
                      <div class="card-header">
                        <strong> {{ ctrans('texts.address') }} </strong>
                      </div>
                      <div class="card-body">

                        {!! Former::text('address1')->label( ctrans('texts.address1')) !!}
                        {!! Former::text('address2')->label( ctrans('texts.address2')) !!}
                        {!! Former::text('city')->label( ctrans('texts.city')) !!}
                        {!! Former::text('state')->label( ctrans('texts.state')) !!}
                        {!! Former::text('postal_code')->label( ctrans('texts.postal_code')) !!}

                        {!! Former::select('country_id')
                            ->addOption('','')
                            ->autocomplete('off')
                            ->label(ctrans('texts.country'))
                            ->fromQuery($countries, 'name', 'id') !!}

                      </div>
                    </div>
                  </div>


                  <div class="col-sm-6">
                    <div class="card">
                      <div class="card-header">
                        <strong> {{ ctrans('texts.shipping_address') }} </strong>
                      </div>
                      <div class="card-body">      

                        {!! Former::text('shipping_address1')->label( ctrans('texts.shipping_address1')) !!}
                        {!! Former::text('shipping_address2')->label( ctrans('texts.shipping_address2')) !!}
                        {!! Former::text('shipping_city')->label(ctrans('texts.shipping_city')) !!}
                        {!! Former::text('shipping_state')->label(ctrans('texts.shipping_state')) !!}
                        {!! Former::text('shipping_postal_code')->label(ctrans('texts.shipping_postal_code')) !!}

                        {!! Former::select('shipping_country_id')
                            ->addOption('','')
                            ->autocomplete('off')
                            ->label(ctrans('texts.shipping_country'))
                            ->fromQuery($countries, 'name', 'id') !!}
                      </div>
                    </div>
                  </div>

                </div>

          <button class="btn btn-primary pull-right">{{ ctrans('texts.save') }}</button>

      </div>
  
  {!! Former::close() !!}

    </div>
@push('scripts')
<script src="/vendors/js/select2.min.js"></script>
<script>
  $(document).ready(function() {

    $('#shipping_country_id').each(function () {
      $(this).select2({
        placeholder: "{{ ctrans('texts.country') }}",
        theme: 'bootstrap4',
        width: 'style',
        allowClear: Boolean($(this).data('allow-clear')),
      }).on('change', function() {
      
      });
    });


    $('#country_id').each(function () {
      $(this).select2({
        placeholder: "{{ ctrans('texts.country') }}",
        theme: 'bootstrap4',
        width: 'style',
        allowClear: Boolean($(this).data('allow-clear')),
      });
    });

  });
</script>  
@endpush