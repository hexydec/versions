<?php
declare(strict_types = 1);
namespace hexydec\versions;
/**
 * Builds a JSON database of browser versions and their release dates by scraping and fetching data from vendor sources
 */
class browsers {

	/**
	 * @var array<string,mixed> $config An array of configuration options
	 */
	protected array $config = [
		'msg' => null, // a callback for where the messages are written
		'cache' => null, // to cache the source content, specify the absolute cache directory
		'githubtoken' => null // a GitHub personal access token, used to fetch full release history via the GraphQL API on rebuild
	];
	/**
	 * @var array<string,float> $timing A map of hostname to the microtime of the last request made to it, used to rate limit requests
	 */
	protected array $timing = [];

	/**
	 * Constructs a new browsers object
	 *
	 * @param array<string,mixed> $config An array of configuration options to merge with the defaults
	 */
	public function __construct(array $config = []) {
		$this->config = \array_replace($this->config, $config);
	}

	/**
	 * Retrieves the callback that collects the versions of each supported browser, keyed by the name it is stored under
	 *
	 * @param array<string,array<string,int>> $data The versions collected so far, so a source can limit how far back it fetches
	 * @return array<string,callable> An array of browser name to the callback that collects its versions
	 */
	protected function getSources(array $data) : array {
		return [
			'chrome' => fn (bool $rebuild) => $this->getChromeVersions($rebuild, $data['chrome'] ?? []),
			'firefox' => [$this, 'getFirefoxVersions'],
			'edge' => [$this, 'getEdgeVersions'],
			'safari' => [$this, 'getSafariVersions'],
			'ie' => [$this, 'getInternetExplorerVersions'],
			'opera' => [$this, 'getOperaVersions'],
			'brave' => [$this, 'getBraveVersions'],
			'vivaldi' => [$this, 'getVivaldiVersions'],
			'maxthon' => [$this, 'getMaxthonVersions'],
			'samsung' => [$this, 'getSamsungInternetVersions'],
			'huawei' => [$this, 'getHuaweiBrowserVersions'],
			'kmeleon' => [$this, 'getKmeleonVersions'],
			'konqueror' => [$this, 'getKonquerorVersions'],
			'ucbrowser' => [$this, 'getUcBrowserVersions'],
			'silk' => [$this, 'getSilkBrowserVersions'],
			'kiwi' => [$this, 'getKiwiBrowserVersions'],
			'duckduckgo' => [$this, 'getDuckDuckBrowserVersions'],
			'waterfox' => [$this, 'getWaterfoxVersions'],
			'palemoon' => [$this, 'getPalemoonVersions'],
			'oculus' => [$this, 'getOculusBrowserVersions'],
			'midori' => [$this, 'getMidoriVersions']
		];
	}

	/**
	 * Collects the versions for each supported browser and writes them to the target JSON file
	 *
	 * @param string $target The absolute path of the JSON file to write the browser versions to
	 * @param bool $rebuild Whether to rebuild the whole dataset from scratch, ignoring any cached sources and fetching the full release history where available
	 * @return bool Whether the output file was written successfully
	 */
	public function build(string $target, bool $rebuild = false) : bool {

		// read existing file as baseline (preserved for sources that fail to fetch on rebuild)
		if (!\file_exists($target)) {
			$data = [];
		} elseif (($json = \file_get_contents($target)) === false) {
			\trigger_error('Could not read file', E_USER_WARNING);
			$data = [];
		} elseif (($data = \json_decode($json, true)) === null) {
			\trigger_error('Data is not valid JSON', E_USER_WARNING);
			$data = [];
		}

		// update browser versions
		$added = 0;
		$total = 0;
		foreach ($this->getSources($data) AS $key => $item) {
			if (($results = \call_user_func($item, $rebuild)) !== false) {
				$existing = $data[$key] ?? [];
				$new = $rebuild ? $results : \array_diff_key($results, $existing);
				$count = \count($new);
				$this->msg('Found '.$count.' for '.\ucfirst($key));
				$data[$key] = $rebuild ? $results : \array_replace($existing, $results);
				\arsort($data[$key]);
				$added += $count;
				$total += \count($data[$key]);
			} else {
				$this->msg('Warning: Could not generate versions for '.\ucfirst($key));
			}
		}

		// generate output file
		$dir = \dirname($target);

		// no browsers found
		if ($total === 0) {
			\trigger_error('No browser versions could be generated', E_USER_WARNING);

		// create output directory
		} elseif (!\is_dir($dir) && !\mkdir($dir, 0755)) {
			\trigger_error('Output directory could not be created', E_USER_WARNING);

		// write output file
		} elseif (\file_put_contents($target, \json_encode($data, JSON_PRETTY_PRINT)) === false) {
			\trigger_error('Could not save output file', E_USER_WARNING);

		// complete
		} else {
			$this->msg($rebuild ? 'Saved '.$total.' browser versions' : 'Added '.$added.' and saved '.$total.' browser versions');
			return true;
		}
		return false;
	}

