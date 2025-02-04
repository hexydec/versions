<?php
declare(strict_types=1);

require \dirname(__DIR__).'/vendor/autoload.php';

\spl_autoload_register(function (string $class) : void {
	$classes = [
		'hexydec\\versions\\browsers' => __DIR__.'/browsers.php'
	];
	if (isset($classes[$class])) {
		require $classes[$class];
	}
});