<?php namespace App\Ninja\Transformers;

use App\Models\User;
use App\Models\Account;

class UserAccountTransformer extends EntityTransformer
{
    protected $defaultIncludes = [
        'user'
    ];

    protected $tokenName;

    public function __construct(Account $account, $serializer, $tokenName)
    {
        parent::__construct($account, $serializer);

        $this->tokenName = $tokenName;
    }

    public function includeUser(User $user)
    {
        $transformer = new UserTransformer($this->account, $this->serializer);
        return $this->includeItem($user, $transformer, 'user');
    }

    public function transform(User $user)
    {
        return [
            'account_key' => $this->account->account_key,
            'name' => $this->account->present()->name,
            'token' => $this->account->getToken($user->id, $this->tokenName),
            'default_url' => SITE_URL,
            'plan' => $this->account->company->plan,
            'logo' => $this->account->logo,
            'logo_url' => $this->account->getLogoURL(),
        ];
    }
}
