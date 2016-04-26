<?php

namespace App\Policies;

class DocumentPolicy extends EntityPolicy {
	public static function create($user){
        return !empty($user);
    }
	
	public static function view($user, $document) {
		if($user->hasPermission('view_all'))return true;
		if($document->expense){
			if($document->expense->invoice)return $user->can('view', $document->expense->invoice);
			return $user->can('view', $document->expense);
		}
		if($document->invoice)return $user->can('view', $document->invoice);
		
		return $user->owns($item);
    }
}