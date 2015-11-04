<?php namespace App\Ninja\Transformers;

use App\Models\User;
use League\Fractal;
use League\Fractal\TransformerAbstract;

class UserAccountTransformer extends TransformerAbstract
{
    protected $tokenName;

    public function __construct($tokenName)
    {
        $this->tokenName = $tokenName;
    }

    public function transform(User $user)
    {
        return [
            'account_key' => $user->account->account_key,
            'name' => $user->account->name,
            'token' => $user->account->getToken($this->tokenName),
            'user' => [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
            ]
        ];
    }
}