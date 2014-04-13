<?php

class Test_Driver
{
	public $payments;

	/*
	 * Class name of the driver being tested
	*/
	public $class_name;

	/*
	 * Instance of the driver being tested
	*/
	public $class;

	/*
	 * The configuration array used
	*/
	public $config;
    
	/*
	 * The methods available, each of which will be tested
	*/
	public $methods_available;

	/*
	 * The Constructor
	*/
	public function __construct($driver)
	{
		$config = array(
			'mode' => 'test'
		);

		echo "\n \n Starting test for $driver \n \n";
		$this->payments = new PHP_Payments($config);

		$test_config = include('.drivers.test_vals.php');

		$class_name = str_replace('.php', '', $driver);
		$uc = explode("_", $class_name);
		foreach($uc as $k=>$piece)
		{
			$uc[$k] = ucfirst($piece);
		}
		$class_name_uc = implode("_", $uc);

		$this->class_name = $class_name_uc;
		$config_name = str_replace('_driver', '', $class_name);
		$loaded_config = Payment_Utility::load('config', 'drivers/'.$config_name);

		$this->config = array_merge($config, $test_config);
	
		$this->class = new $class_name_uc(array_merge($config, $loaded_config));

		$this->methods_available = $this->class->method_map();		
	}

	/*
	 * Run the test
	*/
	public function run()
	{
		//Reset the identifier
		$last_identifier = '';
		
		foreach($this->methods_available as $method=>$method_array)
		{
			$required = $method_array['required'];
	
			$args = array();

			$break = false;
			foreach($required as $r)
			{
				if(isset($this->config[$this->class_name][$r])) 
				{
					$args[$r] = $this->config[$this->class_name][$r];
				}
				else if(isset($this->config['all'][$r]))
				{
					$args[$r] = $this->config['all'][$r];
				}

				if($r == 'identifier')
				{
					if(empty($last_identifier))
					{
						Test_Errors::set_error($this->class_name, $method, '000', 'An identifier was not retrieved in the previous transaction, but is required for '.$method.', so failure is certain.', 'No further details available.');
						$break = true;
					}
					else
					{
						$args['identifier'] = $last_identifier;
					}
				}
			}

			//If an error was already found, don't bother calling the method
			if($break == true) { continue;}

			$result = $this->class->$method($args);

			if($result->response_code != 100)
			{
				Test_Errors::set_error($this->class_name, $method, $result->response_code, $result->response_message, $result->details);
			}

			if(isset($result->details->identifier))
			{
				$last_identifier = $result->details->identifier;
			}
		}
	}

	/*
	 * Echo the results of a test to a screen / console
	*/
	public function result()
	{
		if(count(Test_Errors::$errors) > 0)
		{
			echo "Your test resulted in the following Test_Errors \n \n";

			$e = Test_Errors::$errors;

			foreach($e as $k=>$v)
			{
				echo "Attempt to perform the ".$e[$k]->method." method on class ".$e[$k]->class." was unsuccessful.  The following details may help you in debugging this: \n \n";
			
				echo "The response code: ".$e[$k]->response_code." \n \n";
			
				echo "The response message: ".$e[$k]->response_message." \n \n";

				echo "The details of the response: \n \n";

				var_dump($e[$k]->details);

				echo " \n \n";

				if(isset($e[$k]->errors_warnings)) echo "Additionally, the following errors and warnings were observed:";

				echo " \n \n";
			}
		}
	}


	/*
	 * Shutdown Routine
	*/
	public function __destruct()
	{
		$this->result();
	}
}