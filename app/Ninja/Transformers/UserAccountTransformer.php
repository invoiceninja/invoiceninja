<?php namespace App\Ninja\Transformers;

use App\Models\User;
use League\Fractal;
use League\Fractal\TransformerAbstract;
use League\Fractal\Resource\Item;

class UserAccountTransformer extends TransformerAbstract
{
    protected $defaultIncludes = [
        'user'
    ];

    protected $tokenName;
    
    public function __construct($tokenName)
    {
        $this->tokenName = $tokenName;
    }

    public function includeUser(User $user)
    {
        return $this->item($user, new UserTransformer);
    }

    public function transform(User $user)
    {
        return [
            'account_key' => $user->account->account_key,
            'name' => $user->account->present()->name,
            'token' => $user->account->getToken($this->tokenName),
        ];
    }
}