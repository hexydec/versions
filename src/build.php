<?php
declare(strict_types = 1);
namespace hexydec\versions;

require __DIR__.'/autoload.php';

// directories
$root = \dirname(__DIR__);

// create object
$obj = new browsers([
	'cache' => \in_array('--cache', $argv) ? $root.'/cache/' : null
]);
$obj->build($root.'/dist/versions.json', \in_array('--rebuild', $argv));