	/**
	 * Writes a progress message, either through the configured callback or to the output
	 *
	 * @param string $msg The message to write
	 * @return void
	 */
	protected function msg(string $msg) : void {
		\call_user_func($this->config['msg'] ?? function (string $msg) : void {
			echo $msg."\n";
		}, $msg);
	}

	/**
	 * Fetches a URL, reading from and writing to the local cache where one is configured, and rate limiting requests to each host
	 *
	 * @param string $url The URL to fetch
	 * @param bool $contents Whether to return the contents of the URL, or the path of the file it was downloaded to
	 * @param bool $rebuild Whether to bypass the local cache and fetch the URL again
	 * @param array<string,mixed> $options An array of HTTP stream context options to merge with the defaults
	 * @return string|false The contents of the URL, or the path of the downloaded file if $contents is false, or false if the URL could not be fetched
	 */
	protected function fetch(string $url, bool $contents = true, bool $rebuild = false, array $options = []) : string|false {
		$cache = $this->config['cache'];
		$local = null;
		$file = false;

		// generate cache file name - includes a hash of the request body, as requests like the GitHub
		// GraphQL API POST different queries (e.g. different pagination cursors) to the same URL
		if ($cache !== null) {
			$key = $url.($options['content'] ?? '');
			$local = $cache.\preg_replace('/[^0-9a-z]+/i', '-', $url).'-'.\substr(\md5($key), 0, 8).'.cache';
		}

		// fetch from local cache
		if (!$rebuild && $local !== null && \file_exists($local) && (!$contents || ($file = \file_get_contents($local)) !== false)) {
			return $contents ? $file : $local;

		// download file
		} else {

			// create cache directory
			if ($local !== null && !\is_dir($cache)) {
				\mkdir($cache, 0755);
			}

			// save timing
			$host = \parse_url($url, PHP_URL_HOST);
			if ($host !== false && $host !== null) {
				$wait = 2;
				if (isset($this->timing[$host]) && \microtime(true) < $this->timing[$host] + $wait) {
					\sleep($wait);
				}
			}
			$context = \stream_context_create([
				'http' => \array_merge([
					'user_agent' => 'Mozilla/5.0 (compatible; Hexydec Browser Versions Bot/1.0; +https://github.com/hexydec/versions/)',
					'header' => [
						'Sec-Fetch-Dest: document',
						'Sec-Fetch-Mode: navigate',
						'Sec-Fetch-Site: none',
						'Sec-Fetch-User: ?1',
						'Sec-GPC: 1',
						'Cache-Control: no-cache',
						'Accept-Language: en-GB,en;q=0.5'
					]
				], $options)
			]);
			if ($contents) {
				if (($file = \file_get_contents($url, false, $context)) !== false) {

					// save to local file
					if ($local !== null) {
						\file_put_contents($local, $file);
					}
					return $file;
				}
			} else {
				$file = $local ?? \tempnam(\sys_get_temp_dir(), 'datacentres');
				if (!\copy($url, $file, $context)) {
					$file = false;
				}
			}
			if ($host !== false && $host !== null) {
				$this->timing[$host] = \microtime(true);
			}
			return $file;
		}
	}

	/**
	 * Extracts versions and their release dates from a JSON feed of release objects
	 *
	 * @param string $url The URL of the JSON feed to fetch
	 * @param array<int,string> $version The chain of property names to follow in each item to reach the version
	 * @param array<int,string> $date The chain of property names to follow in each item to reach the release date
	 * @param bool $rebuild Whether to bypass the local cache and fetch the URL again
	 * @return array<string,mixed>|false An array of versions mapped to their release date, or false if the feed could not be fetched or was empty
	 */
	protected function getFromJson(string $url, array $version, array $date, bool $rebuild = false) : array|false {
		if (($file = $this->fetch($url, true, $rebuild)) !== false && ($json = \json_decode($file)) !== null && !empty($json)) {
			$data = [];
			foreach ($json AS $item) {
				$row = [
					'version' => $item,
					'date' => $item
				];
				$keys = ['version' => $version, 'date' => $date];
				foreach ($keys AS $key => $vars) {
					foreach ($vars AS $v) {
						$row[$key] = $row[$key]->{$v};
						if (!\is_object($row[$key])) {
							break;
						} 
					}
				}
				$data[$row['version']] = $row['date'];
			}
			return $data;
		}
		return false;
	}

