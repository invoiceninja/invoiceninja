<script type="application/ld+json">
[
@if ($entityType == ENTITY_INVOICE)
{
  "@context": "http://schema.org",
  "@type": "Invoice",
  "paymentStatus": "{{ $invoice->present()->paymentStatus }}",
  @if ($invoice->due_date)
  "paymentDue": "{{ $invoice->due_date }}T00:00:00+00:00",
  @endif
  "provider": {
    "@type": "Organization",
    "name": "{{ $account->getDisplayName() }}"
  },
  "broker": {
    "@type": "Organization",
    "name": "Invoice Ninja",
    "url": "{!! NINJA_WEB_URL !!}"
  },
  "totalPaymentDue": {
    "@type": "PriceSpecification",
    "price": "{{ $account->formatMoney(isset($payment) ? $payment->amount : $invoice->getRequestedAmount(), $client) }}"
  },
  "action": {
    "@type": "ViewAction",
    "url": "{!! $link !!}"
  }
},
@endif
{
  "@context": "http://schema.org",
  "@type": "EmailMessage",
  "action": {
    "@type": "ViewAction",
    "url": "{!! $link !!}",
    "name": "{{ trans("texts.view_{$entityType}") }}"
  }
}
]
</script>