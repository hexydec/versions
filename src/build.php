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
	var_dump('Found '.\count($data).' Chrome versions found');
	\file_put_contents($root.'/dist/chrome-versions.json', \json_encode($data));
}

// firefox
if (($data = $obj->getFirefoxVersions($cache)) !== false) {
	var_dump('Found '.\count($data).' Firefox versions found');
	\file_put_contents($root.'/dist/firefox-versions.json', \json_encode($data));
}

// edge
if (($data = $obj->getEdgeVersions($cache)) !== false) {
	var_dump('Found '.\count($data).' Edge versions found');
	\file_put_contents($root.'/dist/edge-versions.json', \json_encode($data));
}

// legacy edge
if (($data = $obj->getLegacyEdgeVersions($cache)) !== false) {
	var_dump('Found '.\count($data).' Legacy Edge versions found');
	\file_put_contents($root.'/dist/legacy-edge-versions.json', \json_encode($data));
}

// safari
if (($data = $obj->getSafariVersions($cache)) !== false) {
	var_dump('Found '.\count($data).' Safari versions found');
	\file_put_contents($root.'/dist/safari-versions.json', \json_encode($data));
}

// internet explorer
if (($data = $obj->getInternetExplorerVersions($cache)) !== false) {
	var_dump('Found '.\count($data).' Internet Explorer versions found');
	\file_put_contents($root.'/dist/internet-explorer-versions.json', \json_encode($data));
}