	/**
	 * Retrieves the stable Chrome versions from the Chromium Dash API, paging back through the releases
	 *
	 * @param bool $rebuild Whether to fetch the full release history, ignoring the local cache
	 * @param array<string,int> $existing An array of the versions already collected, used to stop paging once known versions are reached
	 * @return array<string,int>|false An array of versions mapped to their release date as an integer in the format Ymd, or false if no versions could be retrieved
	 */
	protected function getChromeVersions(bool $rebuild = false, array $existing = []) : array|false {
		$items = $rebuild ? 1000 : 20;
		$url = 'https://chromiumdash.appspot.com/fetch_releases?channel=Stable&platform=Windows&num='.$items.'&offset=';
		$chrome = [];
		for ($i = 0; $i < 10; $i++) {
			if (($data = $this->getFromJson($url.($i * $items), ['version'], ['time'], $rebuild)) !== false) {
				$page = \array_map(fn (int $time) : int => \intval(\gmdate('Ymd', \intval($time / 1000))), $data);
				$chrome = \array_merge($chrome, $page);
				if (\count($data) < $items || (!$rebuild && \count(\array_intersect_key($page, $existing)) > 0)) {
					break;
				}
			} else {
				break;
			}
		}
		return $chrome ?: false;
	}

	/**
	 * Retrieves the Firefox versions from the release calendar at whattrainisitnow.com
	 *
	 * @param bool $rebuild Whether to bypass the local cache and fetch the source again
	 * @return array<string,int>|false An array of versions mapped to their release date as an integer in the format Ymd, or false if the source could not be retrieved
	 */
	protected function getFirefoxVersions(bool $rebuild = false) : array|false {
		$url = 'https://whattrainisitnow.com/calendar/';
		if (($html = $this->fetch($url, true, $rebuild)) !== false) {
			$data = [];
			$obj = new \hexydec\html\htmldoc();
			if ($obj->load($html)) {
				foreach ($obj->find('table') AS $item) {
					$caption = $item->find('caption')->text();
					if ($caption === 'Past releases') {
						foreach ($item->find('tbody > tr') AS $row) {
							$version = $row->find('td:first-child')->text();
							if ($version !== '' && ($date = $row->find('td:last-child')->text()) !== '') {
								$data[$version] = \intval(\str_replace('-', '', $date));
							}
						}
					}
				}
				return $data;
			}
		}
		return false;
	}

	/**
	 * Retrieves the legacy (EdgeHTML) Edge versions from Wikipedia
	 *
	 * @return array<string,int>|false An array of versions mapped to their release date as an integer in the format Ymd, or false if the source could not be retrieved
	 */
	protected function getLegacyEdgeVersions() : array|false {
		$url = 'https://en.wikipedia.org/wiki/EdgeHTML';
		if (($html = $this->fetch($url)) !== false) {
			$data = [];
			$obj = new \hexydec\html\htmldoc();
			if ($obj->load($html)) {
				foreach ($obj->find('table.wikitable > tbody > tr') AS $row) {
					$cells = $row->find('td');
					$version = \trim($cells->eq(0)->text());
					if ($version !== '' && ($text = $cells->eq(1)->text()) !== '') {
						$date = new \DateTime($text);
						$data[$version] = \intval($date->format('Ymd'));
					}
				}
				return $data;
			}
		}
		return false;
	}

	/**
	 * Retrieves the Edge versions from the Microsoft release schedule, adding the legacy and archived Chromium releases on rebuild
	 *
	 * @param bool $rebuild Whether to also collect the legacy and archived releases, bypassing the local cache
	 * @return array<string,int>|false An array of versions mapped to their release date as an integer in the format Ymd, or false if no versions could be retrieved
	 */
	protected function getEdgeVersions(bool $rebuild = false) : array|false {
		$data = $rebuild ? $this->getLegacyEdgeVersions() : [];

		// this is the most up to date page
		$url = 'https://learn.microsoft.com/en-us/deployedge/microsoft-edge-release-schedule';
		if (($html = $this->fetch($url, true, $rebuild)) !== false) {
			$obj = new \hexydec\html\htmldoc();
			if ($obj->load($html)) {
				foreach ($obj->find('table > tbody > tr') AS $row) {
					if (\preg_match('/([0-9]{2}-[a-z]{3}-[0-9]{4})\W++([0-9.]{4,})/i', $row->find('td')->eq(3)->text(), $match)) {
						$date = new \DateTime($match[1]);
						$data[$match[2]] = \intval($date->format('Ymd'));
					}
				}
			}
		}

		// this has all the previous chromium builds
		$url = 'https://learn.microsoft.com/en-us/deployedge/microsoft-edge-relnote-archive-stable-channel';
		if ($rebuild && ($html = $this->fetch($url, true, $rebuild)) !== false) {
			$obj = new \hexydec\html\htmldoc();
			if ($obj->load($html)) {
				$year = null;
				$last = 12;
				foreach ($obj->find('h2') AS $row) {
					if (\preg_match('/^Version ([0-9.]++): ([a-z]++ [0-9]++(?:, ([0-9]{4}))?)/i', $row->text(), $match)) {
						$date = new \DateTime($match[2]);
						$month = $date->format('n');
						$year = \intval($match[3] ?? ($month > $last ? $year - 1 : $year)); // track the year, as sometimes it is not there
						$last = $month;
						$date->setDate($year, \intval($date->format('m')), \intval($date->format('d')));
						$data[$match[1]] = \intval($date->format('Ymd'));
					}
				}
			}
		}
		return $data ?: false;
	}

