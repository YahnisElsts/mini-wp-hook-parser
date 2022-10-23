<?php

namespace YahnisElsts\MiniWpHookParser;

use phpDocumentor\Reflection\DocBlock\Tag;
use phpDocumentor\Reflection\DocBlock\Tags\BaseTag;
use phpDocumentor\Reflection\DocBlock\Tags\InvalidTag;
use phpDocumentor\Reflection\DocBlock\Tags\Link;
use phpDocumentor\Reflection\DocBlock\Tags\Param;
use phpDocumentor\Reflection\DocBlock\Tags\TagWithType;
use phpDocumentor\Reflection\Types\AggregatedType;

class Exporter {
	private FormatterInterface $formatter;
	private ?string $normalizedRootDirectory = null;

	public function __construct(FormatterInterface $formatter, ?string $rootDirectory = null) {
		$this->formatter = $formatter;
		if ( $rootDirectory !== null ) {
			$this->normalizedRootDirectory = Parser::normalizePathSlashes($rootDirectory);
			$this->normalizedRootDirectory = rtrim($this->normalizedRootDirectory, '/');
		}
	}

	/**
	 * Export hook details to an associative array.
	 *
	 * @param \YahnisElsts\MiniWpHookParser\HookDescriptor $hook
	 * @return array
	 */
	public function exportHook(HookDescriptor $hook): array {
		$location = $hook->getLocation();

		//Use relative paths if possible.
		if ( $this->normalizedRootDirectory !== null ) {
			$path = Parser::normalizePathSlashes($location->getPath());
			if ( str_starts_with($path, $this->normalizedRootDirectory) ) {
				$path = substr($path, strlen($this->normalizedRootDirectory));
			}
		} else {
			$path = $location->getPath();
		}

		$result = [
			'name'    => $hook->getName(),
			'type'    => $hook->getType(),
			'numArgs' => $hook->getNumArgs(),
			'path'    => $path,
			'line'    => $location->getLine(),
		];
		//To save space, don't include the end line if it's the same as the start line.
		if ( $location->getEndLine() !== $location->getLine() ) {
			$result['endLine'] = $location->getEndLine();
		}

		$doc = $hook->getDocBlock();
		$exportedDoc = [
			'summary' => $this->formatter->formatSummary($doc->getSummary()),
		];
		$formattedDescription = $this->formatter->formatLongDescription($doc->getDescription());
		if ( !empty($formattedDescription) ) {
			$exportedDoc['description'] = $formattedDescription;
		}
		$formattedTags = array_map([$this, 'exportTag'], $doc->getTags());
		if ( !empty($formattedTags) ) {
			$exportedDoc['tags'] = $formattedTags;
		}

		$result['doc'] = $exportedDoc;

		return $result;
	}

	protected function exportTag(Tag $tag): array {
		$formattedDescription = '';

		$output = [
			'name'    => $tag->getName(),
			'content' => '',
		];

		if ( $tag instanceof BaseTag ) {
			$description = (string)$tag->getDescription();
			if ( $description ) {
				$formattedDescription = $this->formatter->formatTagDescription($description);
				$output['content'] = $formattedDescription;
			}
		}

		if ( $tag instanceof TagWithType ) {
			$type = $tag->getType();
			if ( $type instanceof AggregatedType ) {
				$exportedTypes = [];
				foreach ($type->getIterator() as $subType) {
					$exportedTypes[] = (string)($subType);
				}
			} else {
				$exportedTypes = [(string)$tag->getType()];
			}
			$output['types'] = $exportedTypes;
		}

		if ( $tag instanceof Link ) {
			$output['link'] = $tag->getLink();
		}

		if ( $tag instanceof Param ) {
			$output['variable'] = $tag->getVariableName();
		}

		if ( method_exists($tag, 'getReference') ) {
			$ref = $tag->getReference();
			if ( $ref ) {
				$output['reference'] = (string)$ref;
			}
		}

		if ( method_exists($tag, 'getVersion') ) {
			$output['content'] = $tag->getVersion();
			if ( !empty($formattedDescription) ) {
				$output['description'] = $formattedDescription;
			}
		}

		if ( $tag instanceof InvalidTag ) {
			//This happens with incorrectly formatted tags, like "@since MU (3.0.0)".
			if ( empty($output['content']) ) {
				$output['content'] = $tag->__toString();
			}
		}

		return $output;
	}
}