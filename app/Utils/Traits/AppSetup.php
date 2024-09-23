<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Utils\Traits;

use App\DataMapper\EmailTemplateDefaults;
use App\Utils\Ninja;
use App\Utils\SystemHealth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

trait AppSetup
{
    public function checkAppSetup()
    {
        if (Ninja::isNinja()) {  // Is this the invoice ninja production system?
            return Ninja::isNinja();
        }

        $check = SystemHealth::check(true, false);

        return $check['system_health'] == 'true';
    }

    /**
     * @deprecated
     *
     * @param  mixed $force
     * @return void
     */
    public function buildCache($force = false)
    {
        return;

        $cached_tables = config('ninja.cached_tables');

        foreach ($cached_tables as $name => $class) {
            if (! Cache::has($name) || $force) {
                if ($name == 'payment_terms') {
                    $orderBy = 'num_days';
                } elseif ($name == 'fonts') {
                    $orderBy = 'sort_order';
                } elseif (in_array($name, ['currencies', 'industries', 'languages', 'countries', 'banks', 'timezones'])) {
                    $orderBy = 'name';
                } else {
                    $orderBy = 'id';
                }
                $tableData = $class::orderBy($orderBy)->get();
                if ($tableData->count() > 1) {
                    Cache::forever($name, $tableData);
                }
            }
        }

        /*Build template cache*/
    }

    private function updateEnvironmentProperty(string $property, $value): void
    {

        $env = file(base_path('.env'));

        $position = null;

        foreach ((array) $env as $key => $variable) {
            if (Str::startsWith($variable, $property)) {
                $position = $key;
            }
        }

        $words_count = count(explode(' ', trim($value)));

        if (is_null($position)) {
            $words_count > 1 ? $env[] = "{$property}=".'"'.$value.'"'."\n" : $env[] = "{$property}=".$value."\n";
        } else {
            $env[$position] = "{$property}=".'"'.$value.'"'."\n"; // If value of variable is more than one word, surround with quotes.
        }

        try {
            file_put_contents(base_path('.env'), $env);
        } catch (\Exception $e) {
            info($e->getMessage());
        }
    }
}
