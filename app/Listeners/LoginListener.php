<?php
namespace App\Listeners;
use \Aacotroneo\Saml2\Events\Saml2LoginEvent;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Ninja\Repositories;

class LoginListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }
    /**
     * Handle the event.
     *
     * @param  Saml2LoginEvent  $event
     * @return void
     */
    public function handle(Saml2LoginEvent $event)
    {
        $user = $event->getSaml2User();
		$userData = [
			'id' => $user->getUserId(),
			'attributes' => $user->getAttributes(),
            'assertion' => $user->getRawSamlAssertion(),
            'sessionIndex' => $user->getSessionIndex(),
            'nameId' => $user->getNameId()
        ];

	$emailAttribute = config('saml2_settings.emailAttribute');
	$firstnameAttribute = config('saml2_settings.firstnameAttribute');
	$lastnameAttribute = config('saml2_settings.lastnameAttribute');
	$givennameAttribute = config('saml2_settings.givennameAttribute');

		//check if email already exists and fetch user
		$user = \App\Models\User::where('email', $userData['attributes'][$emailAttribute][0])->first();

		if($user === null)
		{

			if($givennameAttribute != '')
			{
                                $namedata = explode(' ', $userData['attributes'][$givennameAttribute][0]);
                                if(count($namedata) > 1)
                                {
                                        $firstname = $namedata[0];
                                        $lastname = $namedata[1];
                                }
                                else
                                {
					$firstname = '';
                                        $lastname = $namedata[0];
                                }
			}
			else
			{
                                $firstname = $userData['attributes'][$firstnameAttribute][0];
                                $lastname = $userData['attributes'][$lastnameAttribute][0];
			}

                        $email = $userData['attributes'][$emailAttribute][0];
                        $password = bcrypt(str_random(8));

			$account = app('App\Ninja\Repositories\AccountRepository')->create($firstname, $lastname, $email, $password);
			$user = \App\Models\User::where('email', $userData['attributes'][$emailAttribute][0])->first();
			$user->confirmed = true;
			$user->registered = true;
			$user->save();
		}

/*		
		//if email doesn't exist, create new user
		if($user === null)
		{		
			$user = new \App\Models\User;
			if($givennameAttribute != '')
			{
				$namedata = explode(' ', $userData['attributes'][$givennameAttribute][0]);
				if(count($namedata) > 1)
				{
					$user->first_name = $namedata[0];
					$user->last_name = $namedata[1];
				}
				else
				{
					$user->last_name = $namedata[0];
				}
			}
			else
			{
				$user->first_name = $userData['attributes'][$firstnameAttribute][0];
				$user->last_name = $userData['attributes'][$lastnameAttribute][0];
			}
			$user->email = $userData['attributes'][$emailAttribute][0];
			$user->password = bcrypt(str_random(8));
			$user->save();
		}
*/
        //insert sessionIndex and nameId into session
        session(['sessionIndex' => $userData['sessionIndex']]);
        session(['nameId' => $userData['nameId']]);
		//login user
		\Auth::login($user);
    }
}
