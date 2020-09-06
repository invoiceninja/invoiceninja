<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Repositories;

use App\Factory\CreditInvitationFactory;
use App\Models\ClientContact;
use App\Models\Credit;
use App\Models\CreditInvitation;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;

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
     * Gets the class name.
     *
     * @return     string The class name.
     */
    public function getClassName()
    {
        return Credit::class;
    }

    /**
     * Saves the client and its contacts.
     *
     * @param      array                           $data    The data
     * @param      \App\Models\Company              $client  The Company
     *
     * @return     Credit|\App\Models\Credit|null  Credit Object
     */
    public function save(array $data, Credit $credit) : ?Credit
    {
        return $this->alternativeSave($data, $credit);
    }

    public function getInvitationByKey($key) :?CreditInvitation
    {
        return CreditInvitation::whereRaw('BINARY `key`= ?', [$key])->first();
    }
}
