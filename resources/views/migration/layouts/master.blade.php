<!doctype html>
<html lang="{{ app()->getLocale() }}">

<head>
    <title>@yield('title', '👏') - Invoice Ninja</title>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">

    <link rel="stylesheet" href="{{ asset('css/built.css') }}?no_cache={{ NINJA_VERSION }}">
    <script src="{{ asset('built.js') }}?no_cache={{ NINJA_VERSION }}" type="text/javascript"></script>

</head>

<body class="container">
<div class="row">
    <div class="center-block">

        <div class="col-md-8 col-md-offset-2">

            <div id="intro" style="margin-top: 8rem; margin-bottom: 3rem;">
                <h3>{!! $intro_title !!}</h3>
                <p>{!! $intro_text !!}</p>
            </div>

            @if($errors->any())
                <div class="alert alert-danger">
                    <ul>
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(session('success'))
                <div class="alert alert-success">
                    <span>{{ session('success') }}</span>
                </div>
            @endif


            @if(session('danger'))
                <div class="alert alert-danger">
                    @foreach(session('danger') as $error)
                        <p>{{ $error[0] }}</p>
                    @endforeach
                </div>
            @endif

            @yield('content')

        </div>

    </div>
</div>
</body>

</html>