	/**
	 * Retrieves the Safari versions that predate the release notes feed, which are hardcoded as there is no machine readable source
	 *
	 * @return array<int|string,int> An array of versions mapped to their release date as an integer in the format Ymd, note that whole number versions are cast to integer keys by PHP
	 */
	protected function getLegacySafariVersions() : array {
		return [
			'1' => 20030623,
			'2' => 20050429,
			'2.0.2' => 20051031,
			'2.0.4' => 20060110,
			'3' => 20070109,
			'3.0.1' => 20070614,
			'3.0.2' => 20070622,
			'3.1' => 20080318,
			'3.1.2' => 20080601,
			'3.2' => 20081113,
			'3.2.3' => 20090512,
			'4' => 20090608,
			'4.0.1' => 20090617,
			'4.0.4' => 20091111,
			'5' => 20100607,
			'5.0.6' => 20100631,
			'5.1' => 20110720,
			'6' => 20120725,
			'7' => 20131022,
			'8' => 20140606,
			'9' => 20150612,
			'10' => 20160920,
			'10.1.2' => 20170719,
			'11' => 20170919,
			'12' => 20180917,
			'12.0.1' => 20181030,
			'12.0.2' => 20181205
		];
	}

	/**
	 * Retrieves the Safari versions from the Apple release notes feed, adding the legacy versions on rebuild
	 *
	 * @param bool $rebuild Whether to also collect the legacy versions, bypassing the local cache
	 * @return array<string,int>|false An array of versions mapped to their release date as an integer in the format Ymd, or false if the feed could not be retrieved
	 */
	protected function getSafariVersions(bool $rebuild = false) : array|false {
		$url = 'https://developer.apple.com/tutorials/data/documentation/safari-release-notes.json';
		if (($file = $this->fetch($url, true, $rebuild)) !== false && ($json = \json_decode($file)) !== null) {
			$data = $rebuild ? $this->getLegacySafariVersions() : [];
			foreach ($json->references AS $item) {
				if (!empty($item->abstract[0]->text)) {
					$text = $item->abstract[0]->text;
					if (\preg_match('/([a-z]++ [0-9]{1,2}, [0-9]{4})[^0-9]++([0-9.]++) \(([0-9.]++)\)/i', $text, $match)) {
						$date = new \DateTime($match[1]);
						$data[$match[2]] = \intval($date->format('Ymd'));
					}
				}
			}
			return $data;
		}
		return false;
	}

	/**
	 * Retrieves the Internet Explorer versions, which are hardcoded as the browser is discontinued
	 *
	 * @return array<int|string,int> An array of versions mapped to their release date as an integer in the format Ymd, note that whole number versions are cast to integer keys by PHP
	 */
	protected function getInternetExplorerVersions() : array {
		return [
			'1' => 19950724,
			'2' => 19951127,
			'3' => 19960813,
			'4' => 19970922,
			'5' => 19990318,
			'5.0.1' => 19991201,
			'5.5' => 20000519,
			'6' => 20010824,
			'7' => 20061018,
			'8' => 20090319,
			'9' => 20110319,
			'10' => 20121026,
			'11' => 20131017,
			'11.0.7' => 20140408,
			'11.0.11' => 20140812,
			'11.0.15' => 20141209,
			'11.0.25' => 20151112
		];
	}

	/**
	 * Retrieves the classic (Presto) Opera versions from Opera's archived history page, only collecting the final builds
	 *
	 * @param bool $rebuild Whether to bypass the local cache and fetch the source again
	 * @return array<string,int>|false An array of versions mapped to their release date as an integer in the format Ymd, or false if the source could not be retrieved
	 */
	protected function getOperaClassicVersions(bool $rebuild = false) : array|false {
		$url = 'https://help.opera.com/en/operas-archived-history/';
		if (($html = $this->fetch($url, true, $rebuild)) !== false) {
			$data = [];
			$obj = new \hexydec\html\htmldoc();
			if ($obj->load($html)) {
				foreach ($obj->find('tbody > tr') AS $row) {
					$cells = $row->find('th,td');
					if ($cells->eq(2)->text() === 'Final') {
						$data[\explode(' ', $cells->eq(0)->text())[1]] = \intval(\str_replace('-', '', $cells->eq(1)->text()));
					}
				}
				return $data;
			}
		}
		return false;
	}

	/**
	 * Retrieves the Opera versions from the desktop release directory listing, adding the classic versions on rebuild
	 *
	 * @param bool $rebuild Whether to also collect the classic versions, bypassing the local cache
	 * @return array<string,int>|false An array of versions mapped to their release date as an integer in the format Ymd, or false if no versions could be retrieved
	 */
	protected function getOperaVersions(bool $rebuild = false) : array|false {
		$data = $rebuild ? ($this->getOperaClassicVersions($rebuild) ?: []) : [];

		// directory listing of all desktop Opera releases
		$url = 'https://get.geo.opera.com/ftp/pub/opera/desktop/';
		if (($html = $this->fetch($url, true, $rebuild)) !== false &&
			\preg_match_all('/href="([0-9][0-9.]+)\/"[^>]*>[^<]*<\/a>\s+([0-9]{2}-[A-Za-z]+-[0-9]{4})/i', $html, $matches)) {
			foreach ($matches[1] AS $i => $version) {
				$data[$version] = \intval((new \DateTime($matches[2][$i]))->format('Ymd'));
			}
		}
		return $data ?: false;
	}

