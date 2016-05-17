{!! Former::open($url)->method($method)->rules(array(
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'required',
            'description' => 'required',
            'company_name' => 'required',
            'tos_agree' => 'required',
            'country' => 'required',
        ))->addClass('warn-on-exit') !!}
{!! Former::populateField('company_name', $account->getDisplayName()) !!}
@if($account->country)
    {!! Former::populateField('country', $account->country->iso_3166_2) !!}
@endif
{!! Former::populateField('first_name', $user->first_name) !!}
{!! Former::populateField('last_name', $user->last_name) !!}
{!! Former::populateField('email', $user->email) !!}
{!! Former::populateField('show_address', 1) !!}
{!! Former::populateField('update_address', 1) !!}
{!! Former::populateField('token_billing_type_id', $account->token_billing_type_id) !!}
<div class="panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title">{!! trans('texts.online_payments') !!}</h3>
    </div>
    <div class="panel-body form-padding-right">
        {!! Former::text('first_name') !!}
        {!! Former::text('last_name') !!}
        {!! Former::text('email') !!}
        {!! Former::text('company_name')->help('wepay_company_name_help')->maxlength(255) !!}
        {!! Former::text('description')->help('wepay_description_help') !!}
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
        {!! Former::select('token_billing_type_id')
                ->options($tokenBillingOptions)
                ->help(trans('texts.token_billing_help')) !!}
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
        {!! Former::checkbox('tos_agree')->label(' ')->text(trans('texts.wepay_tos_agree',
                ['link'=>'<a id="wepay-tos-link" href="https://go.wepay.com/terms-of-service-us" target="_blank">'.trans('texts.wepay_tos_link_text').'</a>']
            ))->value('true') !!}
        <center>
            {!! Button::primary(trans('texts.sign_up_with_wepay'))
                    ->submit()
                    ->large() !!}
            @if(isset($gateways))
                <br><br>
                <a href="javascript::void" id="show-other-providers">{{ trans('texts.use_another_provider') }}</a>
            @endif
        </center>
    </div>
</div>
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
        $('#wepay-accept-debit').toggle(country == 'CA');
        $('#wepay-tos-link').attr('href', 'https://go.wepay.com/terms-of-service-' + country.toLowerCase());
    }
    handleCountryChange();
})
</script>
<input type="hidden" name="gateway_id" value="{{ GATEWAY_WEPAY }}">
{!! Former::close() !!}