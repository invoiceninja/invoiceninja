<!DOCTYPE html>
<html lang="en-US">
    <head>
        <meta charset="utf-8">
    </head>
    <body>

      {{ $clientName }},<p/>

      To view your invoice for {{ $invoiceAmount }}, click the link below:<p/>

      {{ $link }}<p/>

      @if ($emailFooter)
      {{ nl2br($emailFooter) }}
      @else
      Best regards,<br/>
      {{ $accountName }}
      @endif

    </body>
</html>