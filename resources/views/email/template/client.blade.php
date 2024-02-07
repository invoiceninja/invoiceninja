@php
    $primary_color = isset($settings) ? $settings->primary_color : '#4caf50';
    $email_alignment = isset($settings->email_alignment) ? $settings->email_alignment : 'center';
@endphp

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html dir="ltr" lang="en">

  <head>
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type" />
    <meta name="color-scheme" content="light dark" />
    <meta name="supported-color-schemes" content="light dark" />
    <style>
      .background-primary-color {
        background-color: {{ $primary_color }};
      }

      @if(isset($settings) && $settings->email_style === 'dark')
        .border-color {
          border-color: #171717;
        }

        .background-color {
          background-color: #09090b;
        }

        .foreground-color {
          background-color: #18181b;
        }

        .primary-text-color {
          color: white;
        }
      @else
        .border-color {
            border-color: #000;
        }

        .background-color {
            background-color: #f4f3f4;
        }

        .foreground-color {
            background-color: #fff;
        }

        .primary-text-color {
            color: black;
        }
      @endif

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
        margin-bottom: 5px;
        margin-top: 10px;
    }

    .stamp {
        transform: rotate(12deg);
        color: #555;
        font-size: 3rem;
        font-weight: 700;
        border: 0.25rem solid #555;
        text-transform: uppercase;
        border-radius: 1rem;
        font-family: 'Courier';
        mix-blend-mode: multiply;
        z-index:200 !important;
        position: relative;
    }

    .is-paid {
        color:  #D23;
        border: 1rem double  #D23;
        transform: rotate(-5deg);
        font-size: 6rem;
        font-family: "Open sans", Helvetica, Arial, sans-serif;
        border-radius: 0;
        padding: 0.5rem;
        opacity: 0.2;
        z-index:200 !important;
        position: relative;
    }

    .company-logo {}
    </style>
  </head>

  <body class="background-color" style="font-family:system-ui">
    <table align="center" width="100%" class="border-color" border="0" cellPadding="0" cellSpacing="0" role="presentation" style="max-width:37.5em;margin-top:30px;margin-bottom:30px;border:1px solid;border-radius:5px">
      <tbody>
        <tr style="width:100%">
          <td>
            <table align="center" width="100%" class="background-primary-color" style="padding:45px 0px;">
              <tr>
                <th>
                  @if($logo && strpos($logo, 'blank.png') === false)
                    <div style="margin:0 auto;text-align:center"><img class="company-logo" src="{{ $logo ?? '' }}" style="display:block;outline:none;border:none;text-decoration:none;margin:0 auto;max-height:65px" /></div>
                  @endif
                </th>
              </tr>
            </table>
            <table align="center" width="100%" class="foreground-color primary-text-color" border="0" cellPadding="0" cellSpacing="0" role="presentation" style="max-width:37.5em;padding:10px 25px">
              <tbody>
                <tr style="width:100%">
                  <td id="content">
                    {{ $slot ?? '' }}
                    {!! $body ?? '' !!}

                    @isset($links)
                        <div>
                            <ul style="list-style-type: none;">
                            @foreach($links as $link)
                                    <li>{!! $link ?? '' !!} <img height="15px" src="{{ asset('images/svg/dark/file.svg') }}"></li>
                            @endforeach
                            </ul>
                        </div>
                    @endisset
                  </td>
                </tr>
              </tbody>
            </table>
            <table align="center" width="100%" class="background-color primary-text-color" border="0" cellPadding="0" cellSpacing="0" role="presentation" style="max-width:37.5em">
              <tbody>
                <tr style="width:100%">
                  <td>
                    <div style="text-align:center" class="primary-text-color">
                      @isset($signature)
                        {!! nl2br($signature) !!}
                      @endisset

                      @if(isset($company) && $company instanceof \App\Models\Company && $company->getSetting('show_email_footer'))
                        <p style="font-size: 15px; color: #2e2e2e; font-family: 'roboto', Arial, Helvetica, sans-serif; font-weight: 500; margin-bottom:0;">
                            {{ $company->present()->name() }}</p>
                        <p style="font-size: 15px; color: #2e2e2e; font-family: 'roboto', Arial, Helvetica, sans-serif; font-weight: 400; margin-top: 5px;">
                            <p>{{ $company->settings->phone }}</p>
                            <p style="font-weight: 500"> {{ $company->settings->website }}</p>
                        </p>
                      @endif
                    </div>
                  </td>
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
          <p class="primary-text-color" style="font-size:14px;line-height:24px;margin:16px 0;text-align:center">
            @isset($company)
                @if($company->account->isPaid())
                    © {{ date('Y') }} {{ $company->present()->name() }}, All Rights Reserved
                @else
                    © {{ date('Y') }} Invoice Ninja, All Rights Reserved
                @endif
            @else
                © {{ date('Y') }} Invoice Ninja, All Rights Reserved
            @endisset
          </p>
        </tr>
      </tbody>
    </table>

    @if(isset($email_preferences) && $email_preferences)
    <table align="center" width="100%" border="0" cellPadding="0" cellSpacing="0" role="presentation">
      <tbody style="width:100%">
        <tr style="width:100%">
          <div class="primary-text-color" style="font-size:14px;line-height:24px;margin:16px 0;text-align:center">
            <a href="{{ $email_preferences }}">
                <p style="text-align: center; color: #ffffff; font-size: 10px; font-family: Verdana, Geneva, Tahoma, sans-serif;">
                    {{ ctrans('texts.email_preferences') }}
                </p>
            </a>
          </div>
        </tr>
      </tbody>
    </table>
    @endif
  </body>

</html>