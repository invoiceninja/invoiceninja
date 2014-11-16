<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
    </head>
    <body>

      {{ $clientName }},<p/>

      {{ trans('texts.payment_message', ['amount' => $paymentAmount]) }}<p/>      

      @if (isset($emailMessage) && $emailMessage)
      {{ $emailMessage }}<p/>
      @endif

      @if ($emailFooter)
      {{ nl2br($emailFooter) }}
      @else
      {{ trans('texts.email_signature') }}<br/>      
      {{ $accountName }}
      @endif

      @if ($showNinjaFooter)
      <p/>
      {{ trans('texts.ninja_email_footer', ['site' => '<a href="' . NINJA_WEB_URL . '/?utm_source=payment_email_footer">Invoice Ninja</a>']) }}
      @endif
      
    </body>
</html>
