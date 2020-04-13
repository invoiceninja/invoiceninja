@component('email.template.master', ['design' => $design])

@slot('header')
    @component('email.components.header', ['p' => '', 'logo' => 'https://www.invoiceninja.com/wp-content/uploads/2019/01/InvoiceNinja-Logo-Round-300x300.png'])
        
        @if(isset($title))
        {{$title}}
        @endif

    @endcomponent

{!! $body !!}

    @if($footer)
        @component('email.components.button', ['url' => $view_link])
            {{$view_text}}
        @endcomponent
    @endif


@endslot


@endcomponent
