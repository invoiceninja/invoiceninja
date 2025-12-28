@component('email.template.admin', ['logo' => $company ? $company->present()->logo() : ''])
    {!! $message !!}
@endcomponent
