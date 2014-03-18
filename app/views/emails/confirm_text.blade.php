{{ Lang::get('confide::confide.email.account_confirmation.subject') }}

{{ Lang::get('confide::confide.email.account_confirmation.body') }}
{{{ URL::to("user/confirm/{$user->confirmation_code}") }}}

{{ Lang::get('confide::confide.email.account_confirmation.farewell') }}