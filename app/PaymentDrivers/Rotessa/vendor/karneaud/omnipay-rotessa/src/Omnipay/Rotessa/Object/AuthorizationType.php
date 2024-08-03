<?php

namespace Omnipay\Rotessa\Object;

use Omnipay\Rotessa\IsValidTypeTrait;

final class AuthorizationType {

    use isValidTypeTrait;
    
    const IN_PERSON = "In Person";
    const ONLINE = "Online";

    public static function isInPerson($value) {
        return $value === self::IN_PERSON;
    }

    public static function isOnline($value) {
        return $value === self::ONLINE;
    }

    public static function getTypes() : array {
        return [
            self::IN_PERSON,
            self::ONLINE
        ];
    }
}
