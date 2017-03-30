<?php namespace App\Ninja\OAuth;

use App\Models\User;

class OAuth {

    protected $provider;

    public function __construct($provider)
    {
        $this->provider = $provider;
    }

    public function getProvider()
    {
        switch ($this->provider)
        {
            case 'google';
                $this->provider = new Providers\Google();
                break;
            default:
                break;
        }
    }

    public function getTokenResponse($token)
    {
        $email = $this->provider->getTokenResponse($token);

        if($email)
            $user = User::where('email', $email)->first();

        if ($user)
            return $user;
        else
            return false;


    }


}
?>