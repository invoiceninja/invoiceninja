## This code has taken tons of work.  Donations highly appreciated.  [Make a Donation](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=TJMWX5E9GXS7S "Make a Donation to Codeigniter Payments")

# PHP Payments

## NOTICE - USING PHP-PAYMENTS ALONE DOES NOT MAKE YOU PCI COMPLIANT

It is highly recommended that you attempt to architect your application to achieve some level of PCI compliance.  Without this, the applications you create can be vulnerable to fines for PCI compliance violations.  Using PHP-Payments does not circumvent the need for you to do this.  You can check out the PCI compliance self assessment form here: https://www.pcisecuritystandards.org/merchants/self_assessment_form.php

## Installing

There are config files for each gateway in the /config folder of the package.  You need to enter your own API usernames and passwords (the ones in there are mine, used only for testing purposes) in the config of each gateway you would like to use.

## Testing

1.  If you want to test locally (and you should), you need to set "force_secure_connection" to FALSE in config/payments.php

2.  By default, test api endpoints will be used.  To enable production endpoints, change the mode in /config/payments.php from 'test' to 'production' or pass a 'mode' parameter in the config param when you instantiate PHP payments (ie $p = new PHP-Payments(array('mode' => 'test'));.  Note that if you are a Psigate customer, you must obtain your production endpoint from Psigate support.

3.  It's highly advised that you run tests before trying to go live.  The automated testing utility in the tests folder can help you do this.  To use tests:

- To run all tests, cd into tests and type test.php
- To test all payment drivers, cd into tests and type test.php drivers
- To test a specific payment driver, cd into tests and type test.php drivers driver (Where 'driver' is a driver name, such as stripe, beanstream or paypal_payments_pro)

Alternatively, you could visit test.php in a web browser to run all tests.  We may introduce enhanced browser based testing in the future - but for not the preferred usage is from command line.

## Documentation

To avoid constantly updating documentation to match code, we've opted for automating as much documentation as possible.  To view the current documentation, cd into the documentation folder, then cd into dox and type doxgen.php in the command line.  You can alternatively open this file in a web browser.

When you run doxgen, documentation will appear in documentation/HTML which you can load in your web browser.

## Gateway, Method and Parameter Support

As this is constantly changing, please run doxgen.php in the dox folder to generate up to date documentation.  At time of writing the README, PHP-Payments supports a dozen gateways.  However it should be mentioned the PHP-Payments currently supports two types of gateways - "Button" gateways and regular "Server to Server" gateways.  Button gateways may have server to server operations, but for things like payment methods (such as oneoff_payment_button) generate HTML code for a button.  This HTML code is retrieved from the detail property of a PHP-Payments response object.

## Responses

There are two types of responses returned, local responses and gateway responses.  If a method is not supported, required params are missing, a gateway does not exist, etc., a local response will be returned.  This prevents the transaction from being sent to the gateway and the gateway telling you 3 seconds later there is something wrong with your request.:

```php
'type'				=>	'local_response',  //Indicates failure was local
'status' 			=>	$status, //Either success or failure
'response_code' 	=>	$this->_response_codes[$response], 
'response_message' 	=>	$this->_response_messages[$response],
'details'			=>	$response_details
```
Access response properties by naming your call something like this:

```php
$response = $payments->payment_action('gateway_name', $params); 
```

Then you can do:

```php
$status = $response->status;
```

Gateway responses will usually have a full response from the gateway, and on failure a 'reason' property in the details object:

```php
'type'				=>	'gateway_response',
'status' 			=>	$status, 
'response_code' 	=>	$this->_response_codes[$response], 
'response_message' 	=>	$this->_response_messages[$response],
'details'			=>	$details
```

You can access this like $response->details->reason.  You may want to save the full gateway response (it's an array) in a database table, you can access it at $response->details->gateway_response

## LICENSE

Copyright (c) 2011-2012 Calvin Froedge

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
