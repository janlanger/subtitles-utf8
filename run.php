<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/helpers.php';

\Tracy\Debugger::enable(\Tracy\Debugger::PRODUCTION, __DIR__ . '/log');

class Controller
{

	public function run($directory)
	{
		$directory = realpath($directory);
		if (!$directory) {
			return;
		}
		$finder = \Nette\Utils\Finder::findFiles('*.srt', '*.sub')
			->exclude('*.en.srt')
			->exclude('sample*/*')
			->exclude('.srt.backup*/*')
			->size('<', 1 * 1024 * 1024);

		$indexFile = $directory . '/.srtindex';
		// index file to exclude already processed files
		$index = file_exists($indexFile) ? file($indexFile) : [];
		array_walk($index, function (&$item) {
			$item = trim($item, "\n\t\r \\/");
			$item = str_replace("/", DIRECTORY_SEPARATOR, $item);
		});
		$counter = 0;

		$finder->filter(function ($file) use (&$index, $directory, &$counter) {
			/** @var SplFileInfo $file */
			//filter entries in index file
			if (!$file->isFile()) {
				return true;
			}
			$filepath = str_replace($directory, '', $file->getRealPath());
			$filepath = trim($filepath, "\n\t\r \\/");

			if (!in_array($filepath, $index)) {
				$index[] = $filepath;
				$counter++;

				return true;
			}

			return false;
		});

		foreach ($finder->from($directory) as $file) {
			/** @var SplFileInfo $file */
			$this->processFile($file->getRealPath());
		}

		$values = array_values($index);
		array_walk($values, function (&$item) {
			$item = str_replace(DIRECTORY_SEPARATOR, '/', $item); //normalization
		});
		file_put_contents($indexFile, implode("\n", $values));

		echo "\n\nProcessed $counter files.\n\n";
	}

	private function processFile($path)
	{
		$file = file_get_contents($path);

		$encoding = Helpers::detect($file);

		switch ($encoding) {
			case 'WINDOWS-1250':
				echo 'WIN-1250 - ' . substr($path, -100) . "\n";
				$this->transcode($file, $encoding, $path);
				break;
			case 'ISO-8859-2':
				echo 'ISO-8859 - ' . substr($path, -100) . "\n";
				$this->transcode($file, $encoding, $path);
				break;
			case 'UTF-8':
				echo 'UTF-8    - ' . substr($path, -100) . "\n";
				break;
			default:
				echo 'UNKNOWN  - ' . substr($path, -100) . "\n";
		}
	}

	private function transcode($s, $encoding, $path)
	{
		$newS = iconv($encoding, 'UTF-8', $s);
		file_put_contents($path, $newS);
	}

}

$args = Helpers::parseArguments();

if (empty($args)) {
	die('Please provide directory to run in.');
}
(new Controller())->run(array_shift($args));
