<?php

namespace App\Policies;


use App\Models\User;
use Utils;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Class GenericEntityPolicy
 */
class GenericEntityPolicy
{
    use HandlesAuthorization;

    /**
     * @param User $user
     * @param $itemType
     * @param $ownerUserId
     * @return bool|mixed
     */
    public static function editByOwner(User $user, $itemType, $ownerUserId) {
        $itemType = Utils::getEntityName($itemType);
        if (method_exists("App\\Policies\\{$itemType}Policy", 'editByOwner')) {
            return call_user_func(["App\\Policies\\{$itemType}Policy", 'editByOwner'], $user, $ownerUserId);
        }
        
        return false;
    }

    /**
     * @param User $user
     * @param $itemType
     * @param $ownerUserId
     * @return bool|mixed
     */
    public static function viewByOwner(User $user, $itemType, $ownerUserId) {
        $itemType = Utils::getEntityName($itemType);
        if (method_exists("App\\Policies\\{$itemType}Policy", 'viewByOwner')) {
            return call_user_func(["App\\Policies\\{$itemType}Policy", 'viewByOwner'], $user, $ownerUserId);
        }
        
        return false;
    }

    /**
     * @param User $user
     * @param $itemType
     * @return bool|mixed
     */
    public static function create(User $user, $itemType) {
        $itemType = Utils::getEntityName($itemType);
        if (method_exists("App\\Policies\\{$itemType}Policy", 'create')) {
            return call_user_func(["App\\Policies\\{$itemType}Policy", 'create'], $user);
        }
        
        return false;
    }
}