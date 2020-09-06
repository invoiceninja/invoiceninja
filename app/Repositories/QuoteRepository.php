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

use App\Factory\QuoteInvitationFactory;
use App\Helpers\Invoice\InvoiceSum;
use App\Jobs\Quote\ApplyQuoteNumber;
use App\Jobs\Quote\CreateQuoteInvitations;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Quote;
use App\Models\QuoteInvitation;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;

/**
 * QuoteRepository.
 */
class QuoteRepository extends BaseRepository
{
    use MakesHash;

    public function getClassName()
    {
        return Quote::class;
    }

    public function save($data, Quote $quote) : ?Quote
    {
        return $this->alternativeSave($data, $quote);
    }

    public function getInvitationByKey($key) :?QuoteInvitation
    {
        return QuoteInvitation::whereRaw('BINARY `key`= ?', [$key])->first();
    }
}
