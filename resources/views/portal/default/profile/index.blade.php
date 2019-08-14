@extends('portal.default.layouts.master')
@push('css')
    <link href="/vendors/css/select2.min.css" rel="stylesheet">
    <link href="/vendors/css/select2-bootstrap4.css" rel="stylesheet">
    <style>    
      select {border: 1px solid  !important;}
      .select2-container--bootstrap4 .select2-selection--single {border: 1px solid #e4e7ea !important;}
      .control-label {text-align:right;}
    </style>
@endpush
@section('body')
  <main class="main">
      <div class="container-fluid">
        <div class="row" style="padding-top: 30px;">
          <div class="col-sm-12">
            <div class="card">
              <div class="card-header">
                  <strong> {{ ctrans('texts.user_details') }}</strong>
              </div>

                    {!! Former::framework('TwitterBootstrap4'); !!}

                    {!! Former::horizontal_open_for_files()
                          ->id('update_contact')
                          ->route('client.profile.update', auth()->user()->hashed_id)
                          ->method('PUT');  !!}
                    
                    @csrf

              <div class="card-body">

                <div class="row">
                  <div class="col-sm-4">
                    <div class="card align-items-center">
                      <div class="card-body">
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
                    </div>
                  </div>
                  <div class="col-sm-8">
                    <div class="card">
                      <div class="card-body">
                        {!! Former::text('first_name')->label( ctrans('texts.first_name'))->value(auth()->user()->first_name) !!}

                        {!! Former::text('last_name')->placeholder('')->label( ctrans('texts.last_name'))->value(auth()->user()->last_name) !!}

                        {!! Former::text('email')->placeholder('')->label(ctrans('texts.email'))->value(auth()->user()->email) !!}

                        {!! Former::text('phone')->placeholder('')->label(ctrans('texts.phone'))->value(auth()->user()->phone) !!}

                        {!! Former::password('password')->placeholder('')->label(ctrans('texts.password')) !!}

                        {!! Former::password('password_confirmation')->placeholder('')->label(ctrans('texts.confirm_password')) !!}

                        <div>
                          <button class="btn btn-primary pull-right">{{ ctrans('texts.save') }}</button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

                        {!! Former::close() !!}

        @include('portal.default.profile.client_information')
      </div>
  </main>
</body>
@endsection
@section('footer')
@endsection