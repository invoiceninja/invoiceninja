<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Repositories;

use App\Models\User;
use App\Models\CompanyUser;
use App\Factory\CompanyUserFactory;
use Illuminate\Http\Request;

/**
 * UserRepository
 */
class UserRepository extends BaseRepository
{

    public function __construct()
    {


    }

    /**
     * Gets the class name.
     *
     * @return     string The class name.
     */
    public function getClassName()
    {

        return User::class;

    }

	/**
     * Saves the user and its contacts
     *
     * @param      array                         $data    The data
     * @param      \App\Models\user              $user  The user
     *
     * @return     user|\App\Models\user|null  user Object
     */
    public function save(array $data, User $user) : ?user
	{

        $user->fill($data);
        $user->save();

        if($data['company_user'])
        {
            
            $company = auth()->user()->company();
            $account_id = $company->account->id;

            $cu = CompanyUser::whereUserId($user->id)->whereCompanyId($company->id)->first();


            if(!$cu){
                //$cu = CompanyUserFactory::create($user->id, $company->id, $account_id);
                $data['company_user']['account_id'] = $account_id;
                
                $user->companies()->attach($company->id, $data['company_user']);

            }
            
        }

        return $user;
        
	}

}