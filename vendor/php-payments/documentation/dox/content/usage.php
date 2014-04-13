<div class="page-header">
	<h1>PHP-Payments supports x gateways and x methods</h1>
</div>
	<div class="row-fluid">
		<div class="span8">
<h2>Basic Usage</h2>

<pre>
include('/path/to/lib/payments.php');

$payments = new PHP_Payments;

$config = Payment_Utility::load('config', '/path/to/config/file'); //You can do this or you can make this an array.  Config file is recommended since configuration needs differ by gateway.

//Make the call
$response = $payments-&gt;payment_action('gateway_name', $params, $config);
</pre>

<h2>Responses</h2>

<p>There are two types of responses returned, local responses and gateway responses. If a method is not supported, required params are missing, a gateway does not exist, etc., a local response will be returned. This prevents the transaction from being sent to the gateway and the gateway telling you 3 seconds later there is something wrong with your request.</p>

<p>Accessing response properties looks like this:</p>

<pre>
$status = $response-&gt;status;
</pre>

<p>Gateway responses will usually have a full response from the gateway, and on failure a 'reason' property in the details object:</p>

<pre>
$gatway_response = $response-&gt;details;

$failure_reason = $response-&gt;reason;
</pre>

</div>