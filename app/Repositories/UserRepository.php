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

        return $user;
        
	}

}