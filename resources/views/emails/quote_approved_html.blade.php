<!DOCTYPE html>
<html lang="{{ App::getLocale() }}">
<head>
  <meta charset="utf-8">
</head>
<body>
  @if ($account->enable_email_markup)
    @include('emails.partials.user_view_action')
  @endif
  {{ trans('texts.email_salutation', ['name' => $userName]) }} <p/>

  {{ trans("texts.notification_quote_approved", ['amount' => $invoiceAmount, 'client' => $clientName, 'invoice' => $invoiceNumber]) }} <p/>  

  {{ trans('texts.email_signature') }} <br/>
  {{ trans('texts.email_from') }} <p/>
  
  {{ trans('texts.user_email_footer') }} <p/>

</body>
</html>