<?php namespace App\Ninja\Transformers;

use App\Models\User;
use App\Models\Account;
use League\Fractal;
use League\Fractal\TransformerAbstract;
use League\Fractal\Resource\Item;

class UserAccountTransformer extends EntityTransformer
{
    protected $defaultIncludes = [
        'user'
    ];

    protected $tokenName;
    
    public function __construct(Account $account, $tokenName)
    {
        parent::__construct($account);

        $this->tokenName = $tokenName;
    }

    public function includeUser(User $user)
    {
        return $this->item($user, new UserTransformer($this->account));
    }

    public function transform(User $user)
    {
        return [
            'account_key' => $user->account->account_key,
            'name' => $user->account->present()->name,
            'token' => $user->account->getToken($this->tokenName),
            'default_url' => SITE_URL
        ];
    }
}