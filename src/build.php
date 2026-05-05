<?php
declare(strict_types = 1);
namespace hexydec\versions;

require __DIR__.'/autoload.php';

// load local environment variables if present
$envfile = \dirname(__DIR__).'/env.php';
if (\file_exists($envfile)) {
	require $envfile;
}

// directories
$root = \dirname(__DIR__);

// create object
$obj = new browsers([
	'cache' => \in_array('--cache', $argv) ? $root.'/cache/' : null,
	'githubtoken' => $_ENV['GITHUB_TOKEN'] ?? \getenv('GITHUB_TOKEN') ?: null
]);
$obj->build($root.'/dist/versions.json', \in_array('--rebuild', $argv));
