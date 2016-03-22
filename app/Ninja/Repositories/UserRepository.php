<?php namespace App\Ninja\Repositories;

use DB;
use Utils;
use Session;
use App\Models\User;
use App\Ninja\Repositories\BaseRepository;

class UserRepository extends BaseRepository
{
    public function getClassName()
    {
        return 'App\Models\User';
    }

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

    public function save($data, $user)
    {
        $user->fill($data);
        $user->save();

        return $user;
    }
}
