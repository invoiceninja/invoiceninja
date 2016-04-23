<?php

namespace App\Policies;

use App\Models\User;
use App\Post;

use Illuminate\Auth\Access\HandlesAuthorization;

class InvoicePolicy extends EntityPolicy
{
    use HandlesAuthorization;

    /**
     * Create a new policy instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }
}