	/**
	 * Retrieves the Brave versions, from the rolling versions feed normally, or from the GitHub releases on rebuild
	 *
	 * @param bool $rebuild Whether to fetch the full release history from GitHub instead of the rolling versions feed
	 * @return array<string,int>|false An array of versions mapped to their release date as an integer in the format Ymd, or false if no versions could be retrieved
	 */
	protected function getBraveVersions(bool $rebuild = false) : array|false {
		$data = [];

		// get from rolling brave version normally
		if (!$rebuild) {
			$url = 'https://versions.brave.com/latest/brave-versions.json';
			if (($file = $this->fetch($url, true, $rebuild)) !== false && ($json = \json_decode($file)) !== null) {
				foreach ($json AS $item) {
					if ($item->channel === 'release') {
						$data[$item->name] = \intval(\str_replace('-', '', \substr($item->published, 0, 10)));
					}
				}
			}

		// fetch from github on rebuild
		} else {
			$data = $this->getGithubReleases('brave', 'brave-browser', 'Release ', $rebuild);
		}
		return $data ?: false;
	}

	/**
	 * Retrieves the releases of a GitHub repository, through the GraphQL API where a personal access token is configured, otherwise falling back to the REST API
	 *
	 * @param string $user The owner of the repository
	 * @param string $repo The name of the repository
	 * @param string|null $filter A prefix that the release name must start with to be collected, or null to collect all releases
	 * @param bool $rebuild Whether to bypass the local cache and fetch the releases again
	 * @return array<string,int>|false An array of versions mapped to their release date as an integer in the format Ymd, or false if no releases could be retrieved
	 */
	protected function getGithubReleases(string $user, string $repo, ?string $filter = null, bool $rebuild = false) : array|false {
		$data = [];

		// repositories often carry tags that aren't a release (e.g. "test" in maxthon/Maxthon), so only collect version numbers
		$pattern = '/^[0-9]++(?:\.[0-9]++)*+(?:-[0-9a-z_.]++)?$/i';

		// fetch from github on rebuild
		if (($token = $this->config['githubtoken']) !== null) {
			$cursor = null;
			do {

				// prepare query
				$query = 'query{repository(owner:"'.$user.'",name:"'.$repo.'"){releases(first:100'.($cursor !== null ? ',after:"'.$cursor.'"' : '').',orderBy:{field:CREATED_AT,direction:DESC}){pageInfo{hasNextPage endCursor}nodes{name tagName publishedAt}}}}';
				$options = [
					'method' => 'POST',
					'header' => [
						'Content-Type: application/json',
						'Authorization: Bearer '.$token,
					],
					'content' => \json_encode(['query' => $query])
				];

				// reset cursor
				$cursor = null;

				// fetch response
				if (($response = $this->fetch('https://api.github.com/graphql', true, $rebuild, $options)) === false) {
					
				// decode JSON
				} elseif (($json = \json_decode($response)) === null) {
					
				// check releases are set
				} elseif (!isset($json->data->repository->releases)) {
					
				// extract data
				} else {
					$releases = $json->data->repository->releases;
					foreach ($releases->nodes AS $node) {
						$version = \ltrim($node->tagName, 'v');
						if (($filter === null || \str_starts_with((string) $node->name, $filter)) && \preg_match($pattern, $version)) {
							$data[$version] = \intval((new \DateTime($node->publishedAt))->format('Ymd'));
						}
					}
					if ($releases->pageInfo->hasNextPage) {
						$cursor = $releases->pageInfo->endCursor;
					}
				}
			} while ($cursor !== null);

		} else {

			// REST API fallback — capped at 1000 total releases across all channels
			$page = 1;
			while (true) {
				$url = 'https://api.github.com/repos/'.$user.'/'.$repo.'/releases?per_page=100&page='.$page;
				if (($file = $this->fetch($url, true, $rebuild)) !== false && ($json = \json_decode($file)) !== null && \is_array($json) && $json !== []) {
					foreach ($json AS $item) {
						$version = \ltrim($item->tag_name, 'v');
						if (($filter === null || \str_starts_with((string) $item->name, $filter)) && \preg_match($pattern, $version)) {
							$data[$version] = \intval((new \DateTime($item->published_at))->format('Ymd'));
						}
					}
					if (\count($json) < 100) {
						break;
					}
					$page++;
				} else {
					break;
				}
			}
		}
		return $data ?: false;
	}

