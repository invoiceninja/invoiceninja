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

namespace App\Models\Presenters;

use App\Utils\Number;
use App\Utils\Traits\MakesDates;

/**
 * Class InvoicePresenter.
 *
 * For convenience and to allow users to easiliy
 * customise their invoices, we provide all possible
 * invoice variables to be available from this presenter.
 *
 * Shortcuts to other presenters are here to facilitate
 * a clean UI / UX
 */
class QuotePresenter extends EntityPresenter
{
    use MakesDates;

    public function amount()
    {
        return Number::formatMoney($this->balance, $this->client);
    }

    public function invoice_number()
    {
        if ($this->number != '') {
            return $this->number;
        } else {
            return '';
        }
    }
}
