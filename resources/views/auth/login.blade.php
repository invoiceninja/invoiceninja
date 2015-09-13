@extends('master')

@section('head')	

<link href="{{ asset('css/bootstrap.min.css') }}" rel="stylesheet" type="text/css"/> 
<link href="{{ asset('css/style.css') }}" rel="stylesheet" type="text/css"/>    

<style type="text/css">
    body {
        padding-top: 40px;
        padding-bottom: 40px;
    }
    .modal-header {
        border-top-left-radius: 3px;
        border-top-right-radius: 3px;
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


    {!! Former::open('login')->rules(['email' => 'required|email', 'password' => 'required'])->addClass('form-signin') !!}
    {{ Former::populateField('remember', 'true') }}

    <div class="modal-header">
        <a href="{{ NINJA_WEB_URL }}" target="_blank">
            <img src="{{ asset('images/icon-login.png') }}" />        
            <h4>Invoice Ninja | {{ trans('texts.account_login') }}</h4>
        </a>
    </div>    
        <div class="inner">
            <p>
                {!! Former::text('email')->placeholder(trans('texts.email_address'))->raw() !!}
                {!! Former::password('password')->placeholder(trans('texts.password'))->raw() !!}
                {!! Former::hidden('remember')->raw() !!}
            </p>

            <p>{!! Button::success(trans(Input::get('new_company') ? 'texts.login' : 'texts.lets_go'))->large()->submit()->block() !!}</p>

            @if (Input::get('new_company') && Utils::allowNewAccounts())
                <center><p>- {{ trans('texts.or') }} -</p></center>
                <p>{!! Button::primary(trans('texts.new_company'))->asLinkTo(URL::to('/invoice_now?new_company=true&sign_up=true'))->large()->submit()->block() !!}</p>
            @endif


            <p class="link">
                {!! link_to('/forgot', trans('texts.forgot_password')) !!}
                {!! link_to(NINJA_WEB_URL.'/knowledgebase/', trans('texts.knowledge_base'), ['target' => '_blank', 'class' => 'pull-right']) !!}
            </p>


            @if (count($errors->all()))
                <div class="alert alert-danger">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </div>
            @endif            

            @if (Session::has('warning'))
            <div class="alert alert-warning">{{ Session::get('warning') }}</div>
            @endif

            @if (Session::has('message'))
            <div class="alert alert-info">{{ Session::get('message') }}</div>
            @endif

            @if (Session::has('error'))
            <div class="alert alert-danger"><li>{{ Session::get('error') }}</li></div>
            @endif

        </div>

        {!! Former::close() !!}

        <p/>
        <center>
            <!--
            <div id="fb-root"></div>
            <script>(function(d, s, id) {
                var js, fjs = d.getElementsByTagName(s)[0];
                if (d.getElementById(id)) return;
                js = d.createElement(s); js.id = id;
                js.src = "//connect.facebook.net/en_US/all.js#xfbml=1&appId=635126583203143";
                fjs.parentNode.insertBefore(js, fjs);
            }(document, 'script', 'facebook-jssdk'));</script>

            <div class="fb-follow" data-href="https://www.facebook.com/invoiceninja" data-colorscheme="light" data-layout="button" data-show-faces="false"></div>&nbsp;&nbsp;

            <a href="https://twitter.com/invoiceninja" class="twitter-follow-button" data-show-count="false" data-related="hillelcoren" data-size="medium">Follow @invoiceninja</a>
            <script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0],p=/^http:/.test(d.location)?'http':'https';if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src=p+'://platform.twitter.com/widgets.js';fjs.parentNode.insertBefore(js,fjs);}}(document, 'script', 'twitter-wjs');</script>

            <iframe src="https://ghbtns.com/github-btn.html?user=hillelcoren&repo=invoice-ninja&type=star&count=false" frameborder="0" scrolling="0" width="50px" height="20px"></iframe>
            -->

            <p>&nbsp;</p>
            <p>&nbsp;</p>

            <!--
            <iframe allowTransparency="true" frameborder="0" scrolling="no" src="https://bitnami.com/product/invoice-ninja/widget" style="border:none;width:230px; height:100px;"></iframe>
            -->
            
        </center>

    </div>


    <script type="text/javascript">
        $(function() {
            if ($('#email').val()) {
                $('#password').focus();
            } else {
                $('#email').focus();
            }
        })
    </script>

@endsection