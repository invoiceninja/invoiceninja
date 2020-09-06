<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Helpers\Mail;

use App\Libraries\MultiDB;
use App\Mail\SupportMessageSent;
use App\Models\User;
use App\Providers\MailServiceProvider;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Laravel\Socialite\Facades\Socialite;

/**
 * GmailTransportConfig.
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
        // $oauth_user = Socialite::driver('google')->stateless()->userFromToken($user->oauth_user_token);

        // $user->oauth_user_token = $oauth_user->refreshToken;
        // $user->save();

        Config::set('mail.driver', 'gmail');
        Config::set('services.gmail.token', $user->oauth_user_token);
        (new MailServiceProvider(app()))->register();

        Mail::to('david@romulus.com.au')
        ->send(new SupportMessageSent('a cool message'));
    }
}
