<?php
	$page = $argv[1];
	include('template/head.php');
	include('template/header.php');
?>
	<div class="container-fluid">
<?php
	$page_include = (__DIR__).'/content/'.$page;
	include($page_include);
	include('template/sidebar.php');
?>
	</div>
<?php
	include('template/footer.php');
