<?php

$output_dir = dirname(__DIR__).'/html/';
$content_dir = (__DIR__).'/content/';
$template = (__DIR__).'/template.php';
$files = scandir($content_dir);

foreach($files as $file)
{
	if($file[0] !== '.')
	{
		$output_file = $output_dir . str_replace('.php', '.html', $file);
		exec("php $template $file > $output_file");
	}
}