	/**
	 * Retrieves the Vivaldi versions from the Windows download archive, only collecting the 64bit builds
	 *
	 * @param bool $rebuild Whether to bypass the local cache and fetch the source again
	 * @return array<string,int>|false An array of versions mapped to their release date as an integer in the format Ymd, or false if no versions could be retrieved
	 */
	protected function getVivaldiVersions(bool $rebuild = false) : array|false {
		$data = [];
		$url = 'https://vivaldi.com/download/archive/?platform=win';
		if (($html = $this->fetch($url, true, $rebuild)) !== false) {
			$obj = new \hexydec\html\htmldoc();
			if ($obj->load($html)) {
				foreach ($obj->find('tbody > tr') AS $row) {
					$text = $row->find('td > a')->eq(0)->text();
					if (($date = $row->find('td:first-child + td')->text()) !== '' && \str_ends_with($text, '.x64.exe')) {
						$data[\substr($text, 8, -8)] = \intval(\str_replace('-', '', $date));
					}
				}
			}
		}
		return $data ?: false;
	}

	/**
	 * Retrieves the Maxthon versions from the GitHub releases, supplemented by the version history on their website
	 *
	 * @param bool $rebuild Whether to bypass the local cache and fetch the sources again
	 * @return array<string,int>|false An array of versions mapped to their release date as an integer in the format Ymd, or false if no versions could be retrieved
	 */
	protected function getMaxthonVersions(bool $rebuild = false) : array|false {
		
		// get from github first
		$data = $this->getGithubReleases('maxthon', 'Maxthon', null, $rebuild) ?: [];
		
		// backup from their website
		$url = 'https://www.maxthon.com/history';
		if (($html = $this->fetch($url, true, $rebuild)) !== false) {
			$obj = new \hexydec\html\htmldoc();
			if ($obj->load($html)) {
				foreach ($obj->find('.history-list-text') AS $row) {
					$title = $row->find('.history-list-title > span')->eq(0)->text();
					if (($date = $row->find('.history-list-desc > span')->eq(1)->text()) !== '' && \preg_match('/[0-9.]{3,}/', $title, $match)) {
						$data[$match[0]] = \intval(\str_replace('-', '', $date.(\strlen($date) === 4 ? '-01-01' : '')));
					}
				}
			}
		}
		return $data ?: false;
	}

	/**
	 * Extracts versions and their release dates from an Uptodown versions page
	 *
	 * @param string $url The URL of the Uptodown versions page to fetch
	 * @param bool $rebuild Whether to bypass the local cache and fetch the page again
	 * @return array<string,int>|false An array of versions mapped to their release date as an integer in the format Ymd, or false if no versions could be retrieved
	 */
	protected function getFromUptodown(string $url, bool $rebuild = false) : array|false {
		$data = [];
		if (($html = $this->fetch($url, true, $rebuild)) !== false) {
			$obj = new \hexydec\html\htmldoc();
			if ($obj->load($html)) {
				foreach ($obj->find('[data-version-id]') AS $row) {
					if (($version = $row->find('span.version')->text()) === '') {

					} elseif (($date = $row->find('span.date')->text()) !== '') {
						$data[$version] = \intval((new \DateTime($date))->format('Ymd'));
					}
				}
			}
		}
		return $data ?: false;
	}

	/**
	 * Retrieves the Samsung Internet versions from the release notes for each platform on the Samsung developer site
	 *
	 * @param bool $rebuild Whether to bypass the local cache and fetch the sources again
	 * @return array<string,int>|false An array of versions mapped to their release date as an integer in the format Ymd, or false if no versions could be retrieved
	 */
	protected function getSamsungInternetVersions(bool $rebuild = false) : array|false {
		$urls = [
			'https://developer.samsung.com/internet/release-note.html',
			'https://developer.samsung.com/internet/release-note/windows-release-note.html',
			'https://developer.samsung.com/internet/release-note/android-release-note.html'
		];
		$data = [];
		foreach ($urls AS $url) {
			if (($html = $this->fetch($url, true, $rebuild)) !== false) {
				$obj = new \hexydec\html\htmldoc();
				if ($obj->load($html)) {
					foreach ($obj->find('button') AS $row) {
						$text = $row->find('span.txt')->text();
						$date = $row->find('small.date')->text();
						if ($date !== '' && \str_starts_with($text, 'Samsung Internet') && \preg_match('/([0-9]+(?:\.[0-9]+)+)$/', \trim($text), $match)) {
							$date = (\substr_count($date, ' ') === 1 ? '1st ' : '').\str_replace(',', '', $date);
							$data[$match[1]] = \intval((new \DateTime($date))->format('Ymd'));
						}
					}
				}
			}
		}
		return $data ?: false;
	}

