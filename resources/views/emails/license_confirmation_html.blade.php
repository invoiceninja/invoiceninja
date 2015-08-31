<!DOCTYPE html>
<html lang="{{ App::getLocale() }}">
<head>
  <meta charset="utf-8">
</head>
<body>

  {{ $client }},<p/>

  {{ trans('texts.payment_message', ['amount' => $amount]) }}<p/>      

  {{ $license }}<p/>

  {{ trans('texts.email_signature') }}<br/>      
  {{ $account }}
  
</body>
</html>