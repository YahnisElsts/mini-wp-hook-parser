<?php

namespace YahnisElsts\MiniWpHookParser;

use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;

class Parser {

	/**
	 * Recursively find all PHP files in the specified directory.
	 *
	 * @param string $directory
	 * @param array<string> $ignorePaths Skip files and directories where the path
	 *                                   contains any of these strings.
	 * @return \Generator<string>
	 */
	public static function findSourceFiles(string $directory, array $ignorePaths = []): \Generator {
		$ignorePaths = array_map((function ($path) {
			return static::normalizePathSlashes($path);
		}), $ignorePaths);

		$fileIterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($directory)
		);

		foreach ($fileIterator as $file) {
			/** @var \SplFileInfo $file */
			if ( $file->isFile() && $file->getExtension() === 'php' ) {
				$filePath = static::normalizePathSlashes($file->getPathname());

				$ignore = false;
				foreach ($ignorePaths as $ignorePath) {
					if ( str_contains($filePath, $ignorePath) ) {
						$ignore = true;
						break;
					}
				}

				if ( !$ignore ) {
					yield $filePath;
				}
			}
		}
	}

	public static function normalizePathSlashes(string $path): string {
		return str_replace('\\', '/', $path);
	}

	/**
	 * @param iterable<string> $files
	 * @return \Generator<\YahnisElsts\MiniWpHookParser\HookDescriptor>
	 */
	public static function parseFiles(iterable $files): \Generator {
		$parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);

		$hookExtractor = new HookExtractionVisitor();
		$traverser = new NodeTraverser;
		$traverser->addVisitor($hookExtractor);

		foreach ($files as $filePath) {
			$filePath = static::normalizePathSlashes($filePath);
			$sourceCode = file_get_contents($filePath);
			if ( $sourceCode === false ) {
				continue; //Skip inaccessible files.
			}

			try {
				$ast = $parser->parse($sourceCode);
			} catch (Error) {
				continue; //Skip files that cannot be parsed.
			}

			$hookExtractor->setCurrentFilePath($filePath);
			$traverser->traverse($ast);

			foreach ($hookExtractor->getHooks() as $hook) {
				yield $hook;
			}
		}
	}

	/**
	 * @param iterable<\YahnisElsts\MiniWpHookParser\HookDescriptor> $hooks
	 * @param string|null $rootDirectory
	 * @param \YahnisElsts\MiniWpHookParser\FormatterInterface|null $formatter
	 * @return array
	 */
	public static function exportHooks(
		iterable            $hooks,
		?string             $rootDirectory = null,
		?FormatterInterface $formatter = null
	): array {
		$exporter = new Exporter(
			$formatter ?? new DefaultFormatter(),
			$rootDirectory
		);

		$output = [];
		foreach ($hooks as $hook) {
			$output[] = $exporter->exportHook($hook);
		}
		return $output;
	}

	/**
	 * Extract action and filter documentation from a WordPress installation
	 * and write the results to a JSON file.
	 *
	 * @param string $inputDirectory WordPress root directory.
	 * @param string $outputFile Path to the output file.
	 * @param array $ignorePaths Skip files and directories where the path contains any of these strings.
	 * @return void
	 */
	public static function extractAndWriteToFile(
		string $inputDirectory,
		string $outputFile,
		array  $ignorePaths = ['/wp-content/'],
	): void {
		$files = static::findSourceFiles($inputDirectory, $ignorePaths);
		$hooks = static::parseFiles($files);
		$exported = static::exportHooks($hooks, $inputDirectory);

		$json = [
			'generatedOn' => gmdate('c'),
			'hooks'       => $exported,
		];
		file_put_contents($outputFile, json_encode($json, JSON_PRETTY_PRINT));
	}
}