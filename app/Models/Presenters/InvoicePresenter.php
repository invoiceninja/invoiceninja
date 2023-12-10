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
 *
 * @property \App\Models\Invoice $entity
 */
class InvoicePresenter extends EntityPresenter
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

    public function rBits()
    {
        $properties = new \stdClass();
        $properties->terms_text = $this->terms;
        $properties->note = $this->public_notes;
        $properties->itemized_receipt = [];

        foreach ($this->line_items as $item) {
            $properties->itemized_receipt[] = $this->itemRbits($item);
        }

        $data = new \stdClass();
        $data->receive_time = time();
        $data->type = 'transaction_details';
        $data->source = 'user';
        $data->properties = $properties;

        return [$data];
    }

    public function itemRbits($item)
    {
        $data = new \stdClass();
        $data->description = $item->notes;
        $data->item_price = floatval($item->cost);
        $data->quantity = floatval($item->quantity);
        $data->amount = round($data->item_price * $data->quantity, 2);

        return $data;
    }
}
