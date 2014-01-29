<!DOCTYPE html>
<html lang="en-US">
    <head>
        <meta charset="utf-8">
    </head>
    <body>

      {{ $clientName }},<p/>

      Thank you for your payment of {{ $paymentAmount }}.<p/>

      @if ($emailFooter)
      {{ $emailFooter }}
      @else
      Best regards,<br/>
      {{ $accountName }}
      @endif

    </body>
</html>