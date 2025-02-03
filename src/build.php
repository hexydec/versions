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
	$all['ie'] = $data;
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

// maxthon
if (($data = $obj->getMaxthonVersions($cache)) !== false) {
	$all['maxthon'] = $data;
	var_dump('Found '.\count($data).' Maxthon versions found');
	\file_put_contents($root.'/dist/maxthon-versions.json', \json_encode($data));
}

// samsung
if (($data = $obj->getSamsungInternetVersions($cache)) !== false) {
	$all['samsung'] = $data;
	var_dump('Found '.\count($data).' Samsung Internet versions found');
	\file_put_contents($root.'/dist/samsung-internet-versions.json', \json_encode($data));
}

// samsung
if (($data = $obj->getHuaweiBrowserVersions($cache)) !== false) {
	$all['huawei'] = $data;
	var_dump('Found '.\count($data).' Huawei Browser versions found');
	\file_put_contents($root.'/dist/huawei-browser-versions.json', \json_encode($data));
}

// kmeleon
if (($data = $obj->getKmeleonVersions($cache)) !== false) {
	$all['kmeleon'] = $data;
	var_dump('Found '.\count($data).' K-Meleon versions found');
	\file_put_contents($root.'/dist/kmeleon-versions.json', \json_encode($data));
}

// all
\file_put_contents($root.'/dist/versions.json', \json_encode($all));