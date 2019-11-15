<!doctype html>
<html lang="en">
<head>
    <title>@yield('title', config('app.name')) - {!! trans('texts.migrate_to_v2') !!}</title>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link href="https://fonts.googleapis.com/css?family=Rubik&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/tailwindcss@^1.0/dist/tailwind.min.css" rel="stylesheet">

    <script src="https://cdnjs.cloudflare.com/ajax/libs/turbolinks/5.2.0/turbolinks.js" integrity="sha256-iM4Yzi/zLj/IshPWMC1IluRxTtRjMqjPGd97TZ9yYpU=" crossorigin="anonymous"></script>
    <script !src="">
        Turbolinks.start();
    </script>
</head>

<style>
    body {
        font-family: 'Rubik', sans-serif;
    }
</style>

<body class="bg-gray-200">

<div class="flex mt-20 justify-center">
    <div class="w-full mx-8 md:mx-0 md:w-1/2 rounded-lg flex items-center justify-between">
        <section>
            <p class="text-xl font-medium">{{ $step_title }}</p>
            @isset($step_description)
                <p class="text-sm font-thin hidden md:block">{{ $step_description }}</p>
            @endif
        </section>
        <a href="https://invoiceninja.com">
            <img class="w-16 hidden md:block"
                 src="https://www.invoiceninja.com/wp-content/uploads/2015/10/logo-blk-vertical-1.png"
                 alt="Invoice Ninja Logo">
        </a>
    </div>
</div>

<div class="flex mt-6 justify-center mb-10">
    <div class="w-full mx-8 md:mx-0 md:w-1/2 p-6 rounded-lg bg-white shadow">
        @yield('body')
    </div>
</div>

</body>

</html>