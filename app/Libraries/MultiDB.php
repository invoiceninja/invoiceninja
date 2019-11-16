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

namespace App\Libraries;

use App\Models\ClientContact;
use App\Models\Company;
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

    public static function checkDomainAvailable($domain) : bool
    {

        if (! config('ninja.db.multi_db_enabled'))
        {
            return Company::whereDomain($domain)->get()->count() == 0;
        }

            //multi-db active
            foreach (self::$dbs as $db)
            {
                if(Company::whereDomain($domain)->get()->count() >=1)
                    return false;
            }

            return true;
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

            return null;
    }

    public static function contactFindAndSetDb($token) :bool
    {

        foreach (self::$dbs as $db)
        {

            if($ct = ClientContact::on($db)->whereRaw("BINARY `token`= ?", [$token])->first()) 
            {

                self::setDb($ct->company->db);
                
                return true;
            }

        }
        return false;

    }

    public static function userFindAndSetDb($email) : bool
    {


            //multi-db active
            foreach (self::$dbs as $db)
            {
                if(User::on($db)->where(['email' => $email])->get()->count() >=1) // if user already exists, validation will fail
                    return true;
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

    public static function findAndSetDbByDomain($domain) :bool
    {
    //\Log::error("searching for {$domain}");

        foreach (self::$dbs as $db)
        {

            if($company = Company::on($db)->whereDomain($domain)->first()) 
            {

                self::setDb($company->db);
                return true;

            }

        }
        return false;

    }

    public static function findAndSetDbByInvitation($entity, $invitation_key)
    {
        $entity.'Invitation';

        foreach (self::$dbs as $db)
        {
            if($invite = $entity::on($db)->whereKey($invitation_key)->first())
            {
                self::setDb($db);
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