	/**
	 * Retrieves the Huawei Browser versions from Uptodown
	 *
	 * @param bool $rebuild Whether to bypass the local cache and fetch the source again
	 * @return array<string,int>|false An array of versions mapped to their release date as an integer in the format Ymd, or false if no versions could be retrieved
	 */
	protected function getHuaweiBrowserVersions(bool $rebuild = false) : array|false {
		return $this->getFromUptodown('https://huawei-browser.en.uptodown.com/android/versions', $rebuild);
	}

	/**
	 * Retrieves the UC Browser versions from Uptodown
	 *
	 * @param bool $rebuild Whether to bypass the local cache and fetch the source again
	 * @return array<string,int>|false An array of versions mapped to their release date as an integer in the format Ymd, or false if no versions could be retrieved
	 */
	protected function getUcBrowserVersions(bool $rebuild = false) : array|false {
		return $this->getFromUptodown('https://uc-browser.en.uptodown.com/android/versions', $rebuild);
	}

	/**
	 * Retrieves the Silk Browser versions from Uptodown
	 *
	 * @param bool $rebuild Whether to bypass the local cache and fetch the source again
	 * @return array<string,int>|false An array of versions mapped to their release date as an integer in the format Ymd, or false if no versions could be retrieved
	 */
	protected function getSilkBrowserVersions(bool $rebuild = false) : array|false {
		return $this->getFromUptodown('https://silk-browser.en.uptodown.com/android/versions', $rebuild);
	}

	/**
	 * Retrieves the Kiwi Browser versions from Uptodown
	 *
	 * @param bool $rebuild Whether to bypass the local cache and fetch the source again
	 * @return array<string,int>|false An array of versions mapped to their release date as an integer in the format Ymd, or false if no versions could be retrieved
	 */
	protected function getKiwiBrowserVersions(bool $rebuild = false) : array|false {
		return $this->getFromUptodown('https://kiwi-browser.en.uptodown.com/android/versions', $rebuild);
	}

	/**
	 * Retrieves the DuckDuckGo Browser versions from the GitHub releases
	 *
	 * @param bool $rebuild Whether to bypass the local cache and fetch the source again
	 * @return array<string,int>|false An array of versions mapped to their release date as an integer in the format Ymd, or false if no versions could be retrieved
	 */
	protected function getDuckDuckBrowserVersions(bool $rebuild = false) : array|false {

		// the releases are also listed at https://duckduckgo-search-and-stories.en.uptodown.com/android/versions,
		// but Uptodown blocks requests from datacentre IP addresses, so GitHub is the more reliable source
		return $this->getGithubReleases('duckduckgo', 'Android', null, $rebuild);
	}

	/**
	 * Retrieves the K-Meleon versions from the downloads archive on their wiki
	 *
	 * @param bool $rebuild Whether to bypass the local cache and fetch the source again
	 * @return array<string,int>|false An array of versions mapped to their release date as an integer in the format Ymd, or false if no versions could be retrieved
	 */
	protected function getKmeleonVersions(bool $rebuild = false) : array|false {
		$data = [];
		$url = 'http://kmeleonbrowser.org/wiki/DownloadsArchive';
		if (($html = $this->fetch($url, true, $rebuild)) !== false) {
			$obj = new \hexydec\html\htmldoc();
			if ($obj->load($html)) {
				foreach ($obj->find('.text-body h4') AS $row) {
					$title = $row->text();
					if (\preg_match('/([0-9.]++)[^(]++\(([0-9-]++)\)/', $title, $match)) {
						$data[$match[1]] = \intval(\str_replace('-', '', $match[2]));

					}
				}
			}
		}
		return $data ?: false;
	}

	/**
	 * Retrieves the Konqueror versions from the repository tags on the KDE GitLab, paging through the tags on rebuild
	 *
	 * @param bool $rebuild Whether to page through the whole tag history, bypassing the local cache
	 * @return array<string,int>|false An array of versions mapped to their release date as an integer in the format Ymd, or false if no versions could be retrieved
	 */
	protected function getKonquerorVersions(bool $rebuild = false) : array|false {
		$path = '/network/konqueror/-/tags';
		$data = [];
		while ($path !== null) {
			if (($html = $this->fetch('https://invent.kde.org'.$path, true, $rebuild)) !== false) {
				$obj = new \hexydec\html\htmldoc();
				if ($obj->load($html)) {
					foreach ($obj->find('.content-list > li') AS $row) {
						$title = \ltrim($row->find('h2 > a')->text(), 'v');

						// attr() returns null when the attribute isn't there, which DateTime() cannot take
						if ($title !== '' && ($date = $row->find('time')->attr('datetime') ?? '') !== '') {
							$data[$title] = \intval((new \DateTime($date))->format('Ymd'));
						}
					}
					$path = $rebuild ? $obj->find('a[rel=next]')->attr('href') : null;
				} else {
					break;
				}
			} else {
				break;
			}
		}
		return $data ?: false;
	}

