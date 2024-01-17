<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Repositories;

use App\Models\Credit;
use App\Models\CreditInvitation;
use App\Utils\Traits\MakesHash;

/**
 * CreditRepository.
 */
class CreditRepository extends BaseRepository
{
    use MakesHash;

    public function __construct()
    {
    }

    /**
     * Saves the client and its contacts.
     *
     * @param array $data The data
     * @param Credit $credit
     * @return     Credit|Credit|null  Credit Object
     * @throws \ReflectionException
     */
    public function save(array $data, Credit $credit): ?Credit
    {
        return $this->alternativeSave($data, $credit);
    }

    public function getInvitationByKey($key): ?CreditInvitation
    {
        return CreditInvitation::query()->where('key', $key)->first();
    }

    public function delete($credit)
    {
        if ($credit->is_deleted) {
            return;
        }

        $credit = $credit->service()->deleteCredit()->save();

        return parent::delete($credit);
    }

    public function restore($credit)
    {
        //we cannot restore a deleted payment.
        if (! $credit->trashed()) {
            return;
        }

        parent::restore($credit);

        $credit = $credit->service()->restoreCredit()->save();

        return $credit;
    }
}
