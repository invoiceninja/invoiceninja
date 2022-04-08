{!! $title !!}

{!! ctrans('texts.company_import_failure_body') !!}

@if(isset($whitelabel) && !$whitelabel)
{{ ctrans('texts.ninja_email_footer', ['site' => 'https://invoiceninja.com']) }}
@endif
