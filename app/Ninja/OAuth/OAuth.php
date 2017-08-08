<?php namespace App\Ninja\OAuth;

use App\Models\User;

class OAuth {

    private $providerInstance;

    public function __construct()
    {
    }

    public function getProvider($provider)
    {
        switch ($provider)
        {
            case 'google';
                $this->providerInstance = new Providers\Google();
                return $this;

            default:
                return null;
                break;
        }
    }

    public function getTokenResponse($token)
    {
        $email = null;
        $user = null;

        if($this->providerInstance)
            $user = User::where('email', $this->providerInstance->getTokenResponse($token))->first();

        if ($user)
            return $user;
        else
            return false;

    }


}
?>