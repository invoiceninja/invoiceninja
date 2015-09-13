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

  {{ trans("texts.notification_{$entityType}_paid", ['amount' => $paymentAmount, 'client' => $clientName, 'invoice' => $invoiceNumber]) }} <p/>

  {{ trans("texts.{$entityType}_link_message") }} <br/>
  <a href="{{ $invoiceLink }}">{{ $invoiceLink }}</a><p/>  
    
  {{ trans('texts.email_signature') }} <br/>
  {{ trans('texts.email_from') }} <p/>
  {{ trans('texts.user_email_footer') }} <p/>
  
  
</body>
</html>