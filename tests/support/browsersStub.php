<?php
declare(strict_types = 1);
namespace hexydec\versions;

/**
 * Test double for browsers that replaces every source-specific getter with a configurable stub, so
 * build()'s merge/sort/write orchestration can be exercised without touching the network at all.
 * Any getter that isn't explicitly stubbed behaves as if the source could not be reached.
 */
class browsersStub extends browsers {

	protected array $stub = [];

	public function stub(string $method, array|false $result) : static {
		$this->stub[$method] = $result;
		return $this;
	}

	protected function result(string $method) : array|false {
		return $this->stub[$method] ?? false;
	}

	protected function getChromeVersions(bool $rebuild = false) : array|false {
		return $this->result(__FUNCTION__);
	}

	protected function getFirefoxVersions(bool $rebuild = false) : array|false {
		return $this->result(__FUNCTION__);
	}

	protected function getEdgeVersions(bool $rebuild = false) : array|false {
		return $this->result(__FUNCTION__);
	}

	protected function getSafariVersions(bool $rebuild = false) : array|false {
		return $this->result(__FUNCTION__);
	}

	protected function getInternetExplorerVersions() : array|false {
		return $this->result(__FUNCTION__);
	}

	protected function getOperaVersions(bool $rebuild = false) : array|false {
		return $this->result(__FUNCTION__);
	}

	protected function getBraveVersions(bool $rebuild = false) : array|false {
		return $this->result(__FUNCTION__);
	}

	protected function getVivaldiVersions(bool $rebuild = false) : array|false {
		return $this->result(__FUNCTION__);
	}

	protected function getMaxthonVersions(bool $rebuild = false) : array|false {
		return $this->result(__FUNCTION__);
	}

	protected function getSamsungInternetVersions(bool $rebuild = false) : array|false {
		return $this->result(__FUNCTION__);
	}

	protected function getHuaweiBrowserVersions(bool $rebuild = false) : array|false {
		return $this->result(__FUNCTION__);
	}

	protected function getKmeleonVersions(bool $rebuild = false) : array|false {
		return $this->result(__FUNCTION__);
	}

	protected function getKonquerorVersions(bool $rebuild = false) : array|false {
		return $this->result(__FUNCTION__);
	}

	protected function getUcBrowserVersions(bool $rebuild = false) : array|false {
		return $this->result(__FUNCTION__);
	}

	protected function getWaterfoxVersions(bool $rebuild = false) : array|false {
		return $this->result(__FUNCTION__);
	}

	protected function getPalemoonVersions(bool $rebuild = false) : array|false {
		return $this->result(__FUNCTION__);
	}

	protected function getOculusBrowserVersions(bool $rebuild = false) : array|false {
		return $this->result(__FUNCTION__);
	}

	protected function getMidoriVersions(bool $rebuild = false) : array|false {
		return $this->result(__FUNCTION__);
	}
}
