<?php
declare(strict_types = 1);
namespace hexydec\versions;

/**
 * Test double for browsers that replaces network access with a fixed map of URL => response, so
 * the scraping/parsing logic can be exercised without ever making a real HTTP request.
 */
class browsersFixture extends browsers {

	protected array $responses = [];
	protected array $requested = [];

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

	protected function fetch(string $url, bool $contents = true) : string|false {
		$this->requested[] = $url;
		if (!\array_key_exists($url, $this->responses)) {
			throw new \RuntimeException('No fixture response was registered for "'.$url.'"');
		}
		return $this->responses[$url];
	}
}
