<?php

namespace App\Policies;

use App\Models\User;
use App\Models\EntityModel;

use Illuminate\Auth\Access\HandlesAuthorization;

class EntityPolicy
{
    use HandlesAuthorization;
    
    public static function create($user) {
        return $user->hasPermission('create_all');
    }
    
    public static function edit($user, $item) {
        return $user->hasPermission('edit_all') || $user->owns($item);
    }
    
    public static function view($user, $item) {
        return $user->hasPermission('view_all') || $user->owns($item);
    }
    
    public static function viewByOwner($user, $ownerUserId) {
        return $user->hasPermission('view_all') || $user->id == $ownerUserId;
    }
    
    public static function editByOwner($user, $ownerUserId) {
        return $user->hasPermission('edit_all') || $user->id == $ownerUserId;
    }
}