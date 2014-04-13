<div class="page-header">
	<h1>PHP-Payments was built to allow you to use one API for handling many payment integrations.</h1>
</div>
	<div class="row-fluid">
		<div class="span8">
			<h2>Five Lines of Code to Integrate</h2>
			<pre>
&lt;?php
include('/path/to/payments/payments.php');

$p = new PHP_Payments;

$config = Payment_Utility::load('config', '/path/to/your/gateway/config'); 
$params = array('cc_number' =&gt; 4111111111111111, 'amt' =&gt; 35.00, 'cc_exp' =&gt; '022016', 'cc_code' =&gt; '203');

$response = $p-&gt;oneoff_payment('name_of_payment_driver', $params, $config);
			</pre>

			<div class="row-fluid">
				<div class="span6">
					<h2>Framework Agnostic</h2>
					<p>Though the project began as a CodeIgniter spark, PHP-Payments is now a framework agnostic API.  Writing an interface to bring it to your framework is easy, and your framework could already be supported.  Check out the <a href="/frameworks.html">frameworks page</a> for more info.</p>
				</div>
				<div class="span6">
					<h2>Get PHP-Payments</h2>
					<p>Hot and fresh.</p>
					<a href="https://github.com/calvinfroedge/PHP-Payments/zipball/master" class="btn btn-large">Download .zip</a>						
					<a href="https://github.com/calvinfroedge/PHP-Payments" class="btn btn-large">Fork Me on Github</a>						
				</div>
			</div>
		</div>
