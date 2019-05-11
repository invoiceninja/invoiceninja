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

namespace App\Factory;

class InvoiceItemFactory
{
	public static function create() :\stdClass
	{
		$item = new \stdClass;
		$item->qty = 0;
		$item->cost = 0;
		$item->product_key = '';
		$item->notes = '';
		$item->discount = 0;
		$item->is_amount_discount = true;
		$item->tax_name1 = '';
		$item->tax_rate1 = 0;
		$item->tax_name2 = '';
		$item->tax_rate2 = 0;
		$item->sort_id = 0;
		$item->line_total = 0;
		$item->invoice_item_type_id = 0;
		
		return $item;
	}
}

