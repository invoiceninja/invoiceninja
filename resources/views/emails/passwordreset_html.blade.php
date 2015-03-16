{{ trans('texts.email_salutation', ['name' => $user->username]) }} <p/>

{{ trans('texts.reset_password') }} <br/> 
{{{ (Confide::checkAction('UserController@reset_password', array($token))) ? : URL::to('user/reset/'.$token)  }}}<p/>

{{ trans('texts.email_signature') }} <br/>
{{ trans('texts.email_from') }} <p/>

{{ trans('texts.reset_password_footer') }} <p/>