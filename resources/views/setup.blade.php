<!DOCTYPE html>
<html lang="{{App::getLocale()}}">
  <head>
    <title>Invoice Ninja | Setup</title>
    <meta charset="utf-8">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <script src="{{ asset('built.js') }}?no_cache={{ NINJA_VERSION }}" type="text/javascript"></script>
    <link href="{{ asset('css/built.public.css') }}?no_cache={{ NINJA_VERSION }}" rel="stylesheet" type="text/css"/>
    <link href="{{ asset('css/built.css') }}?no_cache={{ NINJA_VERSION }}" rel="stylesheet" type="text/css"/>
    <link href="{{ asset('favicon.png?test') }}" rel="shortcut icon">

    <style type="text/css">
    body {
        background-color: #FEFEFE;
    }
    </style>

  </head>

  <body>
  <div class="container">

    &nbsp;
    <div class="row">
    <div class="col-md-8 col-md-offset-2">

    <div class="jumbotron">
        <h2>Invoice Ninja Setup</h2>
        @if (version_compare(phpversion(), '5.5.9', '<'))
            <div class="alert alert-warning">Warning: The application requires PHP >= 5.5.9</div>
        @endif
        @if (!function_exists('proc_open'))
            <div class="alert alert-warning">Warning: <a href="http://php.net/manual/en/function.proc-open.php" target="_blank">proc_open</a> must be enabled.</div>
        @endif
        @if (!@fopen(base_path()."/.env", 'a'))
            <div class="alert alert-warning">Warning: Permission denied to write .env config file
                <pre>sudo chown www-data:www-data /path/to/ninja/.env</pre>
            </div>
        @endif
        If you need help you can either post to our <a href="https://www.invoiceninja.com/forums/forum/support/" target="_blank">support forum</a> with the design you\'re using
        or email us at <a href="mailto:contact@invoiceninja.com" target="_blank">contact@invoiceninja.com</a>.
        <p>
<pre>-- Commands to create a MySQL database and user
CREATE SCHEMA `ninja` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
CREATE USER 'ninja'@'localhost' IDENTIFIED BY 'ninja';
GRANT ALL PRIVILEGES ON `ninja`.* TO 'ninja'@'localhost';
FLUSH PRIVILEGES;</pre>
        </p>
    </div>

    {!! Former::open()->rules([
        'app[url]' => 'required',
        'database[type][host]' => 'required',
        'database[type][database]' => 'required',
        'database[type][username]' => 'required',
        'database[type][password]' => 'required',
        'first_name' => 'required',
        'last_name' => 'required',
        'email' => 'required|email',
        'password' => 'required',
        'terms_checkbox' => 'required'
      ]) !!}

    @include('partials.system_settings')

    <div class="panel panel-default">
      <div class="panel-heading">
        <h3 class="panel-title">User Details</h3>
      </div>
      <div class="panel-body">
        {!! Former::text('first_name') !!}
        {!! Former::text('last_name') !!}
        {!! Former::text('email') !!}
        {!! Former::password('password') !!}
      </div>
    </div>


    {!! Former::checkbox('terms_checkbox')->label(' ')->text(trans('texts.agree_to_terms', ['terms' => '<a href="'.NINJA_APP_URL.'/terms" target="_blank">'.trans('texts.terms_of_service').'</a>']))->value(1) !!}
    {!! Former::actions( Button::primary('Submit')->large()->submit() ) !!}
    {!! Former::close() !!}

  </div>

  </body>
</html>
