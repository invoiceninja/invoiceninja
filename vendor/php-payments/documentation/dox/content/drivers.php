<div class="page-header">
	<h1>Gateway specific method support in PHP-Payments</h1>
</div>
	<div class="row-fluid">
		<div class="span8">

<?php
$dir = dirname(dirname(dirname(__DIR__))).'/lib/';
$ddir = $dir.'payment_drivers/';
include $dir.'payment_driver.php';
include $dir.'payment_utility.php';
?>
<ul>
<?php

$classes = array();

foreach(scandir($ddir) as $k=>$v)
{
	if($v[0] != '.')
	{
		include $ddir.$v;
		$class = str_replace('.php', '', $v);
		$ex = explode('_', $class);
		foreach($ex as $k=>$v)
		{
			$ex[$k] = ucfirst($v);
		}
		$class_name = implode("_", $ex);
		$classes[$class] = $class_name;
	?>
	<li><a href="#<?php echo $class;?>"><?php echo $class_name;?></a></li>
	<?php
	}
}
?>
</ul>

<?php
foreach($classes as $class_key=>$class_name)
{
?>
<div id="<?php echo $class_key;?>">
<h2><?php echo $class_name;?></h2>
<?php
	$i = @new $class_name(array());
	$methods = @$i->method_map();
	foreach($methods as $method=>$p)
	{
?>
		<h3><?php echo $method;?></h3>
		<h4>Requred Params:</h4>
		<ul>
<?php
		foreach($p['required'] as $k=>$v)
		{
?>
			<li><?php echo $v;?></li>
<?php
		}
?>
		</ul>
<?php
		if(isset($p['keymatch']))
		{
		?>

		<h4>Available Params:</h4>
		<ul>
		<?php
			foreach($p['keymatch'] as $k=>$v)
			{
?>
				<li><?php echo $k;?></li>
<?php
			}
			?>
		</ul>
			<?php
		}
	}
?>
</div>
<?php
}
?>
</div>