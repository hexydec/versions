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
			echo 'Fetching "'.$url.($i * $items).'"'."\n";
			if (($data = $this->getFromJson($url.($i * $items), ['version'], ['time'], $cache)) !== false) {
				echo 'Found '.\count($data).' Chrome versions'."\n";
				$chrome = \array_merge($chrome, \array_map(fn (int $time) => \date('Y-m-d', $time), $data));
				if (\count($data) < $items) {
					return $chrome;
				} else {
					sleep(5);
				}
			} else {
				return $chrome;
			}
		}
		return false;
	}

	public function getFirefoxVersions(?string $cache = null) : array|false {
		$url = 'https://whattrainisitnow.com/calendar/';
		if (($html = $this->fetch($url, $cache)) !== false) {
			echo 'Fetching "'.$url.'"'."\n";
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
				echo 'Found '.\count($data).' Firefox versions'."\n";
				return $data;
			}
		}
		return false;
	}
}