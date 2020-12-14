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
			0 => 'invoice.number',
			1 => 'invoice.user_id',
			2 => 'invoice.amount',
			3 => 'invoice.balance',
			4 => 'invoice.client_id',
			5 => 'invoice.status_id',
			6 => 'invoice.is_deleted',
			7 => 'invoice.discount',
			8 => 'invoice.po_number',
			9 => 'invoice.date',
			10 => 'invoice.due_date',
			11 => 'invoice.terms',
			12 => 'invoice.public_notes',
			13 => 'invoice.private_notes',
			14 => 'invoice.uses_inclusive_taxes',
			15 => 'invoice.tax_name1',
			16 => 'invoice.tax_rate1',
			17 => 'invoice.tax_name2',
			18 => 'invoice.tax_rate2',
			19 => 'invoice.tax_name3',
			20 => 'invoice.tax_rate3',
			21 => 'invoice.is_amount_discount',
			22 => 'invoice.footer',
			23 => 'invoice.partial',
			24 => 'invoice.partial_due_date',
			25 => 'invoice.custom_value1',
			26 => 'invoice.custom_value2',
			27 => 'invoice.custom_value3',
			28 => 'invoice.custom_value4',
			29 => 'invoice.custom_surcharge1',
			30 => 'invoice.custom_surcharge2',
			31 => 'invoice.custom_surcharge3',
			32 => 'invoice.custom_surcharge4',
			33 => 'invoice.exchange_rate',
			34 => 'invoice.line_items',
			35 => 'client.name',
			36 => 'client.email',
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