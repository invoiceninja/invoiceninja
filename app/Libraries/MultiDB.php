<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Libraries;

use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\CompanyToken;
use App\Models\Document;
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

        $current_db = config('database.default');

        foreach (self::$dbs as $db) {
            if (Company::on($db)->whereSubdomain($subdomain)->exists()) {
                self::setDb($current_db);

                return false;
            }
        }

        self::setDb($current_db);

        return true;
    }

    public static function checkUserEmailExists($email) : bool
    {
        if (! config('ninja.db.multi_db_enabled')) {
            return User::where(['email' => $email])->withTrashed()->exists();
        } // true >= 1 emails found / false -> == emails found

        $current_db = config('database.default');

        foreach (self::$dbs as $db) {
            if (User::on($db)->where(['email' => $email])->withTrashed()->exists()) { // if user already exists, validation will fail
                self::setDb($current_db);

                return true;
            }
        }

        self::setDb($current_db);

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
        $current_db = config('database.default');

        foreach (self::$dbs as $db) {
            if (User::on($db)->where(['email' => $email])->withTrashed()->exists()) {
                if (Company::on($db)->where(['company_key' => $company_key])->exists()) {
                    self::setDb($current_db);

                    return true;
                } else {
                    self::setDb($current_db);

                    return false;
                }
            }
        }

        self::setDb($current_db);

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

        $current_db = config('database.default');

        foreach (self::$dbs as $db) {
            self::setDB($db);
            if ($user = User::where($data)->withTrashed()->first()) {
                return $user;
            }
        }

        self::setDb($current_db);

        return null;
    }

    /**
     * @param array $data
     * @return User|null
     */
    public static function hasContact(string $email) : ?ClientContact
    {
        if (! config('ninja.db.multi_db_enabled')) {
            return ClientContact::where('email', $email)->withTrashed()->first();
        }

        $current_db = config('database.default');

        foreach (self::$dbs as $db) {
            $user = ClientContact::on($db)->where('email', $email)->withTrashed()->first();

            if ($user) {
                self::setDb($db);

                return $user;
            }
        }

        self::setDB($current_db);

        return null;
    }

    /**
     * @param array $data
     * @return User|null
     */
    public static function findContact(array $search) : ?ClientContact
    {
        if (! config('ninja.db.multi_db_enabled')) {
            return ClientContact::where($search)->first();
        }

        $current_db = config('database.default');

        foreach (self::$dbs as $db) {
            $user = ClientContact::on($db)->where($search)->first();

            if ($user) {
                self::setDb($db);

                return $user;
            }
        }

        self::setDB($current_db);

        return null;
    }

    public static function contactFindAndSetDb($token) :bool
    {
        $current_db = config('database.default');

        foreach (self::$dbs as $db) {
            if (ClientContact::on($db)->where('token', $token)->exists()) {
                self::setDb($db);

                return true;
            }
        }

        self::setDB($current_db);

        return false;
    }

    public static function userFindAndSetDb($email) : bool
    {
        $current_db = config('database.default');

        //multi-db active
        foreach (self::$dbs as $db) {
            if (User::on($db)->where('email', $email)->withTrashed()->exists()) {
                self::setDb($db);

                return true;
            }
        }

        self::setDB($current_db);

        return false;
    }

    public static function documentFindAndSetDb($hash) : bool
    {
        $current_db = config('database.default');

        //multi-db active
        foreach (self::$dbs as $db) {
            if (Document::on($db)->where('hash', $hash)->exists()) {
                self::setDb($db);

                return true;
            }
        }

        self::setDB($current_db);

        return false;
    }

    public static function findAndSetDb($token) :bool
    {
        $current_db = config('database.default');

        foreach (self::$dbs as $db) {
            if (CompanyToken::on($db)->where('token', $token)->exists()) {
                self::setDb($db);

                return true;
            }
        }

        self::setDB($current_db);

        return false;
    }

    public static function findAndSetDbByCompanyKey($company_key) :bool
    {
        $current_db = config('database.default');

        foreach (self::$dbs as $db) {
            if (Company::on($db)->where('company_key', $company_key)->exists()) {
                self::setDb($db);

                return true;
            }
        }

        self::setDB($current_db);

        return false;
    }

    public static function findAndSetDbByCompanyId($company_id) :?Company
    {
        $current_db = config('database.default');

        foreach (self::$dbs as $db) {
            if ($company = Company::on($db)->where('id', $company_id)->first()) {
                self::setDb($db);

                return $company;
            }
        }

        self::setDB($current_db);

        return false;
    }

    public static function findAndSetDbByAccountKey($account_key) :bool
    {
        $current_db = config('database.default');

        foreach (self::$dbs as $db) {
            if (Account::on($db)->where('key', $account_key)->exists()) {
                self::setDb($db);

                return true;
            }
        }

        self::setDB($current_db);

        return false;
    }

    public static function findAndSetDbByContactKey($contact_key) :bool
    {
        $current_db = config('database.default');

        foreach (self::$dbs as $db) {
            if (ClientContact::on($db)->where('contact_key', $contact_key)->exists()) {
                self::setDb($db);

                return true;
            }
        }

        self::setDB($current_db);

        return false;
    }

    public static function findAndSetDbByClientHash($client_hash) :bool
    {
        $current_db = config('database.default');

        foreach (self::$dbs as $db) {
            if (Client::on($db)->where('client_hash', $client_hash)->exists()) {
                self::setDb($db);

                return true;
            }
        }

        self::setDB($current_db);

        return false;
    }

    public static function findAndSetDbByClientId($client_id) :?Client
    {
        $current_db = config('database.default');

        foreach (self::$dbs as $db) {
            if ($client = Client::on($db)->where('id', $client_id)->first()) {
                self::setDb($db);

                return $client;
            }
        }

        self::setDB($current_db);

        return false;
    }

    public static function findAndSetDbByDomain($query_array)
    {
        if (! config('ninja.db.multi_db_enabled')) {
            return Company::where($query_array)->first();
        }

        $current_db = config('database.default');

        foreach (self::$dbs as $db) {
            if ($company = Company::on($db)->where($query_array)->first()) {
                self::setDb($db);

                return $company;
            }
        }

        self::setDB($current_db);

        return false;
    }

    public static function findAndSetDbByInvitation($entity, $invitation_key)
    {
        $class = 'App\Models\\'.ucfirst(Str::camel($entity)).'Invitation';
        $current_db = config('database.default');

        foreach (self::$dbs as $db) {
            if ($invite = $class::on($db)->where('key', $invitation_key)->exists()) {
                self::setDb($db);

                return true;
            }
        }

        self::setDB($current_db);

        return false;
    }

    /**
     * @param array $data
     * @return User|null
     */
    public static function hasPhoneNumber(string $phone) : bool
    {
        if (! config('ninja.db.multi_db_enabled')) 
            return Account::where('account_sms_verification_number', $phone)->where('account_sms_verified', true)->exists();
        
        $current_db = config('database.default');  

        foreach (self::$dbs as $db) {

            self::setDB($db);
            if ($exists = Account::where('account_sms_verification_number', $phone)->where('account_sms_verified', true)->exists()) {
                self::setDb($current_db);
                return true;
            }
            
        }

        self::setDb($current_db);

        return false;
    }

    

    public static function randomSubdomainGenerator()
    {
        $current_db = config('database.default');

        do {
            $length = 8;
            $string = '';
            $vowels = ['a', 'e', 'i', 'o', 'u'];
            $consonants = [
                'b', 'c', 'd', 'f', 'g', 'h', 'j', 'k', 'l', 'm',
                'n', 'p', 'r', 's', 't', 'v', 'w', 'x', 'y', 'z',
            ];

            $max = $length / 2;
            for ($i = 1; $i <= $max; $i++) {
                $string .= $consonants[rand(0, 19)];
                $string .= $vowels[rand(0, 4)];
            }
        } while (! self::checkDomainAvailable($string));

        self::setDb($current_db);

        return $string;
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
