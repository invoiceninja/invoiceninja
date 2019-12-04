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

use App\Libraries\MultiDB;
use App\Models\User;
use App\Providers\MailServiceProvider;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

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

		Config::set('mail.driver', 'gmail');
		Config::set('services.gmail.token', $user->oauth_user_token);
		(new MailServiceProvider(app()))->register();   


	    Mail::to('david@romulus.com.au')
	    ->send(new SupportMessageSent('a cool message'));
    }


}











