<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xmlns="http://www.w3.org/1999/xhtml" lang="{{ App::getLocale() }}">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <!--[if !mso]><!-- -->
    @if($fontsUrl = Utils::getAccountFontsUrl())
    <link href="{{ $fontsUrl }}" rel="stylesheet" type="text/css" />
    @endif
    <!--<![endif]-->
</head>
<body style="color: #000000;{!! isset($account) ? $account->getBodyFontCss() : '' !!}font-size: 12px; -webkit-text-size-adjust: none; -ms-text-size-adjust: none; background: #F4F5F5; margin: 0; padding: 0;" 
    alink="#FF0000" link="#FF0000" bgcolor="#F4F5F5" text="#000000" yahoo="fix">
    @yield('markup')

    <style type="text/css">
        .footer a:visited {
            font-weight: bold; font-size: 10px; color: #A7A6A6; text-decoration: none;
        }
        span.yshortcuts:hover {
            color: #000; background-color: none; border: none;
        }
        span.yshortcuts:active {
            color: #000; background-color: none; border: none;
        }
        span.yshortcuts:focus {
            color: #000; background-color: none; border: none;
        }
        a:visited {
            color: #19BB40; text-decoration: none;
        }
        a:focus {
            color: #19BB40; text-decoration: underline;
        }
        a:hover {
            color: #19BB40; text-decoration: underline;
        }
        @media only screen and (max-device-width: 480px) {
            body[yahoo] #container1 {
                display: block !important;
            }
            body[yahoo] p {
                font-size: 10px;
            }
        }
        @media only screen and (min-device-width: 768px) and (max-device-width: 1024px) {
            body[yahoo] #container1 {
                display: block !important;
            }
            body[yahoo] p {
                font-size: 12px;
            }
        }
    </style> 

    <div id="body_style" style="{!! isset($account) ? $account->getBodyFontCss() : '' !!};color: #2E2B2B; font-size: 16px; 
        background: #F4F5F5; padding: 0px 0px 15px;"> 

        <table cellpadding="0" cellspacing="0" border="0" bgcolor="#FFFFFF" width="580" align="center">

            @yield('content')
            
            <tr class="footer" style="text-align: center; color: #a7a6a6;" align="center">
                <td bgcolor="#F4F5F5" 
                    style="border-collapse: collapse; padding-top: 32px;">
                    @yield('footer')
                </td>
            </tr>

        </table>
    </div> 

</body>

</html>