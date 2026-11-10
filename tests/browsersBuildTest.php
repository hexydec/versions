<?php
declare(strict_types = 1);
use hexydec\versions\browsersStub;

/**
 * Exercises build()'s merge, sort, and write orchestration through a stub, so none of these tests
 * touch the network and they cannot be affected by a source going down or blocking the request
 */
final class browsersBuildTest extends \PHPUnit\Framework\TestCase {

	/**
	 * @var array<int,string> $messages The progress messages written by the object under test
	 */
	protected array $messages = [];

	protected function getBrowsers() : browsersStub {
		$this->messages = [];
		return new browsersStub([
			'msg' => function (string $msg) : void {
				$this->messages[] = $msg;
			}
		]);
	}

	protected function getTarget() : string {
		return \sys_get_temp_dir().'/versions-build-test-'.\uniqid().'/versions.json';
	}

	protected function remove(string $target) : void {
		if (\file_exists($target)) {
			\unlink($target);
		}
		if (\is_dir($dir = \dirname($target))) {
			\rmdir($dir);
		}
	}

	/**
	 * @return array<mixed> The decoded contents of the generated file
	 */
	protected function getOutput(string $target) : array {
		$this->assertFileExists($target);
		$data = \json_decode((string) \file_get_contents($target), true);
		$this->assertIsArray($data);
		return $data;
	}

	public function testBuildCreatesTheOutputDirectoryAndFile() : void {
		$target = $this->getTarget();
		try {
			$browsers = $this->getBrowsers()->stub('chrome', ['141.0.7390.55' => 20251001]);
			$this->assertTrue($browsers->build($target));
			$this->assertSame(['chrome' => ['141.0.7390.55' => 20251001]], $this->getOutput($target));
			$this->assertContains('Found 1 for Chrome', $this->messages);
			$this->assertContains('Added 1 and saved 1 browser versions', $this->messages);
		} finally {
			$this->remove($target);
		}
	}

	public function testBuildSortsVersionsByDateDescending() : void {
		$target = $this->getTarget();
		try {
			$browsers = $this->getBrowsers()->stub('chrome', [
				'140.0.7339.80' => 20250902,
				'142.0.7444.60' => 20251028,
				'141.0.7390.55' => 20251001
			]);
			$this->assertTrue($browsers->build($target));
			$data = $this->getOutput($target);
			$this->assertSame(['142.0.7444.60', '141.0.7390.55', '140.0.7339.80'], \array_keys($data['chrome']));
		} finally {
			$this->remove($target);
		}
	}

	public function testBuildMergesNewVersionsIntoTheExistingFile() : void {
		$target = $this->getTarget();
		try {
			\mkdir(\dirname($target), 0755);
			\file_put_contents($target, (string) \json_encode(['chrome' => ['141.0.7390.55' => 20251001]]));
			$browsers = $this->getBrowsers()->stub('chrome', ['142.0.7444.60' => 20251028]);
			$this->assertTrue($browsers->build($target));
			$data = $this->getOutput($target);
			$this->assertSame(['142.0.7444.60' => 20251028, '141.0.7390.55' => 20251001], $data['chrome']);

			// only the version that wasn't already present is counted as added
			$this->assertContains('Found 1 for Chrome', $this->messages);
			$this->assertContains('Added 1 and saved 2 browser versions', $this->messages);
		} finally {
			$this->remove($target);
		}
	}

	public function testBuildPreservesBrowsersWhoseSourceCouldNotBeFetched() : void {
		$target = $this->getTarget();
		try {
			\mkdir(\dirname($target), 0755);
			\file_put_contents($target, (string) \json_encode(['firefox' => ['144.0' => 20251014]]));

			// only chrome is stubbed, so every other source behaves as though it could not be reached
			$browsers = $this->getBrowsers()->stub('chrome', ['142.0.7444.60' => 20251028]);
			$this->assertTrue($browsers->build($target));
			$data = $this->getOutput($target);
			$this->assertSame(['144.0' => 20251014], $data['firefox'], 'Existing versions should survive a source that could not be fetched');
			$this->assertArrayHasKey('chrome', $data);
			$this->assertContains('Warning: Could not generate versions for Firefox', $this->messages);
		} finally {
			$this->remove($target);
		}
	}

	public function testBuildReplacesExistingVersionsOnRebuild() : void {
		$target = $this->getTarget();
		try {
			\mkdir(\dirname($target), 0755);
			\file_put_contents($target, (string) \json_encode(['chrome' => ['1.0.154.36' => 20081211]]));
			$browsers = $this->getBrowsers()->stub('chrome', ['142.0.7444.60' => 20251028]);
			$this->assertTrue($browsers->build($target, true));
			$data = $this->getOutput($target);
			$this->assertSame(['142.0.7444.60' => 20251028], $data['chrome'], 'A rebuild should replace the existing versions rather than merge into them');
			$this->assertContains('Saved 1 browser versions', $this->messages);
		} finally {
			$this->remove($target);
		}
	}

	public function testBuildRebuildsFromScratchWhenTheExistingFileIsNotValidJson() : void {
		$target = $this->getTarget();
		try {
			\mkdir(\dirname($target), 0755);
			\file_put_contents($target, 'Not JSON');
			$browsers = $this->getBrowsers()->stub('chrome', ['142.0.7444.60' => 20251028]);
			$this->assertSame(['Data is not valid JSON'], $this->collectWarnings(function () use ($browsers, $target) : void {
				$this->assertTrue($browsers->build($target));
			}));
			$this->assertSame(['chrome' => ['142.0.7444.60' => 20251028]], $this->getOutput($target));
		} finally {
			$this->remove($target);
		}
	}

	public function testBuildFailsWhenNoVersionsCouldBeGenerated() : void {
		$target = $this->getTarget();
		try {

			// nothing is stubbed, so every source behaves as though it could not be reached
			$browsers = $this->getBrowsers();
			$this->assertSame(['No browser versions could be generated'], $this->collectWarnings(function () use ($browsers, $target) : void {
				$this->assertFalse($browsers->build($target));
			}));
			$this->assertFileDoesNotExist($target);
		} finally {
			$this->remove($target);
		}
	}

	/**
	 * Captures the warnings triggered by the given callback, so they don't surface as test warnings
	 *
	 * @param callable $callback The callback to capture the triggered warnings of
	 * @return array<int,string> The messages of any warnings triggered by the callback, in trigger order
	 */
	protected function collectWarnings(callable $callback) : array {
		$warnings = [];
		\set_error_handler(function (int $errno, string $error) use (&$warnings) : bool {
			$warnings[] = $error;
			return true;
		}, E_USER_WARNING);
		try {
			$callback();
		} finally {
			\restore_error_handler();
		}
		return $warnings;
	}
}
