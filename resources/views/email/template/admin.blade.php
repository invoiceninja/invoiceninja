@php
    $primary_color = isset($settings) ? $settings->primary_color : '#4caf50';
@endphp

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
        "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en-US">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">

    <style type="text/css">
        @import url("https://use.typekit.net/zxn7pho.css");

        :root {
            color-scheme: light dark;
            supported-color-schemes: light dark;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: "Roboto", Arial, Helvetica, sans-serif;
            color: #3b3b3b;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        @if(isset($settings) && $settings->email_style === 'dark')
            body,
            [data-ogsc],
            [data-ogsb] {
                background-color: #1a1a1a !important;
                color: #ffffff !important;
            }

            div, tr, td,
            [data-ogsc] div,
            [data-ogsc] tr,
            [data-ogsc] td,
            [data-ogsb] div,
            [data-ogsb] tr,
            [data-ogsb] td {
                border-color: #222222 !important;
            }

            h1, h2, h3, p, td,
            [data-ogsc] h1,
            [data-ogsc] h2,
            [data-ogsc] h3,
            [data-ogsc] p,
            [data-ogsc] td,
            [data-ogsb] h1,
            [data-ogsb] h2,
            [data-ogsb] h3,
            [data-ogsb] p,
            [data-ogsb] td {
                color: #ffffff !important;
            }

            p,
            [data-ogsc] p,
            [data-ogsb] p {
                color: #bbbbbc !important;
            }

            .dark-bg-base,
            [data-ogsc] .dark-bg-base,
            [data-ogsb] .dark-bg-base {
                background-color: #222222 !important;
            }

            .dark-bg,
            [data-ogsc] .dark-bg,
            [data-ogsb] .dark-bg {
                background-color: #3a3a3c !important;
            }

            .logo-dark,
            [data-ogsc] .logo-dark,
            [data-ogsb] .logo-dark {
                display: block !important;
            }

            .logo-light,
            [data-ogsc] .logo-light,
            [data-ogsb] .logo-light {
                display: none !important;
                mso-hide: all;
            }

            .btn-white,
            [data-ogsc] .btn-white,
            [data-ogsb] .btn-white {
                background-color: #fefefe !important;
            }
        @endif

        #content h1 {
            font-family: 'canada-type-gibson', 'roboto', Arial, Helvetica, sans-serif; 
            font-weight: 600; 
            font-size: 32px;
        }

        #content span {
            font-family: 'canada-type-gibson', 'roboto', Arial, Helvetica, sans-serif; 
            font-weight: 600; 
            font-size: 32px;
        }

        #content .button {
            font-size: 13px; 
            font-weight: 600; 
            text-decoration: none !important; 
            text-transform: uppercase; 
            color: #ffffff; 
            display: block;
            width: 30%;
            padding: 15px;
            background-color: {{ $primary_color }};
        }
    </style>
</head>
<body>

<table border="0" cellpadding="0" cellspacing="0" width="100%"
       style="min-width: 320px; font-size: 1px; line-height: normal;"
