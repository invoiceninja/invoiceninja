<!DOCTYPE html>
<html lang="en-US">
<head>
  <meta charset="utf-8">
</head>
<body>

  Dear {{ $userName }}, <p/>

  A payment of {{ $paymentAmount }} was made by client {{ $clientName }} towards invoice {{ $invoiceNumber }}. <p/>

  To view your client invoice click the link below: <br/>
  {{ $invoiceLink }} <p/>

  To adjust your email notification settings please <a href="http://www.invoiceninja.com/account/settings">click here</a>.

</body>
</html>