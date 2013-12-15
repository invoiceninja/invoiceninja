<?php

class ReportController extends \BaseController {

	public function monthly()
	{
		$records = DB::table('invoices')
					->select(DB::raw('sum(total) as total, month(invoice_date) as month'))
					->where('invoices.deleted_at', '=', null)
					->where('invoices.invoice_date', '>', 0)
					->groupBy('month');
					
		$totals = $records->lists('total');
		$dates = $records->lists('month');		
		$data = array_combine($dates, $totals);
		
		$startDate = date_create('2013-06-30');
		$endDate = date_create('2013-12-30');
		$endDate = $endDate->modify('+1 month'); 
		$interval = new DateInterval('P1M');
		$period = new DatePeriod($startDate, $interval, $endDate);

		$totals = [];
		$dates = [];

		foreach ($period as $d)
		{
			$date = $d->format('Y-m-d');
			$month = $d->format('n');

			$dates[] = $date;
			$totals[] = isset($data[$month]) ? $data[$month] : 0;
		}
		
		$width = (ceil( max($totals) / 100 ) * 100) / 10;  

		$params = [
			'dates' => $dates,
			'totals' => $totals,
			'scaleStepWidth' => $width,
		];
		
		return View::make('reports.monthly', $params);
	}

}