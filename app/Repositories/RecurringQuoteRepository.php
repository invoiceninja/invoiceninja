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

namespace App\Repositories;

use App\Helpers\Invoice\InvoiceSum;
use App\Models\RecurringQuote;
use Illuminate\Http\Request;

/**
 * RecurringQuoteRepository.
 */
class RecurringQuoteRepository extends BaseRepository
{
    public function save(Request $request, RecurringQuote $quote) : ?RecurringQuote
    {
        $quote->fill($request->input());

        $quote->save();

        $quote_calc = new InvoiceSum($quote);

        $quote = $quote_calc->build()->getQuote();

        return $quote;
    }
}
