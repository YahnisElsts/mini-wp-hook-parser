<?php

namespace YahnisElsts\MiniWpHookParser;
require __DIR__ . '/../vendor/autoload.php';

$options = getopt('', [
	'input:',
	'output:',
]);

if ( empty($options['input']) || empty($options['output']) ) {
	printf("Usage: php %s --input=<wordpress-path> --output=<json-file-path>\n", $argv[0]);
	exit(1);
}

$inputDirectory = $options['input'];
if ( !is_dir($inputDirectory) ) {
	printf("Input directory not found: %s\n", $inputDirectory);
	exit(2);
}

$outputFile = $options['output'];
if ( file_exists($outputFile) && !is_writable($outputFile) ) {
	printf("Output file is not writable: %s\n", $outputFile);
	exit(3);
}

$outputDir = dirname($outputFile);
if ( !is_dir($outputDir) ) {
	printf("Output directory not found: %s\n", $outputDir);
	exit(4);
}

printf(
	"Extracting hooks from \"%s\". This may take a few minutes...\n",
	$inputDirectory
);

$files = Parser::findSourceFiles($inputDirectory, ['/wp-content/']);
$hooks = Parser::parseFiles($files);
$exported = Parser::exportHooks($hooks, $inputDirectory);

$json = [
	'generatedOn' => gmdate('c'),
	'hooks'       => $exported,
];
$writtenBytes = file_put_contents(
	$outputFile,
	json_encode($json, JSON_PRETTY_PRINT)
);

if ( $writtenBytes === false ) {
	printf("Failed to write to output file: %s\n", $outputFile);
	exit(5);
}

printf("Done. %.0f KiB written to %s.\n", $writtenBytes / 1024, $outputFile);