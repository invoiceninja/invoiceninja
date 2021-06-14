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

namespace App\Utils;

use App\Libraries\MultiDB;
use App\Mail\TestMailServer;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

/**
 * Class SystemHealth.
 */
class SystemHealth
{
    private static $extensions = [
        // 'mysqli',
        'gd',
        'curl',
        'zip',
//        'gmp',
        'openssl',
        'mbstring',
        'xml',
        'bcmath',
        // 'mysqlnd',
        //'intl', //todo double check whether we need this for email dns validation
    ];

    private static $php_version = 7.3;

    /**
     * Check loaded extensions / PHP version / DB Connections.
     *
     * @param bool $check_database
     * @return     array  Result set of checks
     */
    public static function check($check_database = true): array
    {
        $system_health = true;

        if (in_array(false, Arr::dot(self::extensions()))) {
            $system_health = false;
        }

        if (phpversion() < self::$php_version) {
            $system_health = false;
        }

        if (!self::simpleDbCheck() && $check_database) {
            info('db fails');
            $system_health = false;
        }

        return [
            'system_health' => $system_health,
            'extensions' => self::extensions(),
            'php_version' => [
                'minimum_php_version' => (string)self::$php_version,
                'current_php_version' => phpversion(),
                'current_php_cli_version' => (string)self::checkPhpCli(),
                'is_okay' => version_compare(phpversion(), self::$php_version, '>='),
            ],
            'env_writable' => self::checkEnvWritable(),
            //'mail' => self::testMailServer(),
            'simple_db_check' => (bool)self::simpleDbCheck(),
            'cache_enabled' => self::checkConfigCache(),
            'phantom_enabled' => (bool)config('ninja.phantomjs_pdf_generation'),
            'exec' => (bool)self::checkExecWorks(),
            'open_basedir' => (bool)self::checkOpenBaseDir(),
            'mail_mailer' => (string)self::checkMailMailer(),
            'flutter_renderer' => (string)config('ninja.flutter_canvas_kit'),
            'jobs_pending' => (int) Queue::size(),
            'pdf_engine' => (string) self::getPdfEngine(),
        ];
    }

    public static function getPdfEngine()
    {
        if(config('ninja.invoiceninja_hosted_pdf_generation'))
            return 'Invoice Ninja Hosted PDF Generator';
        elseif(config('ninja.phantomjs_pdf_generation'))
            return 'Phantom JS Web Generator';
        else
            return 'SnapPDF PDF Generator';
    }

    public static function checkMailMailer()
    {
        return config('mail.default');
    }

    public static function checkOpenBaseDir()
    {
        if (strlen(ini_get('open_basedir') == 0)) {
            return true;
        }

        return false;
    }

    public static function checkExecWorks()
    {
        if (function_exists('exec')) {
            return true;
        }

        return false;
    }

    public static function checkConfigCache()
    {
        if (env('APP_URL')) {
            return false;
        }

        return true;
    }

    private static function simpleDbCheck(): bool
    {
        $result = true;

        try {
            $pdo = DB::connection()->getPdo();
            $result = true;
        } catch (Exception $e) {
            $result = false;
        }

        return $result;
    }

    private static function checkPhpCli()
    {
        try {
            exec('php -v', $foo, $exitCode);

            if ($exitCode === 0) {
                return empty($foo[0]) ? 'Found php cli, but no version information' : $foo[0];
            }
        } catch (Exception $e) {
            return false;
        }
    }

    private static function extensions(): array
    {
        $loaded_extensions = [];

        foreach (self::$extensions as $extension) {
            $loaded_extensions[] = [$extension => extension_loaded($extension)];
        }

        return $loaded_extensions;
    }

    public static function dbCheck($request = null): array
    {
        $result = ['success' => false];

        if ($request && !config('ninja.preconfigured_install')) {
            config(['database.connections.db-ninja-01.host' => $request->input('db_host')]);
            config(['database.connections.db-ninja-01.port' => $request->input('db_port')]);
            config(['database.connections.db-ninja-01.database' => $request->input('db_database')]);
            config(['database.connections.db-ninja-01.username' => $request->input('db_username')]);
            config(['database.connections.db-ninja-01.password' => $request->input('db_password')]);
            config(['database.default' => 'db-ninja-01']);

            DB::purge('db-ninja-01');
        }

        if (!config('ninja.db.multi_db_enabled')) {
            try {
                $pdo = DB::connection()->getPdo();
                $result[] = [DB::connection()->getDatabaseName() => true];
                $result['success'] = true;
            } catch (Exception $e) {
                $result[] = [config('database.connections.' . config('database.default') . '.database') => false];
                $result['success'] = false;
                $result['message'] = $e->getMessage();
            }
        } else {
            foreach (MultiDB::$dbs as $db) {
                MultiDB::setDB($db);

                try {
                    $pdo = DB::connection()->getPdo();
                    $result[] = [DB::connection()->getDatabaseName() => true];
                    $result['success'] = true;
                } catch (Exception $e) {
                    $result[] = [config('database.connections.' . config('database.default') . '.database') => false];
                    $result['success'] = false;
                    $result['message'] = $e->getMessage();
                }
            }
        }

        return $result;
    }

    private static function checkDbConnection()
    {
        return DB::connection()->getPdo();
    }

    public static function testMailServer($request = null)
    {
        if ($request->driver == 'log') {
            return [];
        }

        if ($request) {
            config(['mail.driver' => $request->input('mail_driver')]);
            config(['mail.host' => $request->input('mail_host')]);
            config(['mail.port' => $request->input('mail_port')]);
            config(['mail.from.address' => $request->input('mail_address')]);
            config(['mail.from.name' => $request->input('mail_name')]);
            config(['mail.encryption' => $request->input('encryption')]);
            config(['mail.username' => $request->input('mail_username')]);
            config(['mail.password' => $request->input('mail_password')]);
        }

        try {
            Mail::to(config('mail.from.address'))->send(new TestMailServer('Email Server Works!', config('mail.from.address')));
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        return ['success' => true];
    }

    private static function checkEnvWritable()
    {
        return is_writable(base_path() . '/.env');
    }
}
