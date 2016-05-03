<?php

namespace App\Policies;

use App\Models\User;
use Utils;

use Illuminate\Auth\Access\HandlesAuthorization;

class GenericEntityPolicy
{
    use HandlesAuthorization;
    
    public static function editByOwner($user, $itemType, $ownerUserId) {
        $itemType = Utils::getEntityName($itemType);
        if (method_exists("App\\Policies\\{$itemType}Policy", 'editByOwner')) {
            return call_user_func(array("App\\Policies\\{$itemType}Policy", 'editByOwner'), $user, $ownerUserId);
        }
        
        return false;
    }
    
    public static function viewByOwner($user, $itemType, $ownerUserId) {
        $itemType = Utils::getEntityName($itemType);
        if (method_exists("App\\Policies\\{$itemType}Policy", 'viewByOwner')) {
            return call_user_func(array("App\\Policies\\{$itemType}Policy", 'viewByOwner'), $user, $ownerUserId);
        }
        
        return false;
    }
    
    public static function create($user, $itemType) {
        $itemType = Utils::getEntityName($itemType);
        if (method_exists("App\\Policies\\{$itemType}Policy", 'create')) {
            return call_user_func(array("App\\Policies\\{$itemType}Policy", 'create'), $user);
        }
        
        return false;
    }
}