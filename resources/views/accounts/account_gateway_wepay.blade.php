@extends('header')

@section('content')
    @parent

    @include('accounts.nav', ['selected' => ACCOUNT_PAYMENTS])

    {!! Former::open($url)->method($method)->rules(array(
                'first_name' => 'required',
                'last_name' => 'required',
                'email' => 'required',
                'company_name' => 'required',
                'tos_agree' => 'required',
                'country' => 'required',
            ))->addClass('warn-on-exit') !!}

    {!! Former::populateField('company_name', $account->getDisplayName()) !!}

    @if ($account->country)
        {!! Former::populateField('country', $account->country->iso_3166_2) !!}
    @endif

    {!! Former::populateField('first_name', $user->first_name) !!}
    {!! Former::populateField('last_name', $user->last_name) !!}
    {!! Former::populateField('email', $user->email) !!}
    {!! Former::populateField('show_address', 1) !!}
    {!! Former::populateField('update_address', 1) !!}

    @if (WEPAY_ENABLE_CANADA)
        {!! Former::populateField('country', 'US') !!}
    @endif

    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">{!! trans('texts.online_payments') !!}</h3>
        </div>
        <div class="panel-body form-padding-right">
            {!! Former::text('first_name') !!}
            {!! Former::text('last_name') !!}
            {!! Former::text('email') !!}
            {!! Former::text('company_name')->help('wepay_company_name_help')->maxlength(255) !!}

            @if (WEPAY_ENABLE_CANADA)
                <div id="wepay-country">
                {!! Former::radios('country')
                        ->radios([
                            trans('texts.united_states') => ['value' => 'US'],
                            trans('texts.canada') => ['value' => 'CA'],
                        ]) !!}
                </div>
                <div id="wepay-accept-debit">
                {!! Former::checkbox('debit_cards')
                        ->text(trans('texts.accept_debit_cards')) !!}
                </div>
            @endif

            {!! Former::checkbox('show_address')
                ->label(trans('texts.billing_address'))
                ->text(trans('texts.show_address_help')) !!}
            {!! Former::checkbox('update_address')
                    ->label(' ')
                    ->text(trans('texts.update_address_help')) !!}
            {!! Former::checkboxes('creditCardTypes[]')
                    ->label('Accepted Credit Cards')
                    ->checkboxes($creditCardTypes)
                    ->class('creditcard-types') !!}
            {!! Former::checkbox('enable_ach')
                    ->label(trans('texts.ach'))
                    ->text(trans('texts.enable_ach')) !!}

            {!! Former::checkbox('tos_agree')->label(' ')->text(trans('texts.wepay_tos_agree',
                    ['link'=>'<a id="wepay-tos-link" href="https://go.wepay.com/terms-of-service-us" target="_blank">'.trans('texts.wepay_tos_link_text').'</a>']
                ))->value('true')
                  ->inlineHelp('standard_fees_apply') !!}
        </div>
        </div>

        <br/>
        <center>
            {!! Button::normal(trans('texts.use_another_provider'))->large()->asLinkTo(URL::to('/gateways/create?other_providers=true')) !!}
            {!! Button::success(trans('texts.sign_up_with_wepay'))->submit()->large() !!}
        </center>


    <style>
        #other-providers{display:none}
        #wepay-country .radio{display:inline-block;padding-right:15px}
        #wepay-country .radio label{padding-left:0}
    </style>

    <script type="text/javascript">
        $(function(){
            $('#wepay-country input').change(handleCountryChange)
            function handleCountryChange(){
                var country = $('#wepay-country input:checked').val();
                if(country) {
                    $('#wepay-accept-debit').toggle(country == 'CA');
                    $('#wepay-tos-link').attr('href', 'https://go.wepay.com/terms-of-service-' + country.toLowerCase());
                }
            }
            handleCountryChange();
        })
    </script>

    <input type="hidden" name="primary_gateway_id" value="{{ GATEWAY_WEPAY }}">
    {!! Former::close() !!}

@stop
