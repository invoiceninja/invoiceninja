<?php

namespace App\Libraries;

use App\Models\CompanyToken;
use App\Models\User;

/**
 * Class MultiDB
 * @package App\Libraries
 */
class MultiDB
{
    const DB_PREFIX = 'db-ninja-';

    public static $dbs = ['db-ninja-01', 'db-ninja-02'];

    /**
     * @param $email
     * @return bool
     */

    public static function getDbs() : array
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
     * @return App\Models\User | bool
     */
    public static function hasUser(array $data) : ?User
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

    public static function findAndSetDb($token) :bool
    {

        foreach (self::$dbs as $db)
        {

            if($ct = CompanyToken::on($db)->whereRaw("BINARY `token`= ?", [$token])->first()) 
            {

                self::setDb($ct->company->db);
                return true;
            }

        }
        return false;

    }

    /**
     * @param $database
     */
    public static function setDB(string $database) : void
    {
        /* This will set the database connection for the request */
        config(['database.default' => $database]);
    }


}