@component('email.template.master', ['design' => 'dark', 'settings' => $settings, 'whitelabel' => $whitelabel])

@slot('header')
    @component('email.components.header', ['p' => $body, 'logo' => (strlen($settings->company_logo) > 1) ? url('') . $settings->company_logo : 'https://www.invoiceninja.com/wp-content/uploads/2019/01/InvoiceNinja-Logo-Round-300x300.png'])
        
        @if(isset($title))
        {{$title}}
        @endif

    @endcomponent

    @if($footer)
        @component('email.components.button', ['url' => $view_link])
            {{$view_text}}
        @endcomponent
    @endif


@endslot


@endcomponent
