<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PurchaseOrderPolicy extends EntityPolicy
{
    use HandlesAuthorization;

    public function create(User $user) : bool
    {
        return $user->isAdmin() || $user->hasPermission('create_purchase_order') || $user->hasPermission('create_all');
    }
}
