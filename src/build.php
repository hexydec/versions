<?php
declare(strict_types = 1);
namespace hexydec\versions;

require __DIR__.'/autoload.php';

// directories
$root = \dirname(__DIR__);
$cache = $root.'/cache/';
$dist = $root.'/dist/';
if (!\is_dir($dist)) {
	\mkdir($dist, 0755);
}

// create object
$obj = new generate();

// chrome
if (($data = $obj->getChromeVersions($cache)) !== false) {
	\file_put_contents($root.'/dist/chrome-versions.json', \json_encode($data));
}

// firefox
if (($data = $obj->getFirefoxVersions($cache)) !== false) {
	\file_put_contents($root.'/dist/firefox-versions.json', \json_encode($data));
}
