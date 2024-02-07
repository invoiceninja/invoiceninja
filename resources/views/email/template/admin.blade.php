@php
    $primary_color = isset($settings) ? $settings->primary_color : '#4caf50';
    $email_alignment = isset($settings) && $settings?->email_alignment ? $settings->email_alignment : 'center';
@endphp

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html dir="ltr" lang="en">

  <head>
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type" />
    <meta name="color-scheme" content="light dark" />
    <meta name="supported-color-schemes" content="light dark" />
    <style>
        @if(isset($settings) && $settings->email_style === 'dark')
            .background-color {
                background-color: #f4f3f4;
            }

            .foreground-color {
                background-color: #fff;
            }

            .primary-text-color {
                color: black;
            }
        @else
            .background-color {
                background-color: #09090b;
            }

            .foreground-color {
                background-color: #18181b;
            }

            .primary-text-color {
                color: white;
            }
        @endif

        .background-primary-color {
            background-color: {{ $primary_color }};
        }

        #content .button {
            display: inline-block;
            background-color: {{ $primary_color }};
            color: #ffffff;
            text-transform: uppercase;
            letter-spacing: 2px;
            text-decoration: none;
            font-size: 13px;
            padding: 15px 70px;
            font-weight: 600;
            margin-bottom: 30px;
        }

        #content {
            text-align: {{ $email_alignment }};
        }
    </style>
  </head>
  <div style="padding:10px;margin-top:-10px;margin-right:-8px;margin-left:-8px" class="background-primary-color"></div>

  <body class="background-color" style="font-family:system-ui">
    <table align="center" width="100%" border="0" cellPadding="0" cellSpacing="0" role="presentation" style="max-width:37.5em;padding-top:45px">
      <tbody>
        <tr style="width:100%">
          <td>
            @if($logo && strpos($logo, 'blank.png') === false)
            <table align="center" width="100%" border="0" cellPadding="0" cellSpacing="0" role="presentation" style="max-width:37.5em;display:flex;align-items:center;justify-content:center">
              <tbody>
                <tr style="width:100%">
                  <td><a href="#" style="color:#067df7;text-decoration:none" target="_blank"><img class="company-logo" src="{{ $logo ?? '' }}" style="display:block;outline:none;border:none;text-decoration:none;max-height:65px" /></a></td>
                </tr>
              </tbody>
            </table>
            @endif
            <table align="center" width="100%" class="foreground-color primary-text-color" border="0" cellPadding="0" cellSpacing="0" role="presentation" style="max-width:37.5em;margin-top:20px;padding:10px 25px;border-radius:8px">
              <tbody>
                <tr style="width:100%">
                  <td id="content">
                    {{ $slot }}
                  </td>
                </tr>
              </tbody>
            </table>
            <table align="center" width="100%" border="0" cellPadding="0" cellSpacing="0" role="presentation" style="max-width:37.5em;background-color:black;margin-top:10px;padding:10px 25px;border-radius:8px;color:white">
              <tbody>
                <tr style="width:100%">
                  <td>
                    <table align="center" width="100%" border="0" cellPadding="0" cellSpacing="0" role="presentation">
                      <tbody style="width:100%">
                        <tr style="width:100%">
                          <p style="font-size:20px;line-height:24px;margin:16px 0;text-align:center">Questions? We&#x27;re here to help!</p>
                        </tr>
                      </tbody>
                    </table>
                    <table align="center" width="100%" border="0" cellPadding="0" cellSpacing="0" role="presentation" style="margin-top:10px;margin-bottom:14px">
                      <tbody style="width:100%">
                        <tr style="width:100%">
                          <td data-id="__react-email-column" style="text-align:center"><a href="https://forum.invoiceninja.com/" style="color:white;text-decoration:none;display:inline-flex;justify-items:center;align-items:center" target="_blank"><img src="{{ asset('images/emails/forum_FILL0_wght400_GRAD0_opsz24.png') }}" style="display:block;outline:none;border:none;text-decoration:none;filter:invert(1)" /><span style="margin-left:5px">Forums</span></a></td>
                          <td data-id="__react-email-column" style="text-align:center"><a href="http://slack.invoiceninja.com/" style="color:white;text-decoration:none;display:inline-flex;justify-items:center;align-items:center" target="_blank"><img src="{{ asset('images/emails/slack-icon-black.png') }}" style="display:block;outline:none;border:none;text-decoration:none;filter:invert(1);max-height:20px" /><span style="margin-left:5px">Slack</span></a></td>
                          <td data-id="__react-email-column" style="text-align:center"><a href="https://www.invoiceninja.com/contact/" style="color:white;text-decoration:none;display:inline-flex;justify-items:center;align-items:center" target="_blank"><img src="{{ asset('images/emails/mail_FILL0_wght400_GRAD0_opsz24.png') }}" style="display:block;outline:none;border:none;text-decoration:none;filter:invert(1)" /><span style="margin-left:5px">E-mail</span></a></td>
                          <td data-id="__react-email-column" style="text-align:center"><a href="https://invoiceninja.github.io/" style="color:white;text-decoration:none;display:inline-flex;justify-items:center;align-items:center" target="_blank"><img src="{{ asset('images/emails/description_FILL0_wght400_GRAD0_opsz24.png') }}" style="display:block;outline:none;border:none;text-decoration:none;filter:invert(1)" /><span style="margin-left:5px">Support docs</span></a></td>
                        </tr>
                      </tbody>
                    </table>
                  </td>
                </tr>
              </tbody>
            </table>
            <table align="center" width="100%" border="0" cellPadding="0" cellSpacing="0" role="presentation">
              <tbody style="width:100%">
                <tr style="width:100%">
                  <p class="primary-text-color" style="font-size:14px;line-height:24px;margin:16px 0;text-align:center">© {{ date('Y') }} Invoice Ninja, All Rights Reserved</p>
                </tr>
              </tbody>
            </table>
          </td>
        </tr>
      </tbody>
    </table>
  </body>

</html>
