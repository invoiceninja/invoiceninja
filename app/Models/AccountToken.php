<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\LookupAccountToken;

/**
 * Class AccountToken.
 */
class AccountToken extends EntityModel
{
    use SoftDeletes;
    /**
     * @var array
     */
    protected $dates = ['deleted_at'];

    /**
     * @return mixed
     */
    public function getEntityType()
    {
        return ENTITY_TOKEN;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function account()
    {
        return $this->belongsTo('App\Models\Account');
    }

    /**
     * @return mixed
     */
    public function user()
    {
        return $this->belongsTo('App\Models\User')->withTrashed();
    }
}

AccountToken::creating(function ($token)
{
    LookupAccountToken::createNew($token->account->account_key, [
        'token' => $token->token,
    ]);
});

AccountToken::deleted(function ($token)
{
    if ($token->forceDeleting) {
        LookupAccountToken::deleteWhere([
            'token' => $token->token
        ]);
    }
});
