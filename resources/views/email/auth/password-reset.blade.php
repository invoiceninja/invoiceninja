@component('email.template.master', ['design' => 'light'])

@slot('header')
    @component('email.components.header', ['p' => '', 'logo' => 'https://www.invoiceninja.com/wp-content/uploads/2019/01/InvoiceNinja-Logo-Round-300x300.png'])
        Hello!
    @endcomponent

@endslot

You are receiving this email because we received a password reset request for your account.

@component('email.components.button', ['url' => $link, 'show_link' => true])
    Reset Password
@endcomponent


@component('email.components.footer', ['url' => 'https://invoiceninja.com', 'url_text' => '&copy; InvoiceNinja'])
    If you’re having trouble clicking the "Reset Password" button, copy and paste the URL below into your web browser:
    <a href="{{ $link }}">{{ $link }}</a>
@endcomponent

@endcomponent