	/**
	 * Retrieves the Waterfox versions from their RSS feed
	 *
	 * @param bool $rebuild Whether to bypass the local cache and fetch the feed again
	 * @return array<string,int>|false An array of versions mapped to their release date as an integer in the format Ymd, or false if no versions could be retrieved
	 */
	protected function getWaterfoxVersions(bool $rebuild = false) : array|false {
		$data = [];
		$url = 'https://www.waterfox.com/rss.xml';
		if (($xml = $this->fetch($url, true, $rebuild)) !== false && ($obj = \simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOERROR | LIBXML_NOWARNING)) !== false) {
			foreach ($obj->xpath('//channel/item') AS $item) {
				if (\preg_match('/^Waterfox (\d[0-9.]+)/', (string) $item->title, $match)) {
					$data[$match[1]] = \intval((new \DateTime((string) $item->pubDate))->format('Ymd'));
				}
			}
		}
		return $data ?: false;
	}

	/**
	 * Retrieves the Pale Moon versions from a local copy of their releases RSS feed, as their repository blocks automated requests
	 *
	 * @param bool $rebuild Whether to bypass the local cache, which has no effect as the source is a local file
	 * @return array<string,int>|false An array of versions mapped to their release date as an integer in the format Ymd, or false if no versions could be retrieved
	 */
	protected function getPalemoonVersions(bool $rebuild = false) : array|false {
		$data = [];
		// $url = 'https://repo.palemoon.org/MoonchildProductions/Pale-Moon/releases.rss';
		// if (($xml = $this->fetch($url, true, $rebuild)) !== false && ($obj = \simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOERROR | LIBXML_NOWARNING)) !== false) {
		$file = \dirname(__DIR__).'/source/palemoon-releases.rss';
		if (($obj = \simplexml_load_file($file, 'SimpleXMLElement', LIBXML_NOERROR | LIBXML_NOWARNING)) !== false) {
			foreach ($obj->xpath('//channel/item') AS $item) {
				$title = \trim((string) $item->title);

				// the titles are inconsistent (e.g. "Pale Moon 33.8.0", "Pale moon 31.3.1", "v29.4.6", "Pale Moon 32.5.0 (SunOS)"),
				// so extract the version number rather than trimming the prefix, and skip pre-releases and items with no version
				if (!\preg_match('/beta|release candidate/i', $title) && \preg_match('/[0-9]+(?:\.[0-9]+)+(?:-r[0-9]+)?/', $title, $match)) {
					$data[$match[0]] = \intval((new \DateTime((string) $item->pubDate))->format('Ymd'));
				}
			}
		}
		return $data ?: false;
	}

	/**
	 * Retrieves the Oculus (Meta Quest) Browser versions from Wikipedia
	 *
	 * @param bool $rebuild Whether to bypass the local cache and fetch the source again
	 * @return array<string,int>|false An array of versions mapped to their release date as an integer in the format Ymd, or false if the source could not be retrieved
	 */
	protected function getOculusBrowserVersions(bool $rebuild = false) : array|false {
		$url = 'https://en.wikipedia.org/wiki/Meta_Quest_Browser';
		if (($html = $this->fetch($url, true, $rebuild)) !== false) {
			$data = [];
			$obj = new \hexydec\html\htmldoc();
			if ($obj->load($html)) {
				foreach ($obj->find('table.wikitable > tbody > tr') AS $row) {
					$cells = $row->find('td');
					$version = \trim($cells->eq(0)->text());
					if ($version !== '' && ($text = \trim($cells->eq(1)->text())) !== '') {
						$date = new \DateTime($text);
						$data[$version] = \intval($date->format('Ymd'));
					}
				}
				return $data;
			}
		}
		return false;
	}

	/**
	 * Retrieves the Midori versions from the repository tags on GitHub, paging through the tags on rebuild
	 *
	 * @param bool $rebuild Whether to page through the whole tag history, bypassing the local cache
	 * @return array<string,int>|false An array of versions mapped to their release date as an integer in the format Ymd, or false if no versions could be retrieved
	 */
	protected function getMidoriVersions(bool $rebuild = false) : array|false {
		$data = [];
		$path = '/goastian/midori-desktop/tags';
		while ($path !== null) {
			if (($html = $this->fetch('https://github.com'.$path, true, $rebuild)) !== false) {
				$obj = new \hexydec\html\htmldoc();
				if ($obj->load($html)) {

					// extract versions
					foreach ($obj->find('.Box-row') AS $row) {
						$version = \ltrim($row->find('.Link--primary')->text(), 'v');
						if ($version !== '' && ($date = $row->find('relative-time')->text()) !== '') {
							$data[$version] = \intval((new \DateTime($date))->format('Ymd'));
						}
					}

					// get next page
					$found = false;
					if ($rebuild) {
						foreach ($obj->find('.pagination a') AS $item) {
							if ($item->text() === 'Next' && ($href = $item->attr('href')) !== null) {
								$path = $href !== $path ? $href : null;
								$found = true;
								break;
							}
						}
					}
					if (!$found) {
						break;
					}
				} else {
					break;
				}
			} else {
				break;
			}
		}
		return $data ?: false;
	}
}