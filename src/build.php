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
$all = [];

// create object
$obj = new generate();

// chrome
if (($data = $obj->getChromeVersions($cache)) !== false) {
	$all['chrome'] = $data;
	var_dump('Found '.\count($data).' Chrome versions found');
	\file_put_contents($root.'/dist/chrome-versions.json', \json_encode($data));
}

// firefox
if (($data = $obj->getFirefoxVersions($cache)) !== false) {
	$all['firefox'] = $data;
	var_dump('Found '.\count($data).' Firefox versions found');
	\file_put_contents($root.'/dist/firefox-versions.json', \json_encode($data));
}

// edge
if (($data = $obj->getEdgeVersions($cache)) !== false) {
	$all['edge'] = $data;
	var_dump('Found '.\count($data).' Edge versions found');
	\file_put_contents($root.'/dist/edge-versions.json', \json_encode($data));
}

// safari
if (($data = $obj->getSafariVersions($cache)) !== false) {
	$all['safari'] = $data;
	var_dump('Found '.\count($data).' Safari versions found');
	\file_put_contents($root.'/dist/safari-versions.json', \json_encode($data));
}

// internet explorer
if (($data = $obj->getInternetExplorerVersions($cache)) !== false) {
	$all['internet explorer'] = $data;
	var_dump('Found '.\count($data).' Internet Explorer versions found');
	\file_put_contents($root.'/dist/internet-explorer-versions.json', \json_encode($data));
}

// brave
if (($data = $obj->getBraveVersions($cache)) !== false) {
	$all['brave'] = $data;
	var_dump('Found '.\count($data).' Brave versions found');
	\file_put_contents($root.'/dist/brave-versions.json', \json_encode($data));
}

// opera
if (($data = $obj->getOperaVersions($cache)) !== false) {
	$all['opera'] = $data;
	var_dump('Found '.\count($data).' Opera versions found');
	\file_put_contents($root.'/dist/opera-versions.json', \json_encode($data));
}

// vivaldi
if (($data = $obj->getVivaldiVersions($cache)) !== false) {
	$all['vivaldi'] = $data;
	var_dump('Found '.\count($data).' Vivaldi versions found');
	\file_put_contents($root.'/dist/vivaldi-versions.json', \json_encode($data));
}

// maxathon
if (($data = $obj->getMaxathonVersions($cache)) !== false) {
	$all['maxathon'] = $data;
	var_dump('Found '.\count($data).' Maxathon versions found');
	\file_put_contents($root.'/dist/maxathon-versions.json', \json_encode($data));
}

// all
\file_put_contents($root.'/dist/versions.json', \json_encode($all));