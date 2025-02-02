<?php
declare(strict_types = 1);
namespace hexydec\versions;

class generate {

	protected array $timing = [];

	public function fetch(string $url, ?string $cache = null, bool $contents = true) : string|false {
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
					'user_agent' => 'Mozilla/5.0 (compatible; Hexydec IP Ranges Bot/1.0; +https://github.com/hexydec/ip-ranges/)',
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

	public function getFromJson(string $url, array $version, array $date, string $cache = null) {
		if (($file = $this->fetch($url, $cache)) !== false && ($json = \json_decode($file)) !== null && !empty($json)) {
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

	public function getChromeVersions(?string $cache = null) : array|false {
		$items = 1000;
		$url = 'https://chromiumdash.appspot.com/fetch_releases?channel=Stable&platform=Windows&num='.$items.'&offset=';
		$chrome = [];
		for ($i = 0; $i < 10; $i++) {
			if (($data = $this->getFromJson($url.($i * $items), ['version'], ['time'], $cache)) !== false) {
				$chrome = \array_merge($chrome, \array_map(fn (int $time) => \date('Y-m-d', \intval($time / 1000)), $data));
				if (\count($data) < $items) {
					break;
				} else {
					sleep(5);
				}
			} else {
				break;
			}
		}
		\ksort($chrome, SORT_NUMERIC);
		return $chrome ?: false;
	}

	public function getFirefoxVersions(?string $cache = null) : array|false {
		$url = 'https://whattrainisitnow.com/calendar/';
		if (($html = $this->fetch($url, $cache)) !== false) {
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
				\ksort($data, SORT_NUMERIC);
				return $data;
			}
		}
		return false;
	}

	protected function getLegacyEdgeVersions(?string $cache = null) : array|false {
		$url = 'https://en.wikipedia.org/wiki/EdgeHTML';
		if (($html = $this->fetch($url, $cache)) !== false) {
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
				\ksort($data, SORT_NUMERIC);
				return $data;
			}
		}
		return false;
	}

	public function getEdgeVersions(?string $cache = null) : array|false {
		$url = 'https://learn.microsoft.com/en-us/deployedge/microsoft-edge-release-schedule';
		if (($html = $this->fetch($url, $cache)) !== false) {
			$data = $this->getLegacyEdgeVersions($cache);
			$obj = new \hexydec\html\htmldoc();
			if ($obj->load($html)) {
				foreach ($obj->find('table > tbody > tr') AS $row) {
					if (\preg_match('/([0-9]{2}-[a-z]{3}-[0-9]{4})\W++([0-9.]{4,})/i', $row->find('td')->eq(3)->text(), $match)) {
						$date = new \DateTime($match[1]);
						$data[$match[2]] = $date->format('Y-m-d');
					}
				}
				\ksort($data, SORT_NUMERIC);
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

	public function getSafariVersions(?string $cache = null) : array|false {
		$url = 'https://developer.apple.com/tutorials/data/documentation/safari-release-notes.json';
		if (($file = $this->fetch($url, $cache)) !== false && ($json = \json_decode($file)) !== null) {
			$data = $this->getLegacySafariVersions();
			foreach ($json->references AS $item) {
				$text = $item->abstract[0]->text;
				if (\preg_match('/([a-z]++ [0-9]{1,2}, [0-9]{4})[^0-9]++([0-9.]++) \(([0-9.]++)\)/i', $text, $match)) {
					$date = new \DateTime($match[1]);
					$data[$match[2]] = $date->format('Y-m-d');
				}
			}
			\ksort($data, SORT_NUMERIC);
			return $data;
		}
		return false;
	}

	public function getInternetExplorerVersions() {
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

	protected function getOperaClassicVersions(?string $cache = null) : array|false {
		$url = 'https://help.opera.com/en/operas-archived-history/';
		if (($html = $this->fetch($url, $cache)) !== false) {
			$data = [];
			$obj = new \hexydec\html\htmldoc();
			if ($obj->load($html)) {
				foreach ($obj->find('tbody > tr') AS $row) {
					$cells = $row->find('th,td');
					if ($cells->eq(2)->text() === 'Final') {
						$data[\explode(' ', $cells->eq(0)->text())[1]] = $cells->eq(1)->text();
					}
				}
				\ksort($data, SORT_NUMERIC);
				return $data;
			}
		}
		return false;
	}

	public function getOperaVersions(?string $cache = null) : array|false {
		$url = 'https://en.wikipedia.org/wiki/History_of_the_Opera_web_browser';
		if (($data = $this->getOperaClassicVersions($cache)) === false) {

		} elseif (($html = $this->fetch($url, $cache)) !== false) {
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
				\ksort($data, SORT_NUMERIC);
			}
		}
		return $data ?: false;
	}

	public function getBraveVersions(?string $cache = null) : array|false {
		$data = [];
		$url = 'https://versions.brave.com/latest/brave-versions.json';
		if (($file = $this->fetch($url, $cache)) !== false && ($json = \json_decode($file)) !== null) {
			foreach ($json AS $item) {
				if ($item->channel === 'release') {
					$data[$item->name] = \substr($item->published, 0, 10);
				}
			}
		}
		return $data ?: false;
	}

	public function getVivaldiVersions(?string $cache = null) : array|false {
		$data = [];
		$url = 'https://vivaldi.com/download/archive/?platform=win';
		if (($html = $this->fetch($url, $cache)) !== false) {
			$obj = new \hexydec\html\htmldoc();
			if ($obj->load($html)) {
				foreach ($obj->find('tbody > tr') AS $row) {
					$text = $row->find('td > a')->eq(0)->text();
					if (\str_ends_with($text, '.x64.exe')) {
						$data[\substr($text, 8, -8)] = $row->find('td:first-child + td')->text();
					}
				}
				\ksort($data, SORT_NUMERIC);
				return $data;
			}
		}
		return $data ?: false;
	}

	public function getMaxathonVersions(?string $cache = null) : array|false {
		$data = [];
		$url = 'https://www.maxthon.com/history';
		if (($html = $this->fetch($url, $cache)) !== false) {
			$obj = new \hexydec\html\htmldoc();
			if ($obj->load($html)) {
				foreach ($obj->find('.history-list-text') AS $row) {
					$title = $row->find('.history-list-title > span')->eq(0)->text();
					if (\preg_match('/[0-9.]{3,}/', $title, $match)) {
						$date = $row->find('.history-list-desc > span')->eq(1)->text();
						$data[$match[0]] = $date.(\strlen($date) === 4 ? '-01-01' : '');

					}
				}
				\ksort($data, SORT_NUMERIC);
				return $data;
			}
		}
		return $data ?: false;
	}

	public function getSamsungInternetVersions(?string $cache = null) : array|false {
		$data = [];
		$url = '/uploads/?appcategory=samsung-internet-for-android';
		while ($url !== null) {
			if (($html = $this->fetch('https://www.apkmirror.com'.$url, $cache)) !== false) {
				$obj = new \hexydec\html\htmldoc();
				if ($obj->load($html)) {
					foreach ($obj->find('.table-row') AS $row) {
						$title = $row->find('a.fontBlack')->text();
						if (\str_starts_with($title, 'Samsung Internet Browser ')) {
							$date = new \DateTime($row->find('.visible-xs .dateyear_utc')->eq(0)->text());
							$data[\explode(' ', \substr($title, 25))[0]] = $date->format('Y-m-d');
						}
					}
					$url = $obj->find('a[rel=next]')->attr('href');
				} else {
					break;
				}
			} else {
				break;
			}
		}
		\ksort($data, SORT_NUMERIC);
		return $data ?: false;
	}

	public function renderPhp(array $data) {
		$php = [
			'<?php',
			'declare(strict_types=1);',
			'namespace hexydec\\version;',
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