<?php
declare(strict_types = 1);
namespace hexydec\versions;

/**
 * Test double for browsers that replaces network access with a fixed map of URL => response, so
 * the scraping/parsing logic can be exercised without ever making a real HTTP request. Extends
 * browsersPublic so that the source specific getters can be called directly.
 */
class browsersFixture extends \browsersPublic {

	/**
	 * @var array<string,string|false> $responses A map of URL to the response fetch() should return for it
	 */
	protected array $responses = [];

	/**
	 * @var array<int,string> $requested The URLs requested via fetch(), in call order
	 */
	protected array $requested = [];

	/**
	 * Registers the response that fetch() should return for the given URL
	 *
	 * @param string $url The URL to register a response for
	 * @param string|false $response The response to return, or false to simulate a request that could not be fetched
	 * @return static This object, for chaining
	 */
	public function respond(string $url, string|false $response) : static {
		$this->responses[$url] = $response;
		return $this;
	}

	/**
	 * @return array<int,string> The URLs requested via fetch(), in call order
	 */
	public function requested() : array {
		return $this->requested;
	}

	/**
	 * Returns the registered response for the requested URL instead of fetching it
	 *
	 * @param string $url The URL to fetch
	 * @param bool $contents Whether to return the contents of the URL, which the fixture always does
	 * @param bool $rebuild Whether to bypass the local cache, which the fixture has no use for
	 * @param array<string,mixed> $options An array of HTTP stream context options, which the fixture ignores
	 * @return string|false The registered response for the URL
	 */
	protected function fetch(string $url, bool $contents = true, bool $rebuild = false, array $options = []) : string|false {
		$this->requested[] = $url;
		if (!\array_key_exists($url, $this->responses)) {
			throw new \RuntimeException('No fixture response was registered for "'.$url.'"');
		}
		return $this->responses[$url];
	}
}
