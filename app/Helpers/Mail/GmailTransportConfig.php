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


/**
 * GmailTransportConfig
 */
class GmailTransportConfig
{

	public function __invoke(User $user)
    {

// $transport = (new Swift_SmtpTransport('smtp.googlemail.com', 465, 'ssl'))
//   ->setUsername('YOUR_GMAIL_USERNAME')
//   ->setPassword('YOUR_GMAIL_PASSWORD')
// ;
// 
//		$transport = \Swift_SmtpTransport::newInstance($host, $port);
		// set encryption
		if (isset($encryption)) $transport->setEncryption($encryption);
		// set username and password
		if (isset($username))
		{
		    $transport->setUsername($username);
		    $transport->setPassword($password);
		}
 
// 
// 
// // Create the Transport


// // Create the Mailer using your created Transport
// $mailer = new Swift_Mailer($transport);

/********************* We may need to fetch a new token on behalf of the client ******************************/

        $query = [
            'email' => 'david@invoicninja.com',
            'oauth_provider_id'=>'google'
        ];

        $user = MultiDB::hasUser($query);

		$transport = (new Swift_SmtpTransport('smtp.gmail.com', 587, 'tls'))
		    ->setAuthMode('XOAUTH2')
		    ->setUsername($user->email)
		    ->setPassword($user->oauth_user_token);

		// set new swift mailer
		Mail::setSwiftMailer(new \Swift_Mailer($transport));


		Mail::to('david@romulus.com.au')
	    ->send('test');
    }


}










