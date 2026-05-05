<?php
declare(strict_types = 1);
use hexydec\versions\browsers;

class browsersPublic extends browsers {

	public function getChromeVersions(bool $rebuild = false, array $existing = []) : array|false {
		return parent::getChromeVersions($rebuild, $existing);
	}

	public function getFirefoxVersions(bool $rebuild = false) : array|false {
		return parent::getFirefoxVersions($rebuild);
	}

	public function getLegacyEdgeVersions() : array|false {
		return parent::getLegacyEdgeVersions();
	}

	public function getEdgeVersions(bool $rebuild = false) : array|false {
		return parent::getEdgeVersions($rebuild);
	}

	public function getLegacySafariVersions() : array {
		return parent::getLegacySafariVersions();
	}

	public function getSafariVersions(bool $rebuild = false) : array|false {
		return parent::getSafariVersions($rebuild);
	}

	public function getInternetExplorerVersions() : array {
		return parent::getInternetExplorerVersions();
	}

	public function getOperaVersions(bool $rebuild = false) : array|false {
		return parent::getOperaVersions($rebuild);
	}

	public function getBraveVersions(bool $rebuild = false) : array|false {
		return parent::getBraveVersions($rebuild);
	}

	public function getVivaldiVersions(bool $rebuild = false) : array|false {
		return parent::getVivaldiVersions($rebuild);
	}

	public function getMaxthonVersions(bool $rebuild = false) : array|false {
		return parent::getMaxthonVersions($rebuild);
	}

	public function getSamsungInternetVersions(bool $rebuild = false) : array|false {
		return parent::getSamsungInternetVersions($rebuild);
	}

	public function getHuaweiBrowserVersions(bool $rebuild = false) : array|false {
		return parent::getHuaweiBrowserVersions($rebuild);
	}

	public function getUcBrowserVersions(bool $rebuild = false) : array|false {
		return parent::getUcBrowserVersions($rebuild);
	}

	public function getKmeleonVersions(bool $rebuild = false) : array|false {
		return parent::getKmeleonVersions($rebuild);
	}

	public function getKonquerorVersions(bool $rebuild = false) : array|false {
		return parent::getKonquerorVersions($rebuild);
	}

	public function getWaterfoxVersions(bool $rebuild = false) : array|false {
		return parent::getWaterfoxVersions($rebuild);
	}

	public function getPalemoonVersions(bool $rebuild = false) : array|false {
		return parent::getPalemoonVersions($rebuild);
	}

	public function getOculusBrowserVersions(bool $rebuild = false) : array|false {
		return parent::getOculusBrowserVersions($rebuild);
	}

	public function getMidoriVersions(bool $rebuild = false) : array|false {
		return parent::getMidoriVersions($rebuild);
	}
}
