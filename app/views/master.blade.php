<!DOCTYPE html>
<html lang="en">
  <head>

    <title>Invoice Ninja {{ isset($title) ? $title : ' - Free Online Invoicing' }}</title>
    <link rel="canonical" href="https://www.invoiceninja.com"></link>
    <link href="{{ asset('favicon.ico') }}" rel="icon" type="image/x-icon">    
    
    <link href='//fonts.googleapis.com/css?family=Roboto:400,700,900,100' rel='stylesheet' type='text/css'>
    <link href='//fonts.googleapis.com/css?family=Roboto+Slab:400,300,700' rel='stylesheet' type='text/css'>

    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta property="og:site_name" content="Invoice Ninja"></meta>
    <meta property="og:url" content="https://www.invoiceninja.com"></meta>
    <meta property="og:title" content="Invoice Ninja"></meta>
    <meta property="og:image" content="https://www.invoiceninja.com/images/social.jpg"></meta>
    <meta property="og:description" content="Simple, Intuitive Invoicing."></meta>
    <meta name="keywords" content="Invoice Ninja"></meta>    
    
    @if (File::exists('vendor/jquery/jquery.js'))
      <script src="{{ asset('vendor/jquery/jquery.js') }}" type="text/javascript"></script>  
    @elseif (File::exists('vendor/jquery/dist/jquery.js'))
      <script src="{{ asset('vendor/jquery/dist/jquery.js') }}" type="text/javascript"></script>  
    @else
      {{ die('Error: Failed to find jQuery') }}
    @endif
 
    <script type="text/javascript">
    window.onerror = function(e) {
      try {
        $.ajax({
          type: 'GET',
          url: '{{ URL::to('log_error') }}',
          data: 'error='+encodeURIComponent(e)+'&url='+encodeURIComponent(window.location)
        });     
      } catch(err) {}
      return false;
    }
    </script>

    <!-- HTML5 shim and Respond.js IE8 support of HTML5 elements and media queries -->
    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
      <script src="https://oss.maxcdn.com/libs/respond.js/1.3.0/respond.min.js"></script>
    <![endif]-->
    
    @yield('head')

  </head>

  <body>

    @if (App::environment() == ENV_PRODUCTION && isset($_ENV['ANALYTICS_KEY']) && $_ENV['ANALYTICS_KEY'])  
      <script>
        (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
        (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
        m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
        })(window,document,'script','//www.google-analytics.com/analytics.js','ga');

        ga('create', '{{ $_ENV['ANALYTICS_KEY'] }}');
        ga('send', 'pageview');
      </script>
    @endif

    @yield('body')

    <script type="text/javascript">
      var NINJA = NINJA || {};
      NINJA.formIsChanged = false;
      $(function() {      
        $('form.warn-on-exit input, form.warn-on-exit textarea, form.warn-on-exit select').on('change', function() {
          NINJA.formIsChanged = true;          
        }); 
      });
      $(window).on('beforeunload', function() {
        if (NINJA.formIsChanged) {
          return "{{ trans('texts.unsaved_changes') }}";
        }
      }); 
      //$('a[rel!=ext]').click(function() { $(window).off('beforeunload') });
    </script> 

  </body>

</html>