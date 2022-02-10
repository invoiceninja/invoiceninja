<?php
/**
 * client Ninja (https://clientninja.com).
 *
 * @link https://github.com/clientninja/clientninja source repository
 *
 * @copyright Copyright (c) 2021. client Ninja LLC (https://clientninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Import\Transformer\Waveaccounting;

use App\Import\ImportException;
use App\Import\Transformer\BaseTransformer;


/**
 * Class ExpenseTransformer.
 */
class ExpenseTransformer extends BaseTransformer {
	/**
	 * @param $line_items_data
	 *
	 * @return bool|array
	 */
	public function transform( $data ) {

		$transformed = [
			'company_id'  => $this->company->id,
			'vendor_id'   => $this->getVendorId($vendor_name = $this->getString($data, 'vendor')),
			'number' 	  => $this->getString($data, 'invoice_number'),
			'public_notes'=> $this->getString($data, 'description'),
			'date'        => date( 'Y-m-d', strtotime( $data['bill_date'] ) ) ?: now()->format('Y-m-d'), //27-01-2022
			'currency_id' => $this->getCurrencyByCode( $data, 'currency' ),
			'category_id' => $this->getOrCreateExpenseCategry($data['account']),
			'amount'	  => $this->getFloat($data['quantity']) * $this->getFloat($data['amount']),
			'tax_name1'   => $this->getTaxName($data['taxes']),
			'tax_rate1'	  => $this->getTaxRate($data['taxes']),
		];


		return $transformed;
	}
}