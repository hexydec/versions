<?php
declare(strict_types = 1);
use hexydec\versions\browsersFixture;

/**
 * Exercises the scraping and parsing of each kind of source against fixed responses, so the parsers
 * are covered even when the live source is unreachable, has changed, or blocks the request
 */
final class browsersParseTest extends \PHPUnit\Framework\TestCase {

	public function testUptodownVersionsAreParsed() : void {
		$url = 'https://huawei-browser.en.uptodown.com/android/versions';
		$browsers = (new browsersFixture())->respond($url, '<html><body>
			<div data-version-id="1001"><span class="version">15.0.6.310</span><span class="date">3 Jul 2024</span></div>
			<div data-version-id="1002"><span class="version">14.0.4.301</span><span class="date">21 Dec 2023</span></div>
			<div data-version-id="1003"><span class="version"></span><span class="date">1 Jan 2023</span></div>
			<div data-version-id="1004"><span class="version">13.0.1.300</span></div>
		</body></html>');
		$this->assertSame([
			'15.0.6.310' => 20240703,
			'14.0.4.301' => 20231221
		], $browsers->getHuaweiBrowserVersions(), 'Rows without a version or without a date should be ignored');
		$this->assertSame([$url], $browsers->requested());
	}

	public function testUptodownReturnsFalseWhenTheSourceCannotBeFetched() : void {
		$url = 'https://uc-browser.en.uptodown.com/android/versions';
		$browsers = (new browsersFixture())->respond($url, false);
		$this->assertFalse($browsers->getUcBrowserVersions(), 'A source that could not be fetched should return false rather than an empty array');
	}

	public function testUptodownReturnsFalseWhenNoVersionsAreFound() : void {
		$url = 'https://silk-browser.en.uptodown.com/android/versions';
		$browsers = (new browsersFixture())->respond($url, '<html><body><p>No versions here</p></body></html>');
		$this->assertFalse($browsers->getSilkBrowserVersions(), 'A source that yielded no versions should return false rather than an empty array');
	}

	/**
	 * Uptodown blocks datacentre IP addresses such as CI runners, so a response that isn't the versions
	 * page must degrade to false rather than yielding entries dated today
	 */
	public function testUptodownReturnsFalseWhenTheResponseIsABotDetectionPage() : void {
		$url = 'https://kiwi-browser.en.uptodown.com/android/versions';
		$browsers = (new browsersFixture())->respond($url, '<html><body>
			<h1>Just a moment...</h1>
			<p>Enable JavaScript and cookies to continue</p>
		</body></html>');
		$this->assertFalse($browsers->getKiwiBrowserVersions(), 'A bot detection page should not yield versions');
	}

	public function testFirefoxVersionsAreParsedFromThePastReleasesTableOnly() : void {
		$url = 'https://whattrainisitnow.com/calendar/';
		$browsers = (new browsersFixture())->respond($url, '<html><body>
			<table>
				<caption>Future releases</caption>
				<tbody><tr><td>146.0</td><td>2025-12-09</td></tr></tbody>
			</table>
			<table>
				<caption>Past releases</caption>
				<tbody>
					<tr><td>144.0</td><td>2025-10-14</td></tr>
					<tr><td>143.0</td><td>2025-09-16</td></tr>
				</tbody>
			</table>
		</body></html>');
		$this->assertSame([
			'144.0' => 20251014,
			'143.0' => 20250916
		], $browsers->getFirefoxVersions(), 'Only the past releases table should be collected');
	}

	public function testChromeVersionsAreParsedFromTheJsonFeed() : void {
		$url = 'https://chromiumdash.appspot.com/fetch_releases?channel=Stable&platform=Windows&num=20&offset=0';
		$browsers = (new browsersFixture())->respond($url, (string) \json_encode([
			['version' => '142.0.7444.60', 'time' => 1761609600000],
			['version' => '141.0.7390.55', 'time' => 1759276800000]
		]));

		// the feed returned fewer items than were requested, so only the first page is fetched
		$this->assertSame([
			'142.0.7444.60' => 20251028,
			'141.0.7390.55' => 20251001
		], $browsers->getChromeVersions());
		$this->assertSame([$url], $browsers->requested());
	}

	public function testWaterfoxVersionsAreParsedFromTheRssFeed() : void {
		$url = 'https://www.waterfox.com/rss.xml';
		$browsers = (new browsersFixture())->respond($url, '<?xml version="1.0"?>
			<rss version="2.0"><channel>
				<item><title>Waterfox 6.5.14</title><pubDate>Tue, 15 Jul 2025 00:00:00 GMT</pubDate></item>
				<item><title>Waterfox 6.5.13</title><pubDate>Wed, 18 Jun 2025 00:00:00 GMT</pubDate></item>
				<item><title>Release notes</title><pubDate>Wed, 18 Jun 2025 00:00:00 GMT</pubDate></item>
			</channel></rss>');
		$this->assertSame([
			'6.5.14' => 20250715,
			'6.5.13' => 20250618
		], $browsers->getWaterfoxVersions(), 'Items that are not a release should be ignored');
	}

	public function testGithubReleasesIgnoreTagsThatAreNotAVersion() : void {
		$url = 'https://api.github.com/repos/duckduckgo/Android/releases?per_page=100&page=1';
		$browsers = (new browsersFixture())->respond($url, (string) \json_encode([
			['name' => '', 'tag_name' => '5.292.0', 'published_at' => '2026-08-12T09:00:00Z'],
			['name' => '', 'tag_name' => 'v5.291.3', 'published_at' => '2026-08-10T09:00:00Z'],
			['name' => null, 'tag_name' => 'test', 'published_at' => '2026-08-09T09:00:00Z']
		]));
		$this->assertSame([
			'5.292.0' => 20260812,
			'5.291.3' => 20260810
		], $browsers->getDuckDuckBrowserVersions(), 'Tags that are not a version number should be ignored, and a v prefix should be trimmed');
	}

	/**
	 * The Pale Moon feed titles are inconsistent, so this covers each of the shapes that appear in it
	 */
	public function testPalemoonVersionsAreExtractedFromInconsistentTitles() : void {
		$result = (new browsersFixture())->getPalemoonVersions();
		$this->assertIsArray($result);

		// the various prefixes the feed uses
		$this->assertSame(20250701, $result['33.8.0'] ?? null, 'A standard title should be collected');
		$this->assertSame(20250703, $result['33.8.0-r2'] ?? null, 'A revision suffix should be retained');
		$this->assertSame(20221101, $result['31.3.1'] ?? null, 'A title with a lowercase name should be collected');
		$this->assertSame(20220412, $result['29.4.6'] ?? null, 'A title with only a tag name should be collected');

		// versions that should not have been collected
		$this->assertArrayNotHasKey('', $result, 'An item with no version should be ignored');
		$this->assertSame(20230320, $result['32.1.0'] ?? null, 'The final release date should be collected rather than the date of one of its betas');
		foreach (\array_keys($result) AS $version) {
			$this->assertMatchesRegularExpression('/^[0-9]+(?:\.[0-9]+)+(?:-r[0-9]+)?$/', (string) $version, 'Only version numbers should be collected');
		}
	}
}
