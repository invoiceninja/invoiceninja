<?php

class Test_Errors
{
	public static $errors = array();
  	
	public static function set_error($class, $method, $code, $message, $details)
    {
          $error = array(
              'class' => $class,
              'method' => $method,
              'response_code' => $code,
              'response_message' => $message,
              'details' => $details
          );
          
          self::$errors[] = (object) $error;
	}
}