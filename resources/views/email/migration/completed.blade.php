@component('email.template.admin', ['logo' => 'https://invoicing.co/images/invoiceninja-black-logo-2.png', 'settings' => $settings])
    <div class="center">
        <h1>{{ ctrans('texts.migration_completed')}}</h1>
        <p>{{ ctrans('texts.migration_completed_description')}}</p>
<!-- 
        <a href="{{ url('/') }}" target="_blank" class="button">
            {{ ctrans('texts.account_login')}}
        </a> -->
        <table border="0" cellspacing="0" cellpadding="0" align="center">
            <tr style="border: 0 !important; ">
                <td class="new_button" style="padding: 12px 18px 12px 18px; border-radius:5px;" align="center"> 
                <a href="{{ url('/') }}") }}" target="_blank" style="border: 0 !important;font-size: 18px; font-family: Helvetica, Arial, sans-serif; color: #ffffff; text-decoration: none; display: inline-block;">
                    {{ ctrans('texts.account_login') }}        
                </a>
                </td>
            </tr>
        </table>


        <p>{{ ctrans('texts.email_signature')}}<br/> {{ ctrans('texts.email_from') }}</p>
    </div>
@endcomponent
