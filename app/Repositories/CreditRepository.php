<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Repositories;

use App\Models\Credit;
use Illuminate\Http\Request;

/**
 * CreditRepository
 */
class CreditRepository extends BaseRepository
{
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
     * Saves the client and its contacts
     *
     * @param      array                           $data    The data
     * @param      \App\Models\Company              $client  The Company
     *
     * @return     Credit|\App\Models\Credit|null  Credit Object
     */
    public function save(array $data, Credit $credit) : ?Credit
    {

    }

}