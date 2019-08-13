@extends('portal.default.layouts.master')
@push('css')
  <link href="/vendors/css/select2.min.css" rel="stylesheet">
  <link href="/vendors/css/select2-bootstrap4.css" rel="stylesheet">
  <style>    
    select {border: 1px solid  !important;}
    .select2-container--bootstrap4 .select2-selection--single 
    {
      border: 1px solid #e4e7ea !important;
    }

  </style>
@endpush
@section('body')
  <main class="main">

      <div class="container-fluid">

		    <div class="row" style="padding-top: 30px;">
		
          <div class="col-sm-3" style="padding-bottom: 10px;">

              {!! Former::framework('TwitterBootstrap4'); !!}

              {!! Former::vertical_open_for_files()
                    ->id('update_contact')
                    ->route('client.profile.update', auth()->user()->hashed_id)
                    ->method('PUT');	!!}
              
              @csrf

              <div class="card">

                  <div class="card-header">
                  
                      <strong>{{ ctrans('texts.avatar') }}</strong>

                  </div>

                  <div class="card-body align-items-center">

                      @if(auth()->user()->avatar)
                      <img src="{{ auth()->user()->avatar }}" class="img-fluid">
                      @else
                      <i class="fa fa-user fa-5x"></i>
                      @endif

                      {!! Former::file('avatar')
                          ->max(2, 'MB')
                          ->accept('image')
                          ->label('')
                          ->inlineHelp(trans('texts.logo_help')) !!}

                  </div>

                  <div class="card-footer">
                  </div>

              </div>

          </div>

          <div class="col-sm-6" style="padding-bottom: 10px;">
              
              <div class="card">

                  <div class="card-header">
                  
                      <strong> {{ ctrans('texts.user_details') }}</strong>

                  </div>

                  <div class="card-body">
                  
                  {!! Former::text('first_name')->placeholder( ctrans('texts.first_name'))->label('')->value(auth()->user()->first_name)!!}

                  {!! Former::text('last_name')->placeholder( ctrans('texts.last_name'))->label('')->value(auth()->user()->last_name) !!}

                  {!! Former::text('email')->placeholder( ctrans('texts.email'))->label('')->value(auth()->user()->email) !!}

                  {!! Former::text('phone')->placeholder( ctrans('texts.phone'))->label('')->value(auth()->user()->phone) !!}

                  {!! Former::password('password')->placeholder( ctrans('texts.password'))->label('') !!}

                  {!! Former::password('password_confirmation')->placeholder( ctrans('texts.confirm_password'))->label('') !!}

                  </div>

                  <div class="card-footer">

                      <button class="btn btn-primary pull-right">{{ ctrans('texts.save') }}</button>

                  </div>

              </div>

              {!! Former::close() !!}

          </div>

		    </div>

    </div>

  </main>

</body>
@endsection