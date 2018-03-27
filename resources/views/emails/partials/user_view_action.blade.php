<script type="application/ld+json">
[
{
  "@context": "http://schema.org",
  "@type": "EmailMessage",
  "action": {
    "@type": "ViewAction",
    "url": "{{ $invoiceLink }}",
    "name": {!! json_encode(trans("texts.view_{$entityType}")) !!}
  }
}
]
</script>
