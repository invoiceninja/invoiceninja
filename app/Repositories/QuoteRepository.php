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

use App\Models\Quote;
use App\Models\QuoteInvitation;

/**
 * QuoteRepository.
 */
class QuoteRepository extends BaseRepository
{
    public function save($data, Quote $quote) : ?Quote
    {
        return $this->alternativeSave($data, $quote);
    }

    public function getInvitationByKey($key) :?QuoteInvitation
    {
        return QuoteInvitation::whereRaw('BINARY `key`= ?', [$key])->first();
    }
}
