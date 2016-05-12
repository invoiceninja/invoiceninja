{!! Former::open($url)->method($method)->rules(array(
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'required',
            'description' => 'required',
            'company_name' => 'required',
            'tos_agree' => 'required',
        ))->addClass('warn-on-exit') !!}
{!! Former::populateField('company_name', $account->getDisplayName()) !!}
{!! Former::populateField('first_name', $user->first_name) !!}
{!! Former::populateField('last_name', $user->last_name) !!}
{!! Former::populateField('email', $user->email) !!}
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
        {!! Former::checkbox('tos_agree')->label(' ')->text(trans('texts.wepay_tos_agree',
                ['link'=>'<a href="https://go.wepay.com/terms-of-service-us" target="_blank">'.trans('texts.wepay_tos_link_text').'</a>']
            ))->value('true') !!}
        <center>
            {!! Button::primary(trans('texts.sign_up_with_wepay'))
                    ->submit()
                    ->large() !!}
            @if(isset($gateways))
                <br><br>
                <a href="#" id="show-other-providers">{{ trans('texts.use_another_provider') }}</a>
            @endif
        </center>
    </div>
</div>
<style>#other-providers{display:none}</style>
<input type="hidden" name="gateway_id" value="{{ GATEWAY_WEPAY }}">
{!! Former::close() !!}