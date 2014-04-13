<?php

class Test
{
	/*
	 * Where is PHP Payments Located?
	*/
	public $dir;
	
	/*
	 * Constructor
	*/
	public function __construct($tests = array())
	{
		//include PHP Payments
		$this->dir = dirname(__DIR__);
		include($this->dir."/lib/payments.php");

		//Include class helpers.  These are all static and do not require instantiation.
		foreach(scandir('classes') as $class)
		{
			if($class[0] !== '.') include "classes/$class";
		}
	}

	/*
	 * Run a Single Test
	*/
	public function single($test, $args = array())
	{
		include "tests/$test.php";
		$class_name = 'Test_'.ucfirst(str_replace('.php', '', $test));
		$test_instance = new $class_name($this->dir, $args);
		$test_instance->run();
	}

	/*
	 * Run All Tests
	*/
	public function all()
	{
		foreach(scandir('tests') as $test)
		{
			if($test[0] !== '.' && !is_dir($this->dir.'/tests/tests/'.$test)) 
			{
				include "tests/$test";
				$class_name = 'Test_'.ucfirst(str_replace('.php', '', $test));
				$test_instance = new $class_name($this->dir);
				$test_instance->run();
			}
		}
	}

	public function destruct()
	{
		echo "Thankyou for choosing PHP-Payments! Please send questions, comments or donations (via PayPal) to calvinfroedge@gmail.com.  If you find a bug, please post it in the issues section of the Git repository: https://github.com/calvinfroedge/PHP-Payments  \n \n";
	}
}

$test = new Test();

//The 0th element is simply the filename.  Get rid of it and reindex the array.
unset($argv[0]);
$argv = array_values($argv);

if(empty($argv))
{
	$test->all();
}
else
{
	$test_name = $argv[0]; //This should be the name of the test to run
	unset($argv[0]);
	$test->single($test_name, $argv); //Passing rest of the params to a specific test to set it's options (if any)
}