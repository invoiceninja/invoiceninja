<!DOCTYPE html>
<html lang="en-US">
<head>
  <meta charset="utf-8">
</head>
<body>

  {{ trans('texts.email_salutation', ['name' => $userName]) }} <p/>

  {{ trans("texts.notification_{$entityType}_viewed", ['amount' => $invoiceAmount, 'client' => $clientName, 'invoice' => $invoiceNumber]) }} <p/>

  {{ trans('texts.email_signature') }} <br/>
  {{ trans('texts.email_from') }} <p/>
  
  {{ trans('texts.user_email_footer') }} <p/>

</body>
</html>