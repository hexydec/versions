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
			'silk' => [$this, 'getSilkBrowserVersions'],
			'waterfox' => [$this, 'getWaterfoxVersions'],
			'palemoon' => [$this, 'getPaleMoonVersions']
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
				$chrome = \array_merge($chrome, \array_map(fn (int $time) => \date('Y-m-d', \intval($time / 1000)), $data));
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
							$data[$row->find('td:first-child')->text()] = $row->find('td:last-child')->text();
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
						$data[\trim($cells->eq(0)->text())] = $date->format('Y-m-d');
					}
				}
				return $data;
			}
		}
		return false;
	}

	protected function getEdgeVersions(bool $rebuild = false) : array|false {
		$url = 'https://learn.microsoft.com/en-us/deployedge/microsoft-edge-release-schedule';
		if (($html = $this->fetch($url)) !== false) {
			$data = $rebuild ? $this->getLegacyEdgeVersions() : [];
			$obj = new \hexydec\html\htmldoc();
			if ($obj->load($html)) {
				foreach ($obj->find('table > tbody > tr') AS $row) {
					if (\preg_match('/([0-9]{2}-[a-z]{3}-[0-9]{4})\W++([0-9.]{4,})/i', $row->find('td')->eq(3)->text(), $match)) {
						$date = new \DateTime($match[1]);
						$data[$match[2]] = $date->format('Y-m-d');
					}
				}
				return $data;
			}
		}
		return false;
	}

	protected function getLegacySafariVersions() : array {
		return [
			'1' => '2003-06-23',
			'2' => '2005-04-29',
			'2.0.2' => '2005-10-31',
			'2.0.4' => '2006-01-10',
			'3' => '2007-01-09',
			'3.0.1' => '2007-06-14',
			'3.0.2' => '2007-06-22',
			'3.1' => '2008-03-18',
			'3.1.2' => '2008-06-01',
			'3.2' => '2008-11-13',
			'3.2.3' => '2009-05-12',
			'4' => '2009-06-08',
			'4.0.1' => '2009-06-17',
			'4.0.4' => '2009-11-11',
			'5' => '2010-06-07',
			'5.0.6' => '2010-06-31',
			'5.1' => '2011-07-20',
			'6' => '2012-07-25',
			'7' => '2013-10-22',
			'8' => '2014-06-06',
			'9' => '2015-06-12',
			'10' => '2016-09-20',
			'10.1.2' => '2017-07-19',
			'11' => '2017-09-19',
			'12' => '2018-09-17',
			'12.0.1' => '2018-10-30',
			'12.0.2' => '2018-12-05'
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
					$data[$match[2]] = $date->format('Y-m-d');
				}
			}
			return $data;
		}
		return false;
	}

	protected function getInternetExplorerVersions() {
		return [
			'1' => '1995-07-24',
			'2' => '1995-11-27',
			'3' => '1996-08-13',
			'4' => '1997-09-22',
			'5' => '1999-03-18',
			'5.0.1' => '1999-12-01',
			'5.5' => '2000-05-19',
			'6' => '2001-08-24',
			'7' => '2006-10-18',
			'8' => '2009-03-19',
			'9' => '2011-03-19',
			'10' => '2012-10-26',
			'11' => '2013-10-17',
			'11.0.7' => '2014-04-08',
			'11.0.11' => '2014-08-12',
			'11.0.15' => '2014-12-09',
			'11.0.25' => '2015-11-12'
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
						$data[\explode(' ', $cells->eq(0)->text())[1]] = $cells->eq(1)->text();
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
		if ($rebuild && ($data = $this->getOperaClassicVersions()) === false) {

		} elseif (($html = $this->fetch($url)) !== false) {
			$obj = new \hexydec\html\htmldoc();
			if ($obj->load($html)) {
				foreach ($obj->find('p') AS $row) {
					$text = $row->text();
					if (\preg_match('/Opera ([0-9]++) was released on ([a-z]++ [0-9]++, [0-9]{4})/i', $text, $match)) {
						$date = new \DateTime($match[2]);
						$data[$match[1]] = $date->format('Y-m-d');
					} elseif (\preg_match('/([a-z]++ [0-9]++, [0-9]{4}), [a-z ]*Opera ([0-9]++)(?:\.0)? was released\./i', $text, $match)) {
						$date = new \DateTime($match[1]);
						$data[$match[2]] = $date->format('Y-m-d');
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
					$data[$item->name] = \substr($item->published, 0, 10);
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
						$data[\substr($text, 8, -8)] = $row->find('td:first-child + td')->text();
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
						$data[$match[0]] = $date.(\strlen($date) === 4 ? '-01-01' : '');

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
								$data[$version] = $date->format('Y-m-d');
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

	protected function getSilkBrowserVersions(bool $rebuild = false) : array|false {
		return $this->getApkMirror('/uploads/?appcategory=silk-browser', 'Silk Browser ', $rebuild);
	}

	protected function getKmeleonVersions(bool $rebuild = false) : array|false {
		$data = [];
		$url = 'http://kmeleonbrowser.org/wiki/DownloadsArchive';
		if (($html = $this->fetch($url)) !== false) {
			$obj = new \hexydec\html\htmldoc();
			if ($obj->load($html)) {
				foreach ($obj->find('.text-body h4') AS $row) {
					$title = $row->text();
					if (\preg_match('/([0-9.]++)[^(]++\(([0-9-]++)\)/', $title, $match)) {
						$data[$match[1]] = $match[2];

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
						$data[$title] = $date->format('Y-m-d');
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
				$data[$item['label']] = (new \DateTime($item['pubDate']))->format('Y-m-d');
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
				$data[\mb_substr((string) $item->title, 10)] = (new \DateTime((string) $item->pubDate))->format('Y-m-d');
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