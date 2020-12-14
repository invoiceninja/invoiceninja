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

namespace App\Import\Definitions

class InvoiceMap
{

	public static function importable()
	{
		return [
			0 => 'number',
			1 => 'user_id',
			2 => 'amount',
			3 => 'balance',
			4 => 'client_id',
			5 => 'status_id',
			6 => 'is_deleted',
			7 => 'number',
			8 => 'discount',
			9 => 'po_number',
			10 => 'date',
			11 => 'due_date',
			12 => 'terms',
			13 => 'public_notes',
			14 => 'private_notes',
			15 => 'uses_inclusive_taxes',
			16 => 'tax_name1',
			17 => 'tax_rate1',
			18 => 'tax_name2',
			19 => 'tax_rate2',
			20 => 'tax_name3',
			21 => 'tax_rate3',
			22 => 'is_amount_discount',
			23 => 'footer',
			24 => 'partial',
			25 => 'partial_due_date',
			26 => 'custom_value1',
			27 => 'custom_value2',
			28 => 'custom_value3',
			29 => 'custom_value4',
			30 => 'custom_surcharge1',
			31 => 'custom_surcharge2',
			32 => 'custom_surcharge3',
			33 => 'custom_surcharge4',
			34 => 'exchange_rate',
			35 => 'line_items',
		];
	}
}