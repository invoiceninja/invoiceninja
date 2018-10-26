<?php

namespace App\Utils\Traits;

use App\Libraries\MultiDB;
use Hashids\Hashids;

/**
 * Class MakesHash
 * @package App\Utils\Traits
 */
trait MakesHash
{
    /**
     * Creates a simple alphanumeric Hash
     * @return string - asd89f7as89df6asf78as6fds
     */
    public function createHash() : string
    {
        return strtolower(str_random(RANDOM_KEY_LENGTH));
    }

    /**
     * Creates a simple alphanumeric Hash which is prepended with a encoded database prefix
     * @param $db - Full database name
     * @return string 01-asfas8df76a78f6a78dfsdf
     */
    public function createDbHash($db) : string
    {
        return  getDbCode($db) . '-' . strtolower(str_random(RANDOM_KEY_LENGTH));
    }

    /**
     * @param $db - Full database name
     * @return string - hashed and encoded int 01,02,03,04
     */
    public function getDbCode($db) : string
    {
        $hashids = new Hashids();

        return $hashids->encode( str_replace( MultiDB::DB_PREFIX, "", $db ) );
    }
}