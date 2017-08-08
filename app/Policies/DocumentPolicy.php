<?php

namespace App\Policies;

use App\Models\User;

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
    public static function create(User $user, $item)
    {
        return ! empty($user);
    }

    /**
     * @param User     $user
     * @param Document $document
     *
     * @return bool
     */
    public static function view(User $user, $document)
    {
        if ($user->hasPermission('view_all')) {
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

        return $user->owns($document);
    }
}
