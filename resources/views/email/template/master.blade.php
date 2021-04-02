@php
    if(!isset($design)) {
        $design = 'light';
    }
    $primary_color = isset($settings) ? $settings->primary_color : '#4caf50';
@endphp

    <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title></title>
</head>

<style type="text/css">
    :root {
        --primary-color: {{ $primary_color }};
    }

    .primary-color-bg {
        background-color: {{ $primary_color }};
    }

    #email-content h1, h2, h3, h4 {
        display: block;
        color: {{ $design == 'light' ? 'black' : 'white' }};
        padding-bottom: 20px;
        padding-top: 20px;
    }

    #email-content p {
        display: block;
        color: {{ $design == 'light' ? 'black' : 'white' }};
        padding-bottom: 20px;
        /*padding-top: 20px;*/
    }

    .button {
        background-color: {{ $primary_color }};
        color: white;
        padding: 10px 16px;
        text-decoration: none;
    }

    #email-content a, .link {
        word-break: break-all;
    }

    #email-content .button {
        position: center;
    }

    .center {
        text-align: center;
    }

    p {
        padding-bottom: 5px;
    }
</style>

<body style="margin: 0; padding: 0; background-color: {{ $design == 'light' ? '#F9FAFB' : '#111827' }};">
<table role="presentation" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td style="padding: 20px; font-family: Arial, sans-serif, 'Open Sans'">
            <table align="center" cellpadding="0" cellspacing="0" width="600"
                   style="box-shadow: 0 1px 3px 0 rgba(0,0,0,.1), 0 1px 2px 0 rgba(0,0,0,.06)">
                <tr>
                    <td align="center" bgcolor="{{ $primary_color }}" class="primary-color-bg" style="padding: 40px 0 30px 0;">
                        {{ $header }}
                    </td>
                </tr>
                <tr>
                    <td bgcolor="{{ $design == 'light' ? '#ffffff' : '#1F2937'}}" style="padding: 40px 30px 40px 30px;">
                        <table cellpadding="0" cellspacing="0" width="100%" style="border-collapse: collapse;">
                            <tr>
                                <td id="email-content">
                                    @yield('greeting')

                                    {{ $slot }}

                                    @yield('signature')
                                    @yield('footer')
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    @isset($whitelabel)
                        @if(!$whitelabel)
                            <td bgcolor="{{ $design == 'light' ? '#ffffff' : '#1F2937'}}" style="padding-top: 20px; padding-bottom: 20px;" align="center">
                                <p style="margin: 0; border-top: 1px solid {{ $design == 'light' ? '#F3F4F6' : '#374151' }}; padding-top: 20px;">
                                    <a href="https://invoiceninja.com" target="_blank">
                                        <img
                                            style="height: 4rem; {{ $design == 'dark' ? 'filter: invert(100%);' : '' }}"
                                            src="{{ asset('images/created-by-invoiceninja-new.png') }}"
                                            alt="Invoice Ninja">
                                    </a>
                                </p>
                            </td>
                        @endif
                    @endif
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>

</html>
