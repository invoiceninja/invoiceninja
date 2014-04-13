<?php
/*
 * Copyright (C) 2007 Google Inc.
 * 
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * 
 *      http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Classes used to generate signature for an HTML shopping cart
 */

  /**
   * Generates a shopping cart signature for an HTML shopping cart
   */
  class HtmlSignatureGen {
	var $html_param_names = array();
	var $html_param_values = array();
	var $merchant_key;
	var $param_string;
	var $signature;
	
	//Parameter names not to be used when generating the signature
	var $excluded_param_names = array('_charset_', 'analyticsdata', 'urchindata');

	//This function adds parameters to an array
	function AddCartParameter($param_name, $param_value) {
		//Trim white spaces from beginning and end of parameter name and value
		$param_name = trim($param_name, ' ');
		$param_value = trim($param_value, ' ');
		
		if(in_array($param_name, $this->excluded_param_names) == TRUE) {
			//Ignore this excluded parameter
		}
		else {
			//$this->html_params[] = array('name' => $param_name, 'value' => $param_value);
			$this->html_param_names[] = $param_name;
			$this->html_param_values[] = $param_value;
		}
	}

	private function CalcHmacSha1($data) {
		$key = $this->merchant_key;
		$blocksize = 64;
		$hashfunc = 'sha1';
		if (strlen($key) > $blocksize) {
			$key = pack('H*', $hashfunc($key));
		}
		$key = str_pad($key, $blocksize, chr(0x00));
		$ipad = str_repeat(chr(0x36), $blocksize);
		$opad = str_repeat(chr(0x5c), $blocksize);
		$hmac = pack(
			'H*', $hashfunc(
				($key^$opad).pack(
					'H*', $hashfunc(
						($key^$ipad).$data
					)
				)
			)
		);
		echo $hmac;
		echo("\n");
		return $hmac; 
	}

	//This function generates and returns the signature
	function GetSignature() {
		//Sort parameters in ascending alphabetical order (first by name, then by value)
		array_multisort($this->html_param_names, $this->html_param_values);
		//Create parameter string
		for($i=0; $i<sizeof($this->html_param_names); $i++) {
			$param_string .= urlencode($this->html_param_names[$i]).'='.urlencode($this->html_param_values[$i]);
			if($i+1 < sizeof($this->html_param_names)){
				$param_string .='&';
			}
		}
		echo($param_string);
		echo("\n");
		$signature = base64_encode($this->CalcHmacSha1($param_string));
		return $signature;
	}
  }
?>