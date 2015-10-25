@extends('public.header')

@section('content')

<style type="text/css">

body {
    background-color: #f8f8f8;
    color: #1b1a1a;
}

.panel-body {
    padding-bottom: 100px;
}

@media screen and (min-width: 700px) {
    header {
        margin: 20px 0 75px;
        float: left;
    }

    .panel-body {
        padding-left: 150px;
        padding-right: 150px;
    }

}


header {
    margin: 0px !important
}

h2 {
    font-weight: 300;
    font-size: 30px;
    color: #2e2b2b;
    line-height: 1;
}

h3 {
    font-weight: 900;
    margin-top: 10px;
    font-size: 15px;
}

h3 .help {
    font-style: italic;
    font-weight: normal;
    color: #888888;
}

header h3 {
    text-transform: uppercase;    
}
    
header h3 span {
    display: inline-block;
    margin-left: 8px;
}
    
header h3 em {
    font-style: normal;
    color: #eb8039;
}


</style>


<div class="container">
<p>&nbsp;</p>

<div class="panel panel-default">
  <div class="panel-body">

    <div class="row">
        <div class="col-md-7">
            <header>
                @if (isset($redirectTo))
                    <h2>Payment Complete</h2>
                @else 
                    <h2>License Key<br/><small>{{ $message }}</small></h2>                
                @endif                    
            </header>
        </div>
    </div>

    <p>&nbsp;</p>
    <p>&nbsp;</p>

    <div class="row">
      <div class="col-md-12">
        <h2 style="text-align:center">
            @if (isset($redirectTo))
                {{ $message }}
            @else
                {{ $license }}
            @endif
        </h2>      
      </div>      
    </div>  

</div>
</div>

<div style="height:300px"></div>

</div>


<script type="text/javascript">

$(function() {
    fbq('track', 'Purchase', {value: '{{ $price }}', currency: 'USD'});
    trackEvent('/license', '/product_{{ $productId }}');

    @if (isset($redirectTo))
        setTimeout(function() {
            location.href = "{!! $redirectTo !!}";
        }, 3000);
    @endif

})

</script>

@stop