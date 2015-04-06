<script type="application/ld+json">
    {
        "@context":"http://schema.org",
        "@type":"EmailMessage",
        "description":"View your {{ $entityType }}",
        "action": {
            "@type": "ViewAction",
            "url": "{{{ $link }}}",
            "name": "View {{ $entityType }}"
        },        
        "publisher": {
            "@type": "Organization",
            "name": "Invoice Ninja",
            "url": "{{{ NINJA_WEB_URL }}}"
        }
    }
</script>