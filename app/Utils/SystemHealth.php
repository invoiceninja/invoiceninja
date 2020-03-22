<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Utils;

use App\Libraries\MultiDB;
use App\Mail\TestMailServer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Class SystemHealth.
 */
class SystemHealth
{
    private static $extensions = [
        'mysqli',
        'gd',
        'curl',
        'zip',
        'gmp'
    ];

    private static $php_version = 7.3;


    /**
     * Check loaded extensions / PHP version / DB Connections
     *
     * @return     array  Result set of checks
     */
    public static function check() : array
    {
        $system_health = true;

        if (in_array(false, self::extensions())) {
            $system_health = false;
        } elseif (phpversion() < self::$php_version) {
            $system_health = false;
        }

        return [
            'system_health' => $system_health,
            'extensions' => self::extensions(),
            'php_version' => phpversion(),
            'min_php_version' => self::$php_version,
            'dbs' => self::dbCheck(),
            'mail' => self::testMailServer(),
            'env_writable' => self::checkEnvWritable(),
        ];
    }

    private static function extensions() :array
    {
        $loaded_extensions = [];

        foreach (self::$extensions as $extension) {
            $loaded_extensions[] = [$extension => extension_loaded($extension)];
        }

        return $loaded_extensions;
    }

    private static function dbCheck() :array
    {
        $result = [];

        if (! config('ninja.db.multi_db_enabled')) {
            $pdo = DB::connection()->getPdo();

            if ($pdo) {
                $result[] = [ DB::connection()->getDatabaseName() => true ];
            } else {
                $result[] = [ config('database.connections.' . config('database.default') . '.database') => false ];
            }
        } else {
            foreach (MultiDB::$dbs as $db) {
                MultiDB::setDB($db);

                $pdo = DB::connection()->getPdo();
                            
                if ($pdo) {
                    $result[] = [ DB::connection()->getDatabaseName() => true ];
                } else {
                    $result[] = [ config('database.connections.' . config('database.default') . '.database') => false ];
                }
            }
        }

        return $result;
    }

    private static function checkDbConnection()
    {
        return DB::connection()->getPdo();
    }

    private static function testMailServer()
    {
        try {
            Mail::to(config('mail.from.address'))
            ->send(new TestMailServer('Email Server Works!', config('mail.from.address')));
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        if (count(Mail::failures()) > 0) {
            return Mail::failures();
        }

        return [];
    }

    private static function checkEnvWritable()
    {
        return is_writable(base_path().'/.env');
        //return @fopen(base_path().'/.env', 'w');
    }
}
