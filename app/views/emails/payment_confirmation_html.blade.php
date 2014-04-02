<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
    </head>
    <body>

      {{ $clientName }},<p/>

      {{ trans('texts.payment_message', ['amount' => $paymentAmount]) }}<p/>      

      @if ($emailFooter)
      {{ nl2br($emailFooter) }}
      @else
      {{ trans('texts.email_signature') }}<br/>      
      {{ $accountName }}
      @endif

    </body>
</html>