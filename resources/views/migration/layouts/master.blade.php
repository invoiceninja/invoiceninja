<!doctype html>
<html lang="{{ app()->getLocale() }}">

<head>
    <title>@yield('title', '👏') - Invoice Ninja</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
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

                @if(session('message') && session()->get('message')['type'] == 'single')
                    <div class="alert alert-info">
                        {{ session('message')['content'] }}
                    </div>
                @endif

                @if(session('message') && session()->get('message')['type'] == 'array')
                    <div class="alert alert-info">
                        @foreach(session()->get('message')['errors'] as $error)
                            @if(is_array($error))
                                @foreach($error as $suberror)
                                    <li>{{ $suberror }}</li>
                                @endforeach
                            @else 
                                <li>{{ $error }}</li>
                            @endif
                        @endforeach
                    </div>
                @endif

                @yield('content')

            </div>

        </div>
    </div>
</body>

</html>