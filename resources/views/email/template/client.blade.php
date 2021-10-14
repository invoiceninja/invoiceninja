@php
    $primary_color = isset($settings) ? $settings->primary_color : '#4caf50';
@endphp

<!DOCTYPE html
        PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en-US">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
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
                border-color: #3a3a3c !important;
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

            .dark-text-white p {
                color: #ffffff !important;
            }

            hr {
                border-color: #474849 !important;
            }
        @endif

        #content .button {
            font-size: 13px; 
            font-weight: 600; 
            text-decoration: none !important; 
            text-transform: uppercase; 
            color: #ffffff; 
            display: block;
            width: 30%;
            padding: 15px;
            text-align: center;
            margin: 0 auto;
            background-color: {{ $primary_color }};
        }
        #content h1 {
            font-family: 'canada-type-gibson', 'roboto', Arial, Helvetica, sans-serif;
            font-weight: 600;
            font-size: 32px;
            margin-top: 20px;
            margin-bottom: 30px;
        }

        #content > p {
            font-size: 16px;
            font-family: 'roboto', Arial, Helvetica, sans-serif;
            font-weight: 500;
        }
        #content .center {
            text-align: center;
        }
        #content .left {
            text-align: left !important;
        }
    </style>
</head>
<body>

<table border="0" cellpadding="0" cellspacing="0" width="100%"
       style="min-width: 320px; font-size: 1px; line-height: normal;">
    <tr>
        <td align="center" valign="top">
            <table class="dark-bg-base" cellpadding="0" cellspacing="0" border="0" width="570"
                   style="max-width: 570px; min-width: 320px; background: #ffffff;"
            >
                <tr>
                    <td>
                        <div style="height: 25px; line-height: 25px; font-size: 23px;">&nbsp;</div>
                        <div style="text-align: center;">
                            <a href="http://google.com/"
                               style="color:#89888a; font-size: 10px; font-family:Verdana, Geneva, Tahoma, sans-serif;">
                                <span style="color:#89888a; font-size: 10px; font-family:Verdana, Geneva, Tahoma, sans-serif;">
                                </span>
                            </a>
                        </div>
                        <div style="height: 10px; line-height: 10px; font-size: 8px;">&nbsp;</div>
                    </td>
                </tr>

                <tr>
                    <td align="center"
                        style="border-left: 1px solid #c2c2c2; border-top: 1px solid #c2c2c2; border-right: 1px solid #c2c2c2; border-bottom: none; border-top-left-radius: 3px; border-top-right-radius: 3px;">

                        <div class="logo-light" style="display: block; max-width: 155px;">

                            <div style="height: 20px; line-height: 20px; font-size: 18px;">&nbsp;</div>

                            <img src="{{ $logo ?? '' }}" alt="Logo" width="155" border="0"
                                 style="display: block; width: 155px; max-width: 100%;"/>
                        </div>

                        <div style="height: 20px; line-height: 20px; font-size: 18px;">&nbsp;</div>
                    </td>
                </tr>
                <tr>
                    <td style="border-left: 1px solid #c2c2c2; border-right: 1px solid #c2c2c2; border-top: none; border-bottom: none; text-align: center;" id="content">
                        <hr style="border: 0; border-top: 1px solid #dbdbdb; max-width: 400px;"/>

                        <div style="height: 30px; line-height: 30px; font-size: 28px;">&nbsp;</div>

                        {{ $slot ?? '' }}
                        {!! $body ?? '' !!}

                        <div style="height: 30px; line-height: 30px; font-size: 28px;">&nbsp;</div>
                    </td>
                </tr>
                <tr>
                    <td>
                        <div class="dark-bg dark-text-white"
                             style="text-align: center; background-color: #f9f9f9; border: 1px solid #c2c2c2; border-top: none; border-bottom-color: #f9f9f9;">

                             @isset($signature)
                                <div style="height: 25px; line-height: 25px; font-size: 23px;">&nbsp;</div>

                                <p style="font-size: 15px; color: #2e2e2e; font-family: 'roboto', Arial, Helvetica, sans-serif; font-weight: 400; margin: 0;">
                                    {!! nl2br($signature) !!}
                                </p>
                            @endisset

                            <div style="height: 45px; line-height: 45px; font-size: 43px;">&nbsp;</div>

                            @if(isset($company) && $company instanceof \App\Models\Company)
                                <p style="font-size: 15px; color: #2e2e2e; font-family: 'roboto', Arial, Helvetica, sans-serif; font-weight: 400; margin: 0;">
                                    {{ $company->present()->name() }}</p>
                                <p style="font-size: 15px; color: #2e2e2e; font-family: 'roboto', Arial, Helvetica, sans-serif; font-weight: 400; margin: 0;">
                                    <span>{{ $company->settings->phone }}</span>
                                    <span style="font-weight: 500"> {{ $company->settings->website }}</span>
                                </p>
                            @endif

                            <div style="height: 40px; line-height: 40px; font-size: 38px;">&nbsp;</div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td class="dark-bg-base"
                        style="background-color: #242424; border-left: 1px solid #c2c2c2; border-top: 1px solid #242424; border-right: 1px solid #c2c2c2; border-bottom: 1px solid #242424;">
                        <div style="height: 10px; line-height: 10px; font-size: 8px;">&nbsp;</div>

                        @if(isset($company))
                                @if($company->account->isPaid())
                                    <p style="text-align: center; color: #ffffff; font-size: 10px; font-family: Verdana, Geneva, Tahoma, sans-serif;">
                                        &copy; {{ date('Y') }} {{ $company->present()->name() }}, All Rights Reserved
                                    </p>
                                @else
                                    <p style="text-align: center; color: #ffffff; font-size: 10px; font-family: Verdana, Geneva, Tahoma, sans-serif;">
                                        &copy; {{ date('Y') }} Invoice Ninja, All Rights Reserved
                                    </p>
                                @endif
                        @else
                            <p style="text-align: center; color: #ffffff; font-size: 10px; font-family: Verdana, Geneva, Tahoma, sans-serif;">
                                © {{ date('Y') }} Invoice Ninja, All Rights Reserved
                            </p>
                        @endif

                        <div style="height: 10px; line-height: 10px; font-size: 8px;">&nbsp;</div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>


</html>