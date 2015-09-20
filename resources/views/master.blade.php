<!DOCTYPE html>
<html lang="{{App::getLocale()}}">
<head>
    <title>{{ isset($title) ? ($title . ' | Invoice Ninja') : ('Invoice Ninja | ' . trans('texts.app_title')) }}</title> 
    <meta name="description" content="{{ isset($description) ? $description : trans('texts.app_description') }}" />

    <!-- Source: https://github.com/hillelcoren/invoice-ninja -->
    <!-- Version: {{ NINJA_VERSION }} -->

    <meta charset="utf-8">
    <meta property="og:site_name" content="Invoice Ninja" />
    <meta property="og:url" content="{{ SITE_URL }}" />
    <meta property="og:title" content="Invoice Ninja" />
    <meta property="og:image" content="{{ SITE_URL }}/images/social.jpg" />
    <meta property="og:description" content="Simple, Intuitive Invoicing." />

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="msapplication-config" content="none"/> 

    <link href="//fonts.googleapis.com/css?family=Roboto:400,700,900,100&subset=latin,latin-ext" rel="stylesheet" type="text/css">
    <link href="//fonts.googleapis.com/css?family=Roboto+Slab:400,300,700&subset=latin,latin-ext" rel="stylesheet" type="text/css">
    <link href="{{ asset('favicon.png?test') }}" rel="shortcut icon">
    <link rel="canonical" href="{{ NINJA_APP_URL }}/{{ Request::path() }}" />

    <script src="{{ asset('js/built.js') }}?no_cache={{ NINJA_VERSION }}" type="text/javascript"></script>    

    <script type="text/javascript">
        var NINJA = NINJA || {};      
        NINJA.isRegistered = {{ \Utils::isRegistered() ? 'true' : 'false' }};    

        window.onerror = function(e) {
            var message = e.message ? (e.message + ' - ' + e.filename + ': ' + e.lineno) : e;
            try {
                $.ajax({
                    type: 'GET',
                    url: '{{ URL::to('log_error') }}',
                    data: 'error='+encodeURIComponent(message)+'&url='+encodeURIComponent(window.location)
                });     
            } catch(err) {}
            return false;
        }

        /* Set the defaults for DataTables initialisation */
        $.extend( true, $.fn.dataTable.defaults, {
            "bSortClasses": false,
            "sDom": "t<'row-fluid'<'span6'i><'span6'p>>l",
            "sPaginationType": "bootstrap",
            "bInfo": true,
            "oLanguage": {
                'sEmptyTable': "{{ trans('texts.empty_table') }}",
                'sLengthMenu': '_MENU_ {{ trans('texts.rows') }}',
                'sSearch': ''
            }
        } );
        
        /*   
        $.extend( true, $.fn.datepicker.defaults, {
            language:'{{App::getLocale()}}'
        });
        */
        
    </script>

    <!-- HTML5 shim and Respond.js IE8 support of HTML5 elements and media queries -->
    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
    <script src="https://oss.maxcdn.com/libs/respond.js/1.3.0/respond.min.js"></script>
    <![endif]-->

@yield('head')

</head>

<body>

    @if (isset($_ENV['TAG_MANAGER_KEY']) && $_ENV['TAG_MANAGER_KEY'])  
    <!-- Google Tag Manager -->
    <noscript><iframe src="//www.googletagmanager.com/ns.html?id={{ $_ENV['TAG_MANAGER_KEY'] }}"
        height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
        <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
            new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
        j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
        '//www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','{{ $_ENV['TAG_MANAGER_KEY'] }}');</script>      
    <!-- End Google Tag Manager -->

    <script>
        function trackEvent(category, action) {}
    </script>
    @elseif (isset($_ENV['ANALYTICS_KEY']) && $_ENV['ANALYTICS_KEY'])  
    <script>
        (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
            (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
            m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
        })(window,document,'script','//www.google-analytics.com/analytics.js','ga');

        ga('create', '{{ $_ENV['ANALYTICS_KEY'] }}', 'auto');        
        ga('send', 'pageview');

        function trackEvent(category, action) {
            ga('send', 'event', category, action, this.src);
        }
    </script>
    @else
    <script>
        function trackEvent(category, action) {}
    </script>
    @endif

@yield('body')

<script type="text/javascript">
    NINJA.formIsChanged = {{ isset($formIsChanged) && $formIsChanged ? 'true' : 'false' }};
    $(function() {      
        $('form.warn-on-exit input, form.warn-on-exit textarea, form.warn-on-exit select').change(function() {
            NINJA.formIsChanged = true;
        }); 

        @if (Session::has('trackEventCategory') && Session::has('trackEventAction'))
            trackEvent('{{ session('trackEventCategory') }}', '{{ session('trackEventAction') }}');            
        @endif
    });
    $('form').submit(function() {
        NINJA.formIsChanged = false;
    });
    $(window).on('beforeunload', function() {
        if (NINJA.formIsChanged) {
            return "{{ trans('texts.unsaved_changes') }}";
        } else {
            return undefined;
        }
    }); 
    function openUrl(url, track) {
        trackEvent('/view_link', track ? track : url);
        window.open(url, '_blank');
    }

//$('a[rel!=ext]').click(function() { $(window).off('beforeunload') });
</script> 

</body>

</html>