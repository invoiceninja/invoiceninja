<?php

class Test_Drivers
{	
	/*
	 * The Drivers to Test
	*/
	public $drivers = array(); 

	/*
	 * Constructor
	*/
	public function __construct($dir, $drivers = array())
	{
		if(empty($drivers))
		{
			$this->drivers = scandir($dir."/lib/payment_drivers");
		}
		else
		{
			foreach($drivers as $k=>$v)
			{
				if(file_exists($dir."/lib/payment_drivers/$v"."_driver.php"))
				{
					array_push($this->drivers, $v."_Driver");
				}
				else
				{
					error_log("$v driver does not exist");
				}
			}
		}

		include('drivers/driver.php');
	}

	/*
	 * Run
	*/
	public function run()
	{
		foreach($this->drivers as $driver)
		{	
			if($driver[0] !== '.')
			{
				$driver_instance = new Test_Driver($driver);
				$driver_instance->run();
			}
		}
	}
}
