<?php
declare(strict_types = 1);

final class browsersTest extends \PHPUnit\Framework\TestCase {

	protected function getBrowsers() : browsersPublic {
		return new browsersPublic([
			'cache' => \dirname(__DIR__).'/cache/',

			// without a token the GitHub REST API allows only 60 requests an hour per IP address, which
			// the GitHub sourced browsers exhaust, so use the GraphQL API when a token is available
			'githubtoken' => $_ENV['GITHUB_TOKEN'] ?? \getenv('GITHUB_TOKEN') ?: null
		]);
	}

	protected function assertVersionArray(array|false $result, string $browser) : void {
		$this->assertIsArray($result, $browser.' should return an array');
		$this->assertNotEmpty($result, $browser.' should not be empty');
		foreach ($result AS $version => $date) {
			$this->assertMatchesRegularExpression('/^[0-9]+(?:\.[0-9]+)*(?:-[0-9a-z_.]+)?$/i', (string) $version, $browser.' version keys should be version numbers, optionally with a build suffix');
			$this->assertIsInt($date, $browser.' dates should be integers');
			$this->assertGreaterThan(19000101, $date, $browser.' dates should be valid YYYYMMDD integers');
			$this->assertLessThan(99991231, $date, $browser.' dates should be valid YYYYMMDD integers');
		}
	}

	protected function assertVersionArrayOrSkip(array|false $result, string $browser) : void {
		if ($result === false) {
			$this->markTestSkipped($browser.' source is behind bot detection, which blocks requests from datacentre IP addresses such as CI runners');
		} else {
			$this->assertVersionArray($result, $browser);
		}
	}

	public function testChromeVersions() : void {
		$this->assertVersionArray($this->getBrowsers()->getChromeVersions(), 'Chrome');
	}

	public function testFirefoxVersions() : void {
		$this->assertVersionArray($this->getBrowsers()->getFirefoxVersions(), 'Firefox');
	}

	public function testEdgeVersions() : void {
		$this->assertVersionArray($this->getBrowsers()->getEdgeVersions(), 'Edge');
	}

	public function testSafariVersions() : void {
		$this->assertVersionArray($this->getBrowsers()->getSafariVersions(), 'Safari');
	}

	public function testInternetExplorerVersions() : void {
		$result = $this->getBrowsers()->getInternetExplorerVersions();
		$this->assertSame(20010824, $result['6']);
		$this->assertSame(20090319, $result['8']);
		$this->assertSame(20131017, $result['11']);
	}

	public function testLegacySafariVersions() : void {
		$result = $this->getBrowsers()->getLegacySafariVersions();
		$this->assertSame(20030623, $result['1']);
		$this->assertSame(20090608, $result['4']);
		$this->assertSame(20180917, $result['12']);
	}

	public function testLegacyEdgeVersions() : void {
		$this->assertVersionArray($this->getBrowsers()->getLegacyEdgeVersions(), 'Legacy Edge');
	}

	public function testOperaVersions() : void {
		$this->assertVersionArray($this->getBrowsers()->getOperaVersions(), 'Opera');
	}

	public function testBraveVersions() : void {
		$this->assertVersionArray($this->getBrowsers()->getBraveVersions(), 'Brave');
	}

	public function testVivaldiVersions() : void {
		$this->assertVersionArray($this->getBrowsers()->getVivaldiVersions(), 'Vivaldi');
	}

	public function testMaxthonVersions() : void {
		$this->assertVersionArray($this->getBrowsers()->getMaxthonVersions(), 'Maxthon');
	}

	public function testSamsungInternetVersions() : void {
		$this->assertVersionArray($this->getBrowsers()->getSamsungInternetVersions(), 'Samsung Internet');
	}

	public function testHuaweiBrowserVersions() : void {
		$this->assertVersionArrayOrSkip($this->getBrowsers()->getHuaweiBrowserVersions(), 'Huawei Browser');
	}

	public function testUcBrowserVersions() : void {
		$this->assertVersionArrayOrSkip($this->getBrowsers()->getUcBrowserVersions(), 'UC Browser');
	}

	public function testSilkBrowserVersions() : void {
		$this->assertVersionArrayOrSkip($this->getBrowsers()->getSilkBrowserVersions(), 'Silk Browser');
	}

	public function testKiwiBrowserVersions() : void {
		$this->assertVersionArrayOrSkip($this->getBrowsers()->getKiwiBrowserVersions(), 'Kiwi Browser');
	}

	public function testDuckDuckBrowserVersions() : void {
		$this->assertVersionArrayOrSkip($this->getBrowsers()->getDuckDuckBrowserVersions(), 'DuckDuckGo Browser');
	}

	public function testKmeleonVersions() : void {
		$this->assertVersionArray($this->getBrowsers()->getKmeleonVersions(), 'K-Meleon');
	}

	public function testKonquerorVersions() : void {
		$this->assertVersionArray($this->getBrowsers()->getKonquerorVersions(), 'Konqueror');
	}

	public function testWaterfoxVersions() : void {
		$this->assertVersionArray($this->getBrowsers()->getWaterfoxVersions(), 'Waterfox');
	}

	public function testPalemoonVersions() : void {
		$this->assertVersionArrayOrSkip($this->getBrowsers()->getPalemoonVersions(), 'Pale Moon');
	}

	public function testOculusBrowserVersions() : void {
		$this->assertVersionArray($this->getBrowsers()->getOculusBrowserVersions(), 'Oculus Browser');
	}

	public function testMidoriVersions() : void {
		$this->assertVersionArray($this->getBrowsers()->getMidoriVersions(), 'Midori');
	}

	public function testBuildFresh() : void {
		$target = \sys_get_temp_dir().'/versions-test-'.uniqid().'.json';
		try {
			$browsers = $this->getBrowsers();
			$this->assertTrue($browsers->build($target));
			$data = \json_decode((string) \file_get_contents($target), true);
			$this->assertIsArray($data);
			foreach (['chrome', 'firefox', 'edge', 'safari', 'ie', 'opera', 'brave', 'vivaldi', 'maxthon', 'samsung', 'waterfox', 'oculus', 'midori', 'konqueror', 'kmeleon'] AS $key) {
				$this->assertArrayHasKey($key, $data, "Expected browser '$key' in output");
				$this->assertNotEmpty($data[$key], "Browser '$key' should have versions");
			}

			// these sources are behind bot detection or rate limited, so may be absent when built from a datacentre IP address such as a CI runner
			foreach (['palemoon', 'huawei', 'ucbrowser', 'silk', 'kiwi', 'duckduckgo'] AS $key) {
				if (isset($data[$key])) {
					$this->assertNotEmpty($data[$key], "Browser '$key' should have versions");
				}
			}
		} finally {
			if (\file_exists($target)) {
				\unlink($target);
			}
		}
	}

	public function testBuildIncremental() : void {
		$target = \sys_get_temp_dir().'/versions-test-'.uniqid().'.json';
		try {

			// seed with existing chrome data
			$existing = ['chrome' => ['131.0.6778.139' => 20241203]];
			\file_put_contents($target, \json_encode($existing));

			$browsers = $this->getBrowsers();
			$this->assertTrue($browsers->build($target));
			$data = \json_decode((string) \file_get_contents($target), true);
			$this->assertIsArray($data);
			$this->assertArrayHasKey('chrome', $data);

			// original entry should still be present
			$this->assertArrayHasKey('131.0.6778.139', $data['chrome']);

			// newer entries should have been merged in
			$this->assertGreaterThan(1, \count($data['chrome']));
		} finally {
			if (\file_exists($target)) {
				\unlink($target);
			}
		}
	}
}
