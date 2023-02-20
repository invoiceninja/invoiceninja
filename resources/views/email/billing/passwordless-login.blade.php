@component('email.template.client', ['logo' => $logo, 'settings' => $settings, 'company' => $company])
    <div class="center">
        <h1>{{ ctrans('texts.login_link_requested_label') }}</h1>
        <p>{{ ctrans('texts.login_link_requested') }}</p>

        <!-- <a href="{{ $url }}" target="_blank" class="button"> {{ ctrans('texts.login')}}</a> -->

        <table border="0" cellspacing="0" cellpadding="0" align="center">
            <tr style="border: 0 !important; ">
                <td class="new_button" style="padding: 12px 18px 12px 18px; border-radius:5px;" align="center"> 
                <a href="{{ $url }}") }}" target="_blank" style="border: 0 !important;font-size: 18px; font-family: Helvetica, Arial, sans-serif; color: #ffffff; text-decoration: none; display: inline-block;">
                    {{ ctrans('texts.login')}}                
                </a>
                </td>
            </tr>
        </table>

    </div>
@endcomponent
