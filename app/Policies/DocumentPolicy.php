<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Class DocumentPolicy.
 */
class DocumentPolicy extends EntityPolicy
{
    /**
     * @param User  $user
     * @param mixed $item
     *
     * @return bool
     */
    public function create(User $user)
    {
        return ! empty($user);
    }

    /**
     * @param User     $user
     * @param Document $document
     *
     * @return bool
     */
    public function view(User $user, $document, $entityType = null)
    {
        if ($user->hasPermission(['view_expense', 'view_invoice'], true)) {
            return true;
        }
        if ($document->expense) {
            if ($document->expense->invoice) {
                return $user->can('view', $document->expense->invoice);
            }

            return $user->can('view', $document->expense);
        }
        if ($document->invoice) {
            return $user->can('view', $document->invoice);
        }
        if($document->ticket){
            return $user->can('view', $document->ticket);
        }


        return $user->owns($document);
    }
}
