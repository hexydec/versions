<?php
declare(strict_types = 1);
namespace hexydec\versions;
class browsers {

	protected array $config = [
		'msg' => null, // a callback for where the messages are written
		'cache' => null // to cache the source content, specify the absolute cache directory
	];
	protected array $timing = [];

	public function __construct(array $config = []) {
		$this->config = $config;
	}

	public function build(string $target, bool $rebuild = false) : bool {
		$browsers = [
			'chrome' => [$this, 'getChromeVersions'],
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
			// 'silk' => [$this, 'getSilkBrowserVersions'],
			'waterfox' => [$this, 'getWaterfoxVersions'],
			// 'palemoon' => [$this, 'getPaleMoonVersions'],
			'oculus' => [$this, 'getOculusBrowserVersions'],
			'midori' => [$this, 'getMidoriVersions']
		];

		// read existing file
		if ($rebuild || !\file_exists($target)) {
			$data = [];
		} elseif (($json = \file_get_contents($target)) === false) {
			\trigger_error('Could not read file', E_USER_WARNING);
			$data = [];
		} elseif (($data = \json_decode($json, true)) === false) {
			\trigger_error('Data is not valid JSON', E_USER_WARNING);
			$data = [];
		}

		// update browser versions
		$added = 0;
		$total = 0;
		foreach ($browsers AS $key => $item) {
			if (($results = \call_user_func($item, $rebuild)) !== false) {
				$new = $rebuild ? $results : \array_diff_key($results, $data[$key]);
				$count = \count($new);
				$this->msg('Found '.$count.' for '.\ucfirst($key));
				$data[$key] = $rebuild ? $results : \array_replace($data[$key], $results);
				\arsort($data[$key]);
				$added += $count;
				$total += \count($data[$key]);
			} else {
				\trigger_error('Could not generate versions for '.\ucfirst($key), E_USER_WARNING);
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

	protected function msg(string $msg) : void {
		\call_user_func($this->config['msg'] ?? function (string $msg) : void {echo $msg."\n";}, $msg);
	}

	protected function fetch(string $url, bool $contents = true) : string|false {
		$cache = $this->config['cache'];
		$local = null;

		// generate cache file name
		if ($cache !== null) {
			$local = $cache.\preg_replace('/[^0-9a-z]+/i', '-', $url).'.cache';
		}

		// fetch from local cache
		if ($local !== null && \file_exists($local) && (!$contents || ($file = \file_get_contents($local)) !== false)) {
			return $contents ? $file : $local;

		// download file
		} else {

			// create cache directory
			if ($local !== null && !\is_dir($cache)) {
				\mkdir($cache, 0755);
			}

			// save timing
			if (($host = \parse_url($url, PHP_URL_HOST)) !== false) {
				$wait = 2;
				if (isset($this->timing[$host]) && \microtime(true) < $this->timing[$host] + $wait) {
					\sleep($wait);
				}
			}
			$context = \stream_context_create([
				'http' => [
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
				]
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
			$this->timing[$host] = \microtime(true);
			return $file;
		}
		return false;
	}

	protected function getFromJson(string $url, array $version, array $date) {
		if (($file = $this->fetch($url)) !== false && ($json = \json_decode($file)) !== null && !empty($json)) {
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

	protected function getChromeVersions(bool $rebuild = false) : array|false {
		$items = $rebuild ? 1000 : 20;
		$url = 'https://chromiumdash.appspot.com/fetch_releases?channel=Stable&platform=Windows&num='.$items.'&offset=';
		$chrome = [];
		for ($i = 0; $i < 10; $i++) {
			if (($data = $this->getFromJson($url.($i * $items), ['version'], ['time'])) !== false) {
				$chrome = \array_merge($chrome, \array_map(fn (int $time) : int => \intval(\date('Ymd', \intval($time / 1000))), $data));
				if (\count($data) < $items || !$rebuild) {
					break;
				}
			} else {
				break;
			}
		}
		return $chrome ?: false;
	}

	protected function getFirefoxVersions(bool $rebuild = false) : array|false {
		$url = 'https://whattrainisitnow.com/calendar/';
		if (($html = $this->fetch($url)) !== false) {
			$data = [];
			$obj = new \hexydec\html\htmldoc();
			if ($obj->load($html)) {
				foreach ($obj->find('table') AS $item) {
					$caption = $item->find('caption')->text();
					if ($caption === 'Past releases') {
						foreach ($item->find('tbody > tr') AS $row) {
							$data[$row->find('td:first-child')->text()] = \intval(\str_replace('-', '', $row->find('td:last-child')->text()));
						}
					}
				}
				return $data;
			}
		}
		return false;
	}

	protected function getLegacyEdgeVersions() : array|false {
		$url = 'https://en.wikipedia.org/wiki/EdgeHTML';
		if (($html = $this->fetch($url)) !== false) {
			$data = [];
			$obj = new \hexydec\html\htmldoc();
			if ($obj->load($html)) {
				foreach ($obj->find('table.wikitable > tbody > tr') AS $row) {
					$cells = $row->find('td');
					if (($text = $cells->eq(1)->text()) !== '') {
						$date = new \DateTime($text);
						$data[\trim($cells->eq(0)->text())] = \intval($date->format('Ymd'));
					}
				}
				return $data;
			}
		}
		return false;
	}

	protected function getEdgeVersions(bool $rebuild = false) : array|false {
		$data = $rebuild ? $this->getLegacyEdgeVersions() : [];

		// this is the most up to date page
		$url = 'https://learn.microsoft.com/en-us/deployedge/microsoft-edge-release-schedule';
		if (($html = $this->fetch($url)) !== false) {
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
		if ($rebuild && ($html = $this->fetch($url)) !== false) {
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

	protected function getSafariVersions(bool $rebuild = false) : array|false {
		$url = 'https://developer.apple.com/tutorials/data/documentation/safari-release-notes.json';
		if (($file = $this->fetch($url)) !== false && ($json = \json_decode($file)) !== null) {
			$data = $rebuild ? $this->getLegacySafariVersions() : [];
			foreach ($json->references AS $item) {
				$text = $item->abstract[0]->text;
				if (\preg_match('/([a-z]++ [0-9]{1,2}, [0-9]{4})[^0-9]++([0-9.]++) \(([0-9.]++)\)/i', $text, $match)) {
					$date = new \DateTime($match[1]);
					$data[$match[2]] = \intval($date->format('Ymd'));
				}
			}
			return $data;
		}
		return false;
	}

	protected function getInternetExplorerVersions() {
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

	protected function getOperaClassicVersions(bool $rebuild = false) : array|false {
		$url = 'https://help.opera.com/en/operas-archived-history/';
		if (($html = $this->fetch($url)) !== false) {
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

	protected function getOperaVersions(bool $rebuild = false) : array|false {
		$url = 'https://en.wikipedia.org/wiki/History_of_the_Opera_web_browser';
		$data = [];

		// get classic opera versions
		if ($rebuild && ($data = $this->getOperaClassicVersions()) === false) {

		// get current versions
		} elseif (($html = $this->fetch($url)) !== false) {
			$obj = new \hexydec\html\htmldoc();
			if ($obj->load($html)) {
				foreach ($obj->find('p') AS $row) {
					$text = $row->text();

					// match "Opera XXX was released on Month XXst, XXXX"
					if (\preg_match('/Opera ([0-9]++) was released on ([a-z]++ [0-9]++, [0-9]{4})/i', $text, $match)) {
						$date = new \DateTime($match[2]);
						$data[$match[1]] = \intval($date->format('Ymd'));

					// match Month XXst, XXXX, [some text] Opera XXX was released.
					} elseif (\preg_match('/([a-z]++ [0-9]++, [0-9]{4}), [a-z ]*Opera ([0-9]++)(?:\.0)? was released\./i', $text, $match)) {
						$date = new \DateTime($match[1]);
						$data[$match[2]] = \intval($date->format('Ymd'));
					}
				}
			}
		}
		return $data ?: false;
	}

	protected function getBraveVersions(bool $rebuild = false) : array|false {
		$data = $rebuild ? $this->getApkMirror('/uploads/?appcategory=brave-browser', 'Brave Private Web Browser, VPN ') : [];
		$url = 'https://versions.brave.com/latest/brave-versions.json';
		if (($file = $this->fetch($url)) !== false && ($json = \json_decode($file)) !== null) {
			foreach ($json AS $item) {
				if ($item->channel === 'release') {
					$data[$item->name] = \intval(\str_replace('-', '', \substr($item->published, 0, 10)));
				}
			}
		}
		return $data ?: false;
	}

	protected function getVivaldiVersions(bool $rebuild = false) : array|false {
		$data = [];
		$url = 'https://vivaldi.com/download/archive/?platform=win';
		if (($html = $this->fetch($url)) !== false) {
			$obj = new \hexydec\html\htmldoc();
			if ($obj->load($html)) {
				foreach ($obj->find('tbody > tr') AS $row) {
					$text = $row->find('td > a')->eq(0)->text();
					if (\str_ends_with($text, '.x64.exe')) {
						$data[\substr($text, 8, -8)] = \intval(\str_replace('-', '', $row->find('td:first-child + td')->text()));
					}
				}
				return $data;
			}
		}
		return $data ?: false;
	}

	protected function getMaxthonVersions(bool $rebuild = false) : array|false {
		$data = [];
		$url = 'https://www.maxthon.com/history';
		if (($html = $this->fetch($url)) !== false) {
			$obj = new \hexydec\html\htmldoc();
			if ($obj->load($html)) {
				foreach ($obj->find('.history-list-text') AS $row) {
					$title = $row->find('.history-list-title > span')->eq(0)->text();
					if (\preg_match('/[0-9.]{3,}/', $title, $match)) {
						$date = $row->find('.history-list-desc > span')->eq(1)->text();
						$data[$match[0]] = \intval(\str_replace('-', '', $date.(\strlen($date) === 4 ? '-01-01' : '')));

					}
				}
				return $data;
			}
		}
		return $data ?: false;
	}

	protected function getApkMirror(string $path, string $prefix, bool $rebuild = false, array $not = []) : array|false {
		$data = [];
		$len = \strlen($prefix);
		while ($path !== null) {
			if (($html = $this->fetch('https://www.apkmirror.com'.$path)) !== false) {
				$obj = new \hexydec\html\htmldoc();
				if ($obj->load($html)) {
					foreach ($obj->find('.table-row') AS $row) {
						$title = $row->find('a.fontBlack')->text();
						if (\str_starts_with($title, $prefix)) {
							$date = new \DateTime($row->find('.visible-xs .dateyear_utc')->eq(0)->text());
							$version = \explode(' ', \substr($title, $len))[0];
							if (!\in_array($version, $not)) {
								$data[$version] = \intval($date->format('Ymd'));
							}
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

	protected function getSamsungInternetVersions(bool $rebuild = false) : array|false {
		return $this->getApkMirror('/uploads/?appcategory=samsung-internet-for-android', 'Samsung Internet Browser ', $rebuild);
	}

	protected function getHuaweiBrowserVersions(bool $rebuild = false) : array|false {
		return $this->getApkMirror('/uploads/?appcategory=huawei-browser', 'HUAWEI Browser ', $rebuild, ['50.50.50.6']);
	}

	protected function getUcBrowserVersions(bool $rebuild = false) : array|false {
		return $this->getApkMirror('/uploads/?appcategory=uc-browser', 'UC Browser-Safe, Fast, Private ', $rebuild);
	}

	// protected function getSilkBrowserVersions(bool $rebuild = false) : array|false {
	// 	if (($data = $this->getApkMirror('/uploads/?appcategory=silk-browser', 'Silk Browser ', $rebuild)) !== false) {
	// 		return \array_filter($data, fn (int $item, string $key) : bool => \intval($key) >= 96, ARRAY_FILTER_USE_BOTH); // the datasource has big gaps before v96, which is not helpful for dermining user agent tampering, so just binning them
	// 	}
	// 	return false;
	// }

	protected function getKmeleonVersions(bool $rebuild = false) : array|false {
		$data = [];
		$url = 'http://kmeleonbrowser.org/wiki/DownloadsArchive';
		if (($html = $this->fetch($url)) !== false) {
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

	protected function getKonquerorVersions(bool $rebuild = false) : array|false {
		$path = '/network/konqueror/-/tags';
		$data = [];
		while ($path !== null) {
			if (($html = $this->fetch('https://invent.kde.org'.$path)) !== false) {
				$obj = new \hexydec\html\htmldoc();
				if ($obj->load($html)) {
					foreach ($obj->find('.content-list > li') AS $row) {
						$title = \ltrim($row->find('a.gl-font-bold')->text(), 'v');
						$date = new \DateTime($row->find('time')->attr('datetime'));
						$data[$title] = \intval($date->format('Ymd'));
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

	protected function getWaterfoxVersions(bool $rebuild = false) : array|false {
		$data = [];
		$url = 'https://www.waterfox.net/download/';
		if (($html = $this->fetch($url)) === false) {

		} elseif (($start = \mb_strpos($html, '(function(){const items = [{')) === false) {
			
		} elseif (($end = \mb_strpos($html, ';', $start)) === false) {

		} elseif (($json = \mb_substr($html, $start + 26, $end - $start - 26)) === false) {
			
		} elseif (($items = \json_decode($json, true)) === null) {
			
		} else {
			foreach ($items AS $item) {
				$data[$item['label']] = \intval((new \DateTime($item['pubDate']))->format('Ymd'));
			}
		}
		return $data ?: false;
	}

	protected function getPalemoonVersions(bool $rebuild = false) : array|false {
		$data = [];
		$url = 'https://repo.palemoon.org/MoonchildProductions/Pale-Moon/releases.rss';
		if (($xml = $this->fetch($url)) !== false) {
			$obj = \simplexml_load_string($xml);
			foreach ($obj->xpath('//channel/item') AS $item) {
				$data[\mb_substr((string) $item->title, 10)] = \intval((new \DateTime((string) $item->pubDate))->format('Ymd'));
			}
		}
		return $data ?: false;
	}

	protected function getOculusBrowserVersions(bool $rebuild = false) : array|false {
		$url = 'https://en.wikipedia.org/wiki/Meta_Quest_Browser';
		if (($html = $this->fetch($url)) !== false) {
			$data = [];
			$obj = new \hexydec\html\htmldoc();
			if ($obj->load($html)) {
				foreach ($obj->find('table.wikitable > tbody > tr') AS $row) {
					$cells = $row->find('td');
					if (($text = \trim($cells->eq(1)->text())) !== '') {
						$date = new \DateTime($text);
						$data[\trim($cells->eq(0)->text())] = \intval($date->format('Ymd'));
					}
				}
				return $data;
			}
		}
		return false;
	}

	protected function getMidoriVersions(bool $rebuild = false) : array|false {
		$data = [];
		$path = '/goastian/midori-desktop/tags';
		while ($path !== null) {
			if (($html = $this->fetch('https://github.com'.$path)) !== false) {
				$obj = new \hexydec\html\htmldoc();
				if ($obj->load($html)) {

					// extract versions
					foreach ($obj->find('.Box-row') AS $row) {
						$date = new \DateTime($row->find('relative-time')->text());
						$data[\ltrim($row->find('.Link--primary')->text(), 'v')] = \intval($date->format('Ymd'));
					}

					// get next page
					$found = false;
					foreach ($obj->find('.pagination a') AS $item) {
						if ($item->text() === 'Next' && ($href = $item->attr('href')) !== null) {
							$path = $href !== $path ? $href : null;
							$found = true;
							break;
						}
					}
					if (!$found) {
						break;
					}
				}
			}
		}
		return $data ?: false;
	}

	protected function renderPhp(array $data) {
		$php = [
			'<?php',
			'declare(strict_types=1);',
			'namespace hexydec\\versions;',
			'',
			'class versions {',
			'',
			"\tpublic static function get() : array {",
			"\t\treturn ["
		];
		foreach ($data AS $key => $item) {
			$php[] = "\t\t\t'".$key."' => [";
			foreach ($item AS $version => $date) {
				$php[] = "\t\t\t\t'".$version."' => '".$date."',";
			}
			$php[] = "\t\t\t],";
		}
		$php[] = "\t\t];";
		$php[] = "\t}";
		$php[] = "}";
		return \implode("\n", $php);
	}
}