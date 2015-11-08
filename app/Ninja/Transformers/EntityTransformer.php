<?php namespace App\Ninja\Transformers;

use App\Models\Account;
use League\Fractal\TransformerAbstract;

class EntityTransformer extends TransformerAbstract
{
    protected $account;

    public function __construct(Account $account)
    {
        $this->account = $account;
    }
}
