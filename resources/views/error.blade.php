@extends('public.header')

@section('content')

<p>&nbsp;<p>
<p>&nbsp;<p>

<div class="well">
  <div class="container" style="min-height:400px">
  <h3>Something went wrong...</h3>
  <h4>{{ $error }}</h4>
  <h4>If you'd like help please email us at {{ env('MAIL_USERNAME') }}.</h4>
</div>
</div>

<p>&nbsp;<p>
<p>&nbsp;<p>

<script type="text/javascript">
    
$(function() {
    var height = $(window).height() - ($('.navbar').height() + $('footer').height() + 200);
    $('.well').height(Math.max(200, height));
});

</script>

@stop