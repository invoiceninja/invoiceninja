<?php

namespace App\Libraries;

use App\Models\User;

/**
 * Class MultiDB
 * @package App\Libraries
 */
class MultiDB
{

    const DB_NINJA_1 = 1;
    const DB_NINJA_2 = 2;

    public static $dbs = ['db-ninja-1', 'db-ninja-2'];

    /**
     * @param $email
     * @return bool
     */

    public static function getDbs()
    {

        return self::$dbs;

    }

    public static function checkUserEmailExists($email) : bool
    {
        if (! config('ninja.db.multi_db_enabled'))
        {
            return User::where(['email' => $email])->get()->count() >= 1 ?? false; // true >= 1 emails found / false -> == emails found
        }

            //multi-db active
            foreach (self::$dbs as $db)
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
    public static function hasUser(array $data)
    {
        if (! config('ninja.db.multi_db_enabled'))
        {
            return User::where($data)->first();
        }

            foreach (self::$dbs as $db)
            {
                self::setDB($db);

                $user = User::where($data)->first();

                    if($user) {

                        return $user;

                    }

            }

            return false;
    }


    /**
     * @param $database
     */
    public static function setDB($database) : void
    {
        /* This will set the database connection for the request */
        config(['database.default' => $database]);
    }

}