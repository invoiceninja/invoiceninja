<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Helpers\Mail;

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use App\Libraries\MultiDB;

/**
 * GmailTransportConfig
 */
class GmailTransportConfig
{

namespace App\Helpers\Mail;

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use App\Libraries\MultiDB;
use App\Mail\SupportMessageSent;


/**
 * GmailTransportConfig
 */
class GmailTransportConfig
{

    public function test()
    {
/********************* We may need to fetch a new token on behalf of the client ******************************/
		$query = [
		    'email' => 'david@invoiceninja.com',
		];

	   	$user = MultiDB::hasUser($query);

	    $transport = (new \Swift_SmtpTransport('smtp.gmail.com', 587, 'tls'))
	        ->setAuthMode('XOAUTH2')
	        ->setUsername($user->email)
	        ->setPassword($user->oauth_user_token);

	    // set new swift mailer
	    Mail::setSwiftMailer(new \Swift_Mailer($transport));


	    Mail::to('david@romulus.com.au')
	    ->send(new SupportMessageSent('a cool message'));
    }


}











