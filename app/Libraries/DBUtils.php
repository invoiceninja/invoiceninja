<?php

namespace App\Libraries;

use DB;

class DBUtils
{
    // NOTE: does not add quotes
    public static function concat() {
        if (func_num_args() < 2) {
            return func_get_arg(1);
        }

        $dialect = [ 'mysql' => ['CONCAT', ','], 'sqlite' => ['', ' || '] ];
        list($fun, $sep) = $dialect[DB::getDriverName()] ?? $dialect['mysql']; // mysql is the default
        return $fun.'('.implode($sep, func_get_args()).')';
    }

    public static function substr() {
        $dialect = [ 'mysql' => 'SUBSTRING', 'sqlite' => 'SUBSTR' ];
        $fun = $dialect[DB::getDriverName()] ?? $dialect['mysql']; // mysql is the default
        return $fun.'('.implode(',', func_get_args()).')';
    }

    public static function unix_timestamp() {
        if (DB::getDriverName() === 'sqlite') {
            return "strftime('%s', 'now')";
        } else {
            return 'unix_timestamp()';
        }
    }
}
