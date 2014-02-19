Hi there! {{ $user->username }}<p/>

You can reset your account password by clicking the following link {{{ (Confide::checkAction('UserController@reset_password', array($token))) ? : URL::to('user/reset/'.$token)  }}}<p/>

Regards, <br/>
The InvoiceNinja Team <p/>

If you did not request this password reset please email our support: admin@invoiceninja.com <p/>
