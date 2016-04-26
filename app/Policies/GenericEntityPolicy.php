<?php

namespace App\Policies;

use App\Models\User;

use Illuminate\Auth\Access\HandlesAuthorization;

class GenericEntityPolicy
{
    use HandlesAuthorization;
    
    public static function editByOwner($user, $itemType, $ownerUserId) {
        $itemType = ucwords($itemType, '_');
        if (method_exists("App\\Policies\\{$itemType}Policy", 'editByOwner')) {
            return call_user_func(array("App\\Policies\\{$itemType}Policy", 'editByOwner'), $user, $ownerUserId);
        }
        
        return false;
    }
    
    public static function viewByOwner($user, $itemType, $ownerUserId) {
        $itemType = ucwords($itemType, '_');
        if (method_exists("App\\Policies\\{$itemType}Policy", 'viewByOwner')) {
            return call_user_func(array("App\\Policies\\{$itemType}Policy", 'viewByOwner'), $user, $ownerUserId);
        }
        
        return false;
    }
    
    public static function create($user, $itemType) {
        $itemType = ucwords($itemType, '_');        
        if (method_exists("App\\Policies\\{$itemType}Policy", 'create')) {
            return call_user_func(array("App\\Policies\\{$itemType}Policy", 'create'), $user);
        }
        
        return false;
    }
}