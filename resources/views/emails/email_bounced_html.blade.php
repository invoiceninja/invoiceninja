<!DOCTYPE html>
<html lang="{{ App::getLocale() }}">
<head>
  <meta charset="utf-8">
</head>
<body>
  @if (false)
    @include('emails.view_action', ['link' => $invoiceLink, 'entityType' => $entityType])
  @endif
  {{ trans('texts.email_salutation', ['name' => $userName]) }} <p/>

  {{ trans("texts.notification_{$entityType}_bounced", ['contact' => $contactName, 'invoice' => $invoiceNumber]) }} <p/>  

  {{ $emailError }}<p/>

  {{ trans('texts.email_signature') }} <br/>
  {{ trans('texts.email_from') }} <p/>

</body>
</html>