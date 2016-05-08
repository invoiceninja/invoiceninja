<?php

namespace App\Policies;

class ProductPolicy extends EntityPolicy {
	public static function edit($user, $item) {
        return $user->hasPermission('admin');
    }

    public static function create($user) {
        return $user->hasPermission('admin');
    }
}