<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Libraries;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\CompanyToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Class MultiDB.
 *
 * Caution!
 *
 * When we perform scans across databases,
 * we need to remember that if we don't
 * return a DB 'HIT' the DB connection will
 * be set to the last DB in the chain,
 *
 * So for these cases, we need to reset the
 * DB connection to the default connection.
 *
 * Even that may be problematic, and we
 * may need to know the current DB connection
 * so that we can fall back gracefully.
 */
class MultiDB
{
    const DB_PREFIX = 'db-ninja-';

    public static $dbs = ['db-ninja-01', 'db-ninja-02'];

    /**
     * @return array
     */
    public static function getDbs() : array
    {
        return self::$dbs;
    }

    public static function checkDomainAvailable($subdomain) : bool
    {
        if (! config('ninja.db.multi_db_enabled')) {
            return Company::whereSubdomain($subdomain)->get()->count() == 0;
        }

        //multi-db active
        foreach (self::$dbs as $db) {
            if (Company::on($db)->whereSubdomain($subdomain)->get()->count() >= 1) {
                return false;
            }
        }

        self::setDefaultDatabase();

        return true;
    }

    public static function checkUserEmailExists($email) : bool
    {
        if (! config('ninja.db.multi_db_enabled')) {
            return User::where(['email' => $email])->get()->count() >= 1 ?? false; // true >= 1 emails found / false -> == emails found
        }

        //multi-db active
        foreach (self::$dbs as $db) {
            if (User::on($db)->where(['email' => $email])->get()->count() >= 1) { // if user already exists, validation will fail
                return true;
            }
        }

        self::setDefaultDatabase();

        return false;
    }

    /**
     * A user and company must co exists on the same database.
     *
     * This function will check that if a user exists on the system,
     * the company is also located on the same database.
     *
     * If no user is found, then we also return true as this must be
     * a new user request.
     *
     * @param  string $email       The user email
     * @param  stirng $company_key The company key
     * @return bool             True|False
     */
    public static function checkUserAndCompanyCoExist($email, $company_key) :bool
    {
        foreach (self::$dbs as $db) {
            if (User::on($db)->where(['email' => $email])->get()->count() >= 1) { // if user already exists, validation will fail
                if (Company::on($db)->where(['company_key' => $company_key])->get()->count() >= 1) {
                    return true;
                } else {
                    self::setDefaultDatabase();

                    return false;
                }
            }
        }

        self::setDefaultDatabase();

        return true;
    }

    /**
     * @param array $data
     * @return User|null
     */
    public static function hasUser(array $data) : ?User
    {
        if (! config('ninja.db.multi_db_enabled')) {
            return User::where($data)->withTrashed()->first();
        }

        foreach (self::$dbs as $db) {
            self::setDB($db);

            $user = User::where($data)->withTrashed()->first();

            if ($user) {
                return $user;
            }
        }

        self::setDefaultDatabase();

        return null;
    }

    /**
     * @param array $data
     * @return User|null
     */
    public static function hasContact(array $data) : ?ClientContact
    {
        if (! config('ninja.db.multi_db_enabled')) {
            return ClientContact::where($data)->withTrashed()->first();
        }

        foreach (self::$dbs as $db) {
            self::setDB($db);

            $user = ClientContacts::where($data)->withTrashed()->first();

            if ($user) {
                return $user;
            }
        }

        self::setDefaultDatabase();

        return null;
    }

    public static function contactFindAndSetDb($token) :bool
    {
        foreach (self::$dbs as $db) {
            if ($ct = ClientContact::on($db)->whereRaw('BINARY `token`= ?', [$token])->first()) {
                self::setDb($ct->company->db);

                return true;
            }
        }

        self::setDefaultDatabase();

        return false;
    }

    public static function userFindAndSetDb($email) : bool
    {

        //multi-db active
        foreach (self::$dbs as $db) {
            
            if (User::on($db)->where(['email' => $email])->count() >= 1) 
                return true;
            
        }
        self::setDefaultDatabase();

        return false;
    }

    public static function findAndSetDb($token) :bool
    {
        foreach (self::$dbs as $db) {
            if ($ct = CompanyToken::on($db)->whereRaw('BINARY `token`= ?', [$token])->first()) {
                self::setDb($ct->company->db);

                return true;
            }
        }
        self::setDefaultDatabase();

        return false;
    }

    public static function findAndSetDbByCompanyKey($company_key) :bool
    {
        foreach (self::$dbs as $db) {
            if ($company = Company::on($db)->where('company_key', $company_key)->first()) {
                self::setDb($company->db);

                return true;
            }
        }
        self::setDefaultDatabase();

        return false;
    }

    public static function findAndSetDbByContactKey($contact_key) :bool
    {
        foreach (self::$dbs as $db) {
            if ($client_contact = ClientContact::on($db)->where('contact_key', $contact_key)->first()) {
                self::setDb($client_contact->company->db);
                return true;
            }
        }
        self::setDefaultDatabase();

        return false;
    }

    public static function findAndSetDbByClientHash($client_hash) :bool
    {
        foreach (self::$dbs as $db) {
            if ($client = Client::on($db)->where('client_hash', $client_hash)->first()) {
                self::setDb($client->company->db);
                return true;
            }
        }
        self::setDefaultDatabase();

        return false;
    }

    public static function findAndSetDbByDomain($subdomain) :bool
    {

        if (! config('ninja.db.multi_db_enabled'))
            return (Company::whereSubdomain($subdomain)->exists() === true);

        foreach (self::$dbs as $db) {
            if ($company = Company::on($db)->whereSubdomain($subdomain)->first()) {
                self::setDb($company->db);
                return true;
            }
        }

        self::setDefaultDatabase();

        return false;
    }

    public static function findAndSetDbByInvitation($entity, $invitation_key)
    {
        $class = 'App\Models\\'.ucfirst(Str::camel($entity)).'Invitation';

        foreach (self::$dbs as $db) {
            if ($invite = $class::on($db)->whereRaw('BINARY `key`= ?', [$invitation_key])->first()) {
                self::setDb($db);

                return true;
            }
        }

        self::setDefaultDatabase();

        return false;
    }

    /**
     * @param $database
     */
    public static function setDB(string $database) : void
    {
        /* This will set the database connection for the request */
        config(['database.default' => $database]);

        // for some reason this breaks everything _hard_
        // DB::purge($database);
        // DB::reconnect($database);
    }

    public static function setDefaultDatabase()
    {
        config(['database.default' => config('ninja.db.default')]);

        // DB::purge(config('ninja.db.default'));
        // DB::reconnect(config('ninja.db.default'));
    }
}
