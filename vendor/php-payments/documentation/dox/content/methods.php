<div class="page-header">
	<h1>PHP-Payments supports many gateways and payment methods</h1>
</div>
	<div class="row-fluid">
		<div class="span8">

<h2>All Methods with Example Params</h2>

<p>Please note that not all drivers implement each of these methods.  In addition, the params available in each method may not be used in a particular driver's implementation.  If you use a param in a request which is not used, it will appear in your error log, but will not create any ill effects in the request - it simply will not be sent to the gateway.</p>

<?php
$dir = dirname(dirname(dirname(__DIR__))).'/lib/';
$mdir = $dir.'payment_methods/';
include $dir.'payment_method.php';
foreach(scandir($mdir) as $k=>$v)
{
	if($v[0] != '.')
	{
		include $mdir.$v;
		$class = str_replace('.php', '', $v);
		$ex = explode('_', $class);
		foreach($ex as $k=>$v)
		{
			$ex[$k] = ucfirst($v);
		}
		$class_name = implode("_", $ex);
		$class_instance = new $class();

	?>
		<h3><?php echo $class;?></h3>
		<h4>Parameters:</h4>
		<table class="table table-striped table-bordered table-condensed">
			<thead>
				<tr>
					<th>#</th>
					<th>Parameter</th>
					<th>Example</th>
				</tr>
			</thead>
			<tbody>
	<?php
		$pcount = 0;
		foreach($class_instance->get_params() as $pk=>$pv)
		{
			++$pcount;
	?>	
				<tr>
					<td><?php echo $pcount;?></td>
					<td><?php echo $pk;?></td>
					<td><?php echo $pv;?></td>
				</tr>
	<?php
		}
	?>
			</tbody>
		</table>
	<?php
	}
}
?>
</div>