>
    <tr>
        <td align="center" valign="top">
            <table cellpadding="0" cellspacing="0" border="0" width="570"
                   style="max-width: 570px; min-width: 320px; background: #ffffff;"
            >
                <tr>
                    <td>
                        <div style="height: 25px; line-height: 25px; font-size: 23px;">&nbsp;</div>
                        <div style="text-align: center;">
                            <a href="http://google.com/"
                               style="color:#89888a; font-size: 10px; font-family:Verdana, Geneva, Tahoma, sans-serif;">
                                <span style="color:#89888a; font-size: 10px; font-family:Verdana, Geneva, Tahoma, sans-serif;">
                                    <!-- spacing on top -->
                                </span>
                            </a>
                        </div>
                        <div style="height: 10px; line-height: 10px; font-size: 8px;">&nbsp;</div>
                    </td>
                </tr>

                <tr>
                    <td align="center"
                        style="border-left: 1px solid #c2c2c2; border-top: 1px solid #c2c2c2; border-right: 1px solid #c2c2c2; border-bottom: none; border-top-left-radius: 3px; border-top-right-radius: 3px;">
                        <div class="dark-bg"
                             style="background-color:#f9f9f9;">

                            <div class="logo-light" style="display: block; max-width: 155px;">

                                <div style="height: 20px; line-height: 20px; font-size: 18px;">&nbsp;</div>

                                <img src="{{ $logo ?? ''}}" alt="Logo" width="155" border="0"
                                     style="display: block; width: 155px; max-width: 100%;"/>
                            </div>

                            <div style="height: 20px; line-height: 20px; font-size: 18px;">&nbsp;</div>
                        </div>
                    </td>
                </tr>

                <tr>
                    <td align="center"
                        style="border-left: 1px solid #c2c2c2; border-top: none; border-right: 1px solid #c2c2c2; border-bottom: none;">
                        <div class="dark-bg-base" id="content">
                            <div style="height: 35px; line-height: 35px; font-size: 33px;">&nbsp;</div>
                                {{ $slot ?? '' }}
                            <div style="height: 30px; line-height: 30px; font-size: 28px;">&nbsp;</div>
                        </div>

                    </td>
                </tr>

                <tr class="dark-bg"
                    style="background-color: {{ $primary_color }};">
                    <td style="border-left: 1px solid #c2c2c2; border-right: 1px solid #c2c2c2;">

                        <div style="height: 25px; line-height: 25px; font-size: 23px;">&nbsp;</div>

                        <h2 style="text-align: center; color: #ffffff; font-family: 'canada-type-gibson', 'roboto', Arial, Helvetica, sans-serif; font-weight: 500; font-size: 26px;">
                            <span style="text-align: center; color: #ffffff; font-family: 'canada-type-gibson', 'roboto', Arial, Helvetica, sans-serif; font-weight: 500; font-size: 26px;">
                                Questions? We're here to help!
                            </span>
                        </h2>

                        <div style="height: 25px; line-height: 25px; font-size: 23px;">&nbsp;</div>

                        <table cellspacing="0" cellpadding="0" border="0" align="center" width="100%">
                            <tbody>
                            <tr>

                                <td width="20" style="width: 20px; max-width: 20px; min-width: 20px;">&nbsp;</td>

                                <td>

                                    <table align="center" cellpadding="0" cellspacing="0" border="0" width="100%"
                                           style="border-collapse: collapse;">

                                        <tr>
                                            <td align="center" valign="middle"
                                                style="background-color: #ffffff; padding: 12px 16px;">
                                                <a href="https://forum.invoiceninja.com" class="btn-white" target="_blank"
                                                   style="vertical-align: middle; font-size: 12px; text-decoration: none; color: {{ $primary_color }}; display: block;">
                                                    <img style="width: 13px; margin-right: 4px; display: inline-block; vertical-align:middle;"
                                                         width="13"
                                                         src="{{ asset('images/emails/forum.png') }}"/>
                                                    <span style="vertical-align: middle; font-size: 12px; text-decoration: none; color: {{ $primary_color }};">Forums</span>
                                                </a>
                                            </td>
                                        </tr>

                                    </table>

                                </td>

                                <td width="20" style="width: 20px; max-width: 20px; min-width: 20px;">&nbsp;</td>

                                <td>

                                    <table align="center" cellpadding="0" cellspacing="0" border="0" width="100%">

                                        <tr>
                                            <td align="center" valign="middle"
                                                style="background-color: #ffffff; padding: 12px 16px;">
                                                <a href="http://slack.invoiceninja.com/" class="btn-white" target="_blank"
                                                   style="font-size: 12px; text-decoration: none; color: {{ $primary_color }}; display: block;">
                                                    <img style="width: 13px; margin-right: 4px; display: inline-block; vertical-align:middle;"
                                                         width="13"
                                                         src="{{ asset('images/emails/slack.png') }}">
                                                    <span style="vertical-align: middle;">Slack</span>
                                                </a>
                                            </td>
                                        </tr>

                                    </table>

                                </td>

                                <td width="20" style="width: 20px; max-width: 20px; min-width: 20px;">&nbsp;</td>

                                <td>

                                    <table align="center" cellpadding="0" cellspacing="0" border="0" width="100%">

                                        <tr>
                                            <td align="center" valign="middle"
                                                style="background-color: #ffffff; padding: 12px 16px;">
                                                <a href="https://www.invoiceninja.com/contact/" class="btn-white" target="_blank"
                                                   style="font-size: 12px; text-decoration: none; color: {{ $primary_color }}; display: block;">
                                                    <img style="width: 13px; margin-right: 4px; display: inline-block; vertical-align:middle;"
                                                         width="13"
                                                         src="{{ asset('images/emails/email.png') }}">
                                                    <span style="vertical-align: middle;">Email</span>
                                                </a>
                                            </td>
                                        </tr>

                                    </table>

                                </td>

                                <td width="20" style="width: 20px; max-width: 20px; min-width: 20px;">&nbsp;</td>

                                <td>

                                    <table align="center" cellpadding="0" cellspacing="0" border="0" width="100%">

                                        <tr>
                                            <td align="center" valign="middle"
                                                style="background-color: #ffffff; padding: 12px 16px;">
                                                <a href="https://invoiceninja.github.io/" class="btn-white" target="_blank"
                                                   style="vertical-align: middle; font-size: 12px; text-decoration: none; color: {{ $primary_color }}; display: block;">
                                                    <span style="vertical-align: middle; font-size: 12px; text-decoration: none; color: {{ $primary_color }}; display: block;">
                                                        Support Docs
                                                    </span>
                                                </a>
                                            </td>
                                        </tr>

                                    </table>

                                </td>

                                <td width="20" style="width: 20px; max-width: 20px; min-width: 20px;">&nbsp;</td>

                            </tr>


                            </tbody>
                        </table>

                        <div style="height: 35px; line-height: 35px; font-size: 33px;">&nbsp;</div>

                    </td>
                </tr>

                <tr>
                    <td class="dark-bg-base"
                        style="background-color: #242424; border-left: 1px solid #c2c2c2; border-top: 1px solid #242424; border-right: 1px solid #c2c2c2; border-bottom: 1px solid #242424;">
                        <div style="height: 10px; line-height: 10px; font-size: 8px;">&nbsp;</div>

                        <p style="text-align: center; color: #ffffff; font-size: 10px;
                            font-family: Verdana, Geneva, Tahoma, sans-serif;">
                            <span style="text-align: center; color: #ffffff; font-size: 10px;
                            font-family: Verdana, Geneva, Tahoma, sans-serif;">
                                &copy; {{ date('Y') }} Invoice Ninja, All Rights Reserved
                            </span>
                        </p>

                        <div style="height: 10px; line-height: 10px; font-size: 8px;">&nbsp;</div>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>

</body>
</html>
