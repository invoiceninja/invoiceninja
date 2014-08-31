<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
    </head>
    <body>

      {{ $clientName }},<p/>

      {{ trans("texts.{$entityType}_message", ['amount' => $invoiceAmount]) }}<p/>      
      <a href="{{ $link }}">{{ $link }}</a><p/>

      @if ($emailFooter)
      {{ nl2br($emailFooter) }}
      @else
      {{ trans('texts.email_signature') }}<br/>
      {{ $accountName }}
      @endif

      @if ($showNinjaFooter)
      <p/>
      {{ trans('texts.ninja_email_footer', ['site' => '<a href="' . NINJA_URL . '/?utm_source=invoice_email_footer">Invoice Ninja</a>']) }}
      @endif    

    </body>
</html>
