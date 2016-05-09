<?php namespace App\Ninja\Repositories;

use DB;
use Utils;
use Session;
use App\Models\Token;
use App\Ninja\Repositories\BaseRepository;

class TokenRepository extends BaseRepository
{
    public function getClassName()
    {
        return 'App\Models\AccountToken';
    }

    public function find($userId)
    {
        $query = DB::table('account_tokens')
                  ->where('account_tokens.user_id', '=', $userId);

        if (!Session::get('show_trash:token')) {
            $query->where('account_tokens.deleted_at', '=', null);
        }

        return $query->select('account_tokens.public_id', 'account_tokens.name', 'account_tokens.token', 'account_tokens.public_id', 'account_tokens.deleted_at');
    }
}
