<?php

namespace App\Policies;

use App\Models\User;
use App\Models\EntityModel;

use Illuminate\Auth\Access\HandlesAuthorization;

class InvoicePolicy extends EntityPolicy
{
    use HandlesAuthorization;
    
    public static function canCreate() {
        return Auth::user()->hasPermission('create_all');
    }
    
    public static function edit($user, $item) {
        $user->hasPermission('edit_all') || $user->owns($item);
    }
    
    public static function view($user, $item) {
        $user->hasPermission('view_all') || $user->owns($item);
    }
}