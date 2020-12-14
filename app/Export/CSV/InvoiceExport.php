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

namespace App\Export\CSV

use App\Models\Company;

class InvoiceExport
{
	private $company;

	public function __construct(Company $company)
	{
		$this->company = $company;
	}

	public function export()
	{
		$fileName = 'test.csv';
		
		$data = $this->company->invoices->get();

        return Excel::create($fileName, function ($excel) use ($data) {
            $excel->sheet('', function ($sheet) use ($data) {
                $sheet->loadView('export', $data);
            });
        })->download('csv');

	}
}