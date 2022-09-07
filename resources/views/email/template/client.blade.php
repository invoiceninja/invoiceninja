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

    <style>
        @import url("https://use.typekit.net/zxn7pho.css");
    </style>

    <style type="text/css">
        :root {
            color-scheme: light dark;
            supported-color-schemes: light dark;
        }
        @if(isset($settings) && $settings->email_style === 'dark')
            body {
            background-color: #1a1a1a !important;
            color: #ffffff !important;
        }
        div, tr, td {
            border-color: #222222 !important;
        }
        h1, h2, h3, p, td {
            color: #ffffff !important;
        }
        p {
            color: #bbbbbc !important;
        }
        .dark-bg-base {
            background-color: #222222 !important;
        }
        .dark-bg {
            background-color: #3a3a3c !important;
        }
        .dark-text-white p {
            color: #ffffff !important;
        }
        hr {
            border-color: #474849 !important;
        }
        @endif
         /** Content-specific styles. **/
        #content .button {
            display: inline-block;
            background-color: {{ $primary_color }};
            color: #ffffff;
            text-transform: uppercase;
            letter-spacing: 2px;
            text-decoration: none;
            font-size: 13px;
            padding: 15px 50px;
            font-weight: 600;
            margin-bottom: 30px;
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

    <!--[if gte mso 9]>
    <xml>
        <o:OfficeDocumentSettings>
        <o:AllowPNG/>
        <o:PixelsPerInch>96</o:PixelsPerInch>
        </o:OfficeDocumentSettings>
    </xml>
    <![endif]-->
</head>

<body
    style="margin: 0; padding: 0; font-family: 'roboto', Arial, Helvetica, sans-serif; color: #3b3b3b;-webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale;">
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td>
            <table align="center" border="0" cellpadding="0" cellspacing="0" width="570"
                   style="border-collapse: collapse;" class="dark-bg-base">
                <tr>
                    <div style="text-align: center;margin-top: 25px; margin-bottom: 10px;"></div>
                </tr>
                <tr>
                    <td align="center" cellpadding="20">
                        <div style="border: 1px solid #c2c2c2; border-bottom: none; padding-bottom: 10px; border-top-left-radius: 3px; border-top-right-radius: 3px;">

                            <!--[if gte mso 9]>
                            <img src="{{ $logo ?? '' }}" alt="" width="400" border="0" align="middle" style="display:block;" />
                            <div style="mso-hide:all;">
                            <![endif]-->
                            <img src="{{ $logo ?? '' }}" alt="" width="400" style="margin-top: 40px; max-width: 200px; display: block; margin-left: auto; margin-right: auto;"/>
                            <!--[if gte mso 9]>
                            </div>
                            <![endif]-->

                        </div>
                    </td>
                </tr>
                <tr>
                    <td cellpadding="20">
                        <div style="border: 1px solid #c2c2c2; border-top: none; border-bottom: none; padding: 20px; text-align: center" id="content">
                                <div style="padding-top: 10px;"></div>

                                {{ $slot ?? '' }}
                                {!! $body ?? '' !!}

                                <div>
                                    <a href="#"
                                        style="display: inline-block;background-color: {{ $primary_color }}; color: #ffffff; text-transform: uppercase;letter-spacing: 2px; text-decoration: none; font-size: 13px; font-weight: 600;">
                                    </a>
                                </div>
                           </div>
                    </td>
                </tr>  
                
                <tr>
                  <td height="20">
                   <div style="border: 1px solid #c2c2c2; border-top: none; border-bottom: none; padding: 20px; text-align: center" id="content"> </div>
                 </td>
                </tr>

                <tr>
                    <td cellpadding="20" bgcolor="#f9f9f9">
                        <div class="dark-bg dark-text-white"
                             style="text-align: center; padding-top: 10px; padding-bottom: 25px; background-color: #f9f9f9; border: 1px solid #c2c2c2; border-top: none; border-bottom-color: #f9f9f9;">
                            @isset($signature)
                                <p style="font-size: 15px; color: #2e2e2e; font-family: 'roboto', Arial, Helvetica, sans-serif; font-weight: 400; margin-bottom: 30px;">
                                    {!! nl2br($signature) !!}
                                </p>
                            @endisset

                            @if(isset($company) && $company instanceof \App\Models\Company)
                                <p style="font-size: 15px; color: #2e2e2e; font-family: 'roboto', Arial, Helvetica, sans-serif; font-weight: 500; margin-bottom:0;">
                                    {{ $company->present()->name() }}</p>
                                <p style="font-size: 15px; color: #2e2e2e; font-family: 'roboto', Arial, Helvetica, sans-serif; font-weight: 400; margin-top: 5px;">
                                    <span>{{ $company->settings->phone }}</span>
                                    <span style="font-weight: 500"> {{ $company->settings->website }}</span>
                                </p>
                            @endif
                        </div>
                    </td>
                </tr>

                <tr>
                    <td bgcolor="#242424"  cellpadding="20">
                        <div class="dark-bg-base"
                             style="padding-top: 10px;padding-bottom: 10px; background-color: #242424; border: 1px solid #c2c2c2; border-top-color: #242424; border-bottom-color: #242424;">
                            @if(isset($company))
                                @if($company->account->isPaid())
                                    <p style="text-align: center; color: #ffffff; font-size: 10px;
                            font-family: Verdana, Geneva, Tahoma, sans-serif;">© {{ date('Y') }} {{ $company->present()->name() }}, All Rights Reserved</p>
                                @else
                                    <p style="text-align: center; color: #ffffff; font-size: 10px; font-family: Verdana, Geneva, Tahoma, sans-serif;">
                                        © {{ date('Y') }} Invoice Ninja, All Rights Reserved
                                    </p>
                                @endif
                            @else
                                <p style="text-align: center; color: #ffffff; font-size: 10px; font-family: Verdana, Geneva, Tahoma, sans-serif;">
                                    © {{ date('Y') }} Invoice Ninja, All Rights Reserved
                                </p>
                            @endif
                        </div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>

</html>