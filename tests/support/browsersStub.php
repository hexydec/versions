<?php
declare(strict_types = 1);
namespace hexydec\versions;

/**
 * Test double for browsers that replaces the result of every source with a configurable stub, so
 * build()'s merge/sort/write orchestration can be exercised without touching the network at all.
 * Any source that isn't explicitly stubbed behaves as if it could not be reached. The sources are
 * taken from the parent, so a browser added there needs no change here.
 */
class browsersStub extends browsers {

	/**
	 * @var array<string,array<string,int>|false> $stub A map of browser name to the result its source should return
	 */
	protected array $stub = [];

	/**
	 * Registers the result that the given browser's source should return
	 *
	 * @param string $browser The name of the browser to stub, as it is keyed in getSources()
	 * @param array<string,int>|false $result The result the source should return
	 * @return static This object, for chaining
	 */
	public function stub(string $browser, array|false $result) : static {
		$this->stub[$browser] = $result;
		return $this;
	}

	/**
	 * Replaces each of the parent's sources with a callback returning its stubbed result
	 *
	 * @param array<string,array<string,int>> $data The versions collected so far, which the stub has no use for
	 * @return array<string,callable> An array of browser name to the callback that returns its stubbed result
	 */
	protected function getSources(array $data) : array {
		$sources = [];
		foreach (\array_keys(parent::getSources($data)) AS $browser) {
			$sources[$browser] = fn (bool $rebuild) : array|false => $this->stub[$browser] ?? false;
		}
		return $sources;
	}
}
