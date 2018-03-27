@extends('login')

@section('form')

    @include('partials.warn_session', ['redirectTo' => '/logout?reason=inactive'])

    <div class="container">

        {!! Former::open()
                ->rules(['totp' => 'required'])
                ->addClass('form-signin') !!}

        <h2 class="form-signin-heading">
            {{ trans('texts.enable_two_factor') }}
        </h2>
        <hr class="green">

        {!! Former::text('totp')
                ->placeholder(trans('texts.one_time_password'))
                ->autofocus()
                ->style('text-indent:4px')
                ->forceValue('')
                ->data_lpignore('true')
                ->raw() !!}

        {!! Former::select('trust')
                ->style('background-color:white !important')
                ->addOption(trans('texts.do_not_trust'), '')
                ->addOption(trans('texts.trust_for_30_days'), '30')
                ->addOption(trans('texts.trust_forever'), 'forever')
                ->raw() !!}

        {!! Button::success(trans('texts.submit'))
                    ->withAttributes(['id' => 'loginButton', 'class' => 'green'])
                    ->large()->submit()->block() !!}

        @if (count($errors->all()))
            <br/>
            <div class="alert alert-danger">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </div>
        @endif

        {!! Former::close() !!}

    </div>

@endsection
