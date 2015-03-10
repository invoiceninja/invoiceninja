<html>
<body>
  <script type="application/ld+json">
    {
      "@context": "http://schema.org",
      "@type": "EmailMessage",
      "action": {
        "@type": "ConfirmAction",
        "name": "Approve Expense",
        "handler": {
          "@type": "HttpActionHandler",
          "url": "https://myexpenses.com/approve?expenseId=abc123"
      }
  },
  "description": "Approval request for John's $10.13 expense for office supplies"
}
</script>

<h1>{{ trans('texts.confirmation_header') }}</h1>

<p>
  {{ $invitationMessage . trans('texts.confirmation_message') }}<br/>
  <a href='{{{ URL::to("user/confirm/{$user->confirmation_code}") }}}'>
      {{{ URL::to("user/confirm/{$user->confirmation_code}") }}}
  </a>
  <p/>

  {{ trans('texts.email_signature') }}<br/>
  {{ trans('texts.email_from') }}

</body>
</html>