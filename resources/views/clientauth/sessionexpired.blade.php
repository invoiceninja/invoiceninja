@extends('public.header')

@section('head')
    @parent
    <style type="text/css">
        body {
            padding-top: 40px;
            padding-bottom: 40px;
        }
        .modal-header {
            border-top-left-radius: 3px;
            border-top-right-radius: 3px;
            background:#222;
            color:#fff
        }
        .modal-header h4 {
            margin:0;
        }
        .modal-header img {
            float: left;
            margin-right: 20px;
        }
        .form-signin {
            max-width: 400px;
            margin: 0 auto;
            background: #fff;
        }
        p.link a {
            font-size: 11px;
        }
        .form-signin .inner {
            padding: 20px;
            border-bottom-right-radius: 3px;
            border-bottom-left-radius: 3px;
            border-left: 1px solid #ddd;
            border-right: 1px solid #ddd;
            border-bottom: 1px solid #ddd;
        }
        .form-signin .checkbox {
            font-weight: normal;
        }
        .form-signin .form-control {
            margin-bottom: 17px !important;
        }
        .form-signin .form-control:focus {
            z-index: 2;
        }

        .modal-header a:link,
        .modal-header a:visited,
        .modal-header a:hover,
        .modal-header a:active {
            text-decoration: none;
            color: white;
        }

    </style>

@endsection

@section('body')
    <div class="container">
        <div class="form-signin">
            <div class="modal-header">
                <h4>{{ trans('texts.session_expired') }}</h4>
            </div>
            <div class="inner">
                <div class="alert alert-info">{{ trans('texts.client_session_expired_message') }}</div>
            </div>
        </div>
    </div>
@endsection
