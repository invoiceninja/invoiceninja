<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Invoice Ninja</title>
</head>

<body>
<table role="presentation" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td style="font-family: Arial, sans-serif, 'Open Sans'">
            <table cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td>
                        <table cellpadding="0" cellspacing="0" width="100%">
                            <tr>
                                <td id="email-content">
                                    {!! $body !!}
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                @if($signature)
                    <tr>
                        <td>
                            <p>{!! $signature !!}</p>
                        </td>
                    </tr>
                @endif
                <tr>
                    @isset($whitelabel)
                        @if(!$whitelabel)
                            <td>
                                <p>
                                    <a href="https://invoiceninja.com" target="_blank">
                                        {{ __('texts.ninja_email_footer', ['site' => 'Invoice Ninja']) }}
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

