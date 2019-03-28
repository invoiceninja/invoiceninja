<?php

namespace App\Transformers;

use App\Models\Account;
use App\Models\Payment;

/**
 * Class AccountTransformer.
 */
class AccountTransformer extends EntityTransformer
{

	/**
     * @SWG\Property(property="account_key", type="string", example="123456")
     */

    /**
     * @var array
     */
    protected $defaultIncludes = [
    ];

    /**
     * @var array
     */
    protected $availableIncludes = [
        'default_company',
    ];


    /**
     * @param Account $account
     *
     * @throws \Laracasts\Presenter\Exceptions\PresenterException
     *
     * @return array
     */
    public function transform(Account $account)
    {
        return [
            'id' => $account->id,
        ];
    }

    public function includeDefaultCompany(Account $account)
    {
        $transformer = new CompanyTransformer($this->serializer);

        return $this->includeItem($account->default_company, $transformer, Company::class);
    }
}
