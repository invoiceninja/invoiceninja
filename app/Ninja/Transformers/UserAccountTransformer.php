<?php namespace App\Ninja\Transformers;

use App\Models\User;
use League\Fractal;
use League\Fractal\TransformerAbstract;

class UserAccountTransformer extends TransformerAbstract
{
    protected $defaultIncludes = [
        'account_tokens'
    ];

    public function includeAccountTokens($user)
    {
        $tokens = $user->account->account_tokens->filter(function($token) use ($user) {
            return $token->user_id === $user->id;
        });

        return $this->collection($tokens, new AccountTokenTransformer);
    }

    public function transform(User $user)
    {
        return [
            'account_key' => $user->account->account_key,
            'name' => $user->account->name,
            'user' => [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
            ]
        ];
    }
}