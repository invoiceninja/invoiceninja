@extends('master')

@section('head')
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <style type="text/css">
        html,body {
            font-family: 'Open Sans', serif;
            font-size: 14px;
            font-weight: 300;
        }
        .hero.is-success {
            background: #F2F6FA;
        }
        .hero .nav, .hero.is-success .nav {
            -webkit-box-shadow: none;
            box-shadow: none;
        }
        .box {
            margin-top: 5rem;
        }
        .avatar {
            margin-top: -70px;
            padding-bottom: 20px;
        }
        .avatar img {
            padding: 5px;
            background: #fff;
            border-radius: 50%;
            -webkit-box-shadow: 0 2px 3px rgba(10,10,10,.1), 0 0 0 1px rgba(10,10,10,.1);
            box-shadow: 0 2px 3px rgba(10,10,10,.1), 0 0 0 1px rgba(10,10,10,.1);
        }
        input {
            font-weight: 300;
        }
        p {
            font-weight: 700;
        }
        p.subtitle {
            padding-top: 1rem;
        }
    </style>
@endsection

@section('body')

    <body>
    <section class="hero is-success is-fullheight">
        <div class="hero-body">
            <div class="container has-text-centered">
                <div class="column is-4 is-offset-4">
                    <h3 class="title has-text-grey">@lang('texts.account_login')</h3>
                    <div class="box">
                        <figure class="avatar">
                            <img src="https://placehold.it/128x128">
                        </figure>
                        {{ html()->form('POST', '/login')->open() }}

                            @if (count($errors->all()))
                                <article class="message is-danger">
                                    <div class="message-header">
                                        <p>@lang('texts.error')</p>
                                    </div>
                                    <div class="message-body">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </div>
                                </article>
                            @endif

                            <div class="field">
                                <div class="control">
                                    {{ html()->email('email')->placeholder(__('texts.email_address'))->class('input is-large') }}
                                </div>
                            </div>

                            <div class="field">
                                <div class="control">
                                    {{ html()->password('password')->placeholder(__('texts.password'))->class('input is-large') }}
                                </div>
                            </div>
                            <div class="field">
                                <label class="checkbox">
                                    <input type="checkbox">
                                    Remember me
                                </label>
                            </div>
                            {{ Spatie\Html\Elements\Element::withTag('button')->text(__('texts.login'))->class('button is-block is-info is-large is-fullwidth') }}

                        {{ html()->form()->close() }}
                    </div>
                    <p class="has-text-grey">
                        <a href="../">Sign Up</a> &nbsp;·&nbsp;
                        <a href="../">Forgot Password</a> &nbsp;·&nbsp;
                        <a href="../">Need Help?</a>
                    </p>
                </div>
            </div>
        </div>
    </section>
    </body>

@endsection

