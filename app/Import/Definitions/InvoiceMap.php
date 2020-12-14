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

namespace App\Import\Definitions;

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
			7 => 'discount',
			8 => 'po_number',
			9 => 'date',
			10 => 'due_date',
			11 => 'terms',
			12 => 'public_notes',
			13 => 'private_notes',
			14 => 'uses_inclusive_taxes',
			15 => 'tax_name1',
			16 => 'tax_rate1',
			17 => 'tax_name2',
			18 => 'tax_rate2',
			19 => 'tax_name3',
			20 => 'tax_rate3',
			21 => 'is_amount_discount',
			22 => 'footer',
			23 => 'partial',
			24 => 'partial_due_date',
			25 => 'custom_value1',
			26 => 'custom_value2',
			27 => 'custom_value3',
			28 => 'custom_value4',
			29 => 'custom_surcharge1',
			30 => 'custom_surcharge2',
			31 => 'custom_surcharge3',
			32 => 'custom_surcharge4',
			33 => 'exchange_rate',
			34 => 'line_items',
		];
	}

	public static function import_keys()
	{
		return [
			0 => 'texts.invoice_number',
			1 => 'texts.user',
			2 => 'texts.amount',
			3 => 'texts.balance',
			4 => 'texts.client',
			5 => 'texts.status',
			6 => 'texts.deleted',
			7 => 'texts.discount',
			8 => 'texts.po_number',
			9 => 'texts.date',
			10 => 'texts.due_date',
			11 => 'texts.terms',
			12 => 'texts.public_notes',
			13 => 'texts.private_notes',
			14 => 'texts.uses_inclusive_taxes',
			15 => 'texts.tax_name1',
			16 => 'texts.tax_rate',
			17 => 'texts.tax_name',
			18 => 'texts.tax_rate',
			19 => 'texts.tax_name',
			20 => 'texts.tax_rate',
			21 => 'texts.is_amount_discount',
			22 => 'texts.footer',
			23 => 'texts.partial',
			24 => 'texts.partial_due_date',
			25 => 'texts.custom_value1',
			26 => 'texts.custom_value2',
			27 => 'texts.custom_value3',
			28 => 'texts.custom_value4',
			29 => 'texts.surcharge',
			30 => 'texts.surcharge',
			31 => 'texts.surcharge',
			32 => 'texts.surcharge',
			33 => 'texts.exchange_rate',
			34 => 'texts.items',
		];
	}
}