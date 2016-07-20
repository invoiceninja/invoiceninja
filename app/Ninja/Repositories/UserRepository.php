<?php

namespace App\Ninja\Repositories;

use App\Models\User;
use DB;
use Session;

/**
 * Class UserRepository
 */
class UserRepository extends BaseRepository
{
    /**
     * @return string
     */
    public function getClassName()
    {
        return 'App\Models\User';
    }

    /**
     * @param $accountId
     *
     * @return $this
     */
    public function find($accountId)
    {
        $query = DB::table('users')
                  ->where('users.account_id', '=', $accountId);

        if (!Session::get('show_trash:user')) {
            $query->where('users.deleted_at', '=', null);
        }

        $query->select('users.public_id', 'users.first_name', 'users.last_name', 'users.email', 'users.confirmed', 'users.public_id', 'users.deleted_at', 'users.is_admin', 'users.permissions');

        return $query;
    }

    /**
     * @param array $data
     * @param User $user
     * 
     * @return User
     */
    public function save(array $data, User $user)
    {
        $user->fill($data);
        $user->save();

        return $user;
    }
}
