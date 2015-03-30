{{ trans('texts.email_salutation', ['name' => $user->username]) }} <p/>

{{ trans('texts.reset_password') }} <br/> 
{!! url('password/reset/'.$token) !!}<p/>

{{ trans('texts.email_signature') }} <br/>
{{ trans('texts.email_from') }} <p/>

{{ trans('texts.reset_password_footer') }} <p/>