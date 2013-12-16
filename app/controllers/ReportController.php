<?php

class ReportController extends \BaseController {

	public function report()
	{
		if (Input::all())
		{
			$groupBy = Input::get('group_by');
			$chartType = Input::get('chart_type');
			$startDate = date_create(Input::get('start_date'));
			$endDate = date_create(Input::get('end_date'));
		}
		else
		{
			$groupBy = 'MONTH';
			$chartType = 'Bar';
			$startDate = date_create()->modify('-3 month');
			$endDate = date_create();
		}

		$padding = $groupBy == 'DAYOFYEAR' ? 'day' : ($groupBy == 'WEEK' ? 'week' : 'month');
		$endDate->modify('+1 '.$padding);
		$datasets = [];
		$labels = [];
		$maxTotals = 0;

		foreach ([ENTITY_INVOICE, ENTITY_PAYMENT, ENTITY_CREDIT] as $entityType)
		{
			$records = DB::table($entityType.'s')
						->select(DB::raw('sum(amount) as total, '.$groupBy.'('.$entityType.'_date) as '.$groupBy))
						->where($entityType.'s.deleted_at', '=', null)
						->where($entityType.'s.'.$entityType.'_date', '>=', $startDate->format('Y-m-d'))
						->where($entityType.'s.'.$entityType.'_date', '<=', $endDate->format('Y-m-d'))					
						->groupBy($groupBy);
						
			$totals = $records->lists('total');
			$dates = $records->lists($groupBy);		
			$data = array_combine($dates, $totals);
			
			$interval = new DateInterval('P1'.substr($groupBy, 0, 1));
			$period = new DatePeriod($startDate, $interval, $endDate);

			$totals = [];			

			foreach ($period as $d)
			{
				$dateFormat = $groupBy == 'DAYOFYEAR' ? 'z' : ($groupBy == 'WEEK' ? 'W' : 'n');				
				$date = $d->format($dateFormat);		
				$totals[] = isset($data[$date]) ? $data[$date] : 0;

				if ($entityType == ENTITY_INVOICE)  
				{
					$labelFormat = $groupBy == 'DAYOFYEAR' ? 'j' : ($groupBy == 'WEEK' ? 'W' : 'F');
					$label = $d->format($labelFormat);
					$labels[] = $label;
				}
			}

			$max = max($totals);

			if ($max > 0)
			{
				$datasets[] = [
					'totals' => $totals,
					'colors' => $entityType == ENTITY_INVOICE ? '78,205,196' : ($entityType == ENTITY_CREDIT ? '199,244,100' : '255,107,107')
				];
				$maxTotals = max($max, $maxTotals);
			}
		}

		$width = (ceil( $maxTotals / 100 ) * 100) / 10;  
		$width = max($width, 10);

		$dateTypes = [
			'DAYOFYEAR' => 'Daily',
			'WEEK' => 'Weekly',
			'MONTH' => 'Monthly'
		];

		$chartTypes = [
			'Bar' => 'Bar',
			'Line' => 'Line'
		];

		$params = [
			'labels' => $labels,
			'datasets' => $datasets,
			'scaleStepWidth' => $width,
			'dateTypes' => $dateTypes,
			'chartTypes' => $chartTypes,
			'chartType' => $chartType,
			'startDate' => $startDate->format('m/d/Y'),
			'endDate' => $endDate->modify('-1'.$padding)->format('m/d/Y'),
			'groupBy' => $groupBy
		];
		
		return View::make('reports.report_builder', $params);
	}
}