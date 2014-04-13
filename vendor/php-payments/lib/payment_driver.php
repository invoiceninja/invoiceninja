<?php

abstract class Payment_Driver 
{
	abstract public function __construct($config);

	/**
	 * Call Magic Method Must Be Implemented
	*/
	abstract public function __call($method, $params);

	/**
	 * Maps Methods to Details Particular to Each Request for that Method
	 */
	abstract public function method_map();

	/**
	 * Builds the Request
	 */
	abstract protected function _build_request($params);

	/**
	 * Parse the Response and then Delegate to the Response Object
	 */
	abstract protected function _parse_response($response);

	/**
	 * Returns a List of Drivers
	*/
	public static function get_drivers()
	{
		$drivers = array();

		/*
		* Humanize the Driver Name
		*/
		function humanize($segs)
		{
			$prepped = array();
			foreach($segs as $v) $prepped[] = ucfirst($v);
			return trim(ucfirst(str_replace('Driver', '', implode(' ', $prepped))));
		}

		/*
		* Driverize the Driver Name
		*/
		function driverize($segs)
		{
			foreach($segs as &$v) ucfirst($v);
			return str_replace('_driver', '', implode('_', $segs));
		}

		foreach(scandir(__DIR__.'/payment_drivers') as $driver)
		{
			if($driver[0] != '.')
			{
				$ex = explode('_', str_replace('.php', '', $driver));
				$drivers[driverize($ex)] = humanize($ex);
			}
		}

		return $drivers;
	}
}
