<?php

namespace App\Libraries;

use App\Models\User;

/**
 * Class MultiDB
 * @package App\Libraries
 */
class MultiDB
{

    /**
     * @param $email
     * @return bool
     */
    public static function checkUserEmailExists($email) : bool
    {
        if (config('auth.providers.users.driver') == 'eloquent') //default eloquent = single DB
        {
            return User::where(['email' => $email])->get()->count() >= 1 ?? false; // true >= 1 emails found / false -> == emails found
        }

            //multi-db active
            foreach (unserialize(MULTI_DBS) as $db)
            {
                if(User::on($db)->where(['email' => $email])->get()->count() >=1) // if user already exists, validation will fail
                    return true;
            }

            return false;

    }

    /**
     * @param array $data
     * @return bool
     */
    public static function getUser(array $data)
    {
        if (config('auth.providers.users.driver') == 'eloquent') //default eloquent = single DB
        {
            return User::where($data)->first();
        }

            foreach (unserialize(MULTI_DBS) as $db)
            {
                self::setDB($db);

                $user = User::where($data)->first();

                    if($user)
                        return $user;
            }

            return false;
    }


    /**
     * @param $database
     */
    public static function setDB($database) : void
    {
        /* This will set the default configuration for the request */
        config(['database.default' => $database]);
        app('db')->connection(config('database.connections.database.'.$database));
    }

}