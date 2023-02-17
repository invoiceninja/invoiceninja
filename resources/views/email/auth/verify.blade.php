@component('email.template.admin', ['logo' => 'https://www.invoiceninja.com/wp-content/uploads/2015/10/logo-white-horizontal-1.png', 'settings' => $settings])
    <div class="center">
        <p>{{ ctrans('texts.confirmation_message') }}</p>

        <!-- <a href="{{ url("/user/confirm/{$user->confirmation_code}") }}" target="_blank" class="button">
            {{ ctrans('texts.confirm') }}
        </a> -->

        <table border="0" cellspacing="0" cellpadding="0" align="center">
            <tr style="border: 0 !important; ">
                <td class="new_button" style="padding: 12px 18px 12px 18px; border-radius:5px;" align="center"> 
                <a href="{{ url("/user/confirm/{$user->confirmation_code}") }}" target="_blank" style="border: 0 !important;font-size: 18px; font-family: Helvetica, Arial, sans-serif; color: #ffffff; text-decoration: none; display: inline-block;">
                    {{ ctrans('texts.confirm') }}
                </a>
                </td>
            </tr>
        </table>


    </div>
@endcomponent
