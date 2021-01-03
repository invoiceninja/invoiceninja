<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Policies;

use App\Models\User;

/**
 * Class TaxRatePolicy.
 */
class TaxRatePolicy extends EntityPolicy
{
    public function create(User $user) : bool
    {
        return $user->isAdmin();
    }
}
