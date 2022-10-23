<?php

namespace YahnisElsts\MiniWpHookParser;

use Parsedown;

class DefaultFormatter implements FormatterInterface {
	private Parsedown $parsedown;

	public function __construct() {
		$this->parsedown = new Parsedown();
	}

	public function formatSummary(string $summary): string {
		return trim(preg_replace('/[\n\r]+/', ' ', $summary));
	}

	public function formatLongDescription(string $description): string {
		$result = $description;

		//If there's a plain <code> element, surround it with a <pre> element.
		if ( str_contains($result, '<code>') ) {
			$result = preg_replace(
				'/<code>(.*?)<\/code>/s',
				'<pre><code>$1</code></pre>',
				$result
			);
		}

		//Convert Markdown to HTML.
		$result = $this->parsedown->text($result);

		//Unwrap manually wrapped text.
		$result = $this->fixNewlines($result);

		return trim($result);
	}

	public function formatTagDescription(string $description): string {
		$description = $this->parsedown->line($description);
		$description = $this->fixNewlines($description);
		return trim($description);
	}

	/**
	 * Fixes newline handling in parsed text.
	 *
	 * DocBlock descriptions are generally manually wrapped to a certain width
	 * for readability. Let's unwrap them.
	 *
	 * This function fixes text by merging consecutive lines of text into a single
	 * line. A special exception is made for text appearing in `<code>` and `<pre>`
	 * tags, as those newlines are usually intentional.
	 */
	protected function fixNewlines(string $html): string {
		//Non-naturally occurring string to use as temporary replacement.
		$placeholder = '{{{{{}}}}}';

		//Replace newline characters within <code> and <pre> tags with a placeholder.
		$html = preg_replace_callback(
			"/(?<=<pre><code>)(.+)(?=<\/code><\/pre>)/s",
			function ($matches) use ($placeholder) {
				return preg_replace('/[\n\r]/', $placeholder, $matches[1]);
			},
			$html
		);

		//Insert a newline when \n follows '.'.
		$html = preg_replace(
			"/\.[\n\r]+(?!\s*[\n\r])/m",
			'.<br>',
			$html
		);

		//Insert a new line when \n is followed by what appears to be a list.
		$html = preg_replace(
			"/[\n\r]+(\s+[*-] )(?!\s*[\n\r])/m",
			'<br>$1',
			$html
		);

		//Merge consecutive non-blank lines together by replacing the newlines
		//with a space.
		$html = preg_replace(
			"/[\n\r](?!\s*[\n\r])/m",
			' ',
			$html
		);

		//Restore newline characters into code blocks.
		return str_replace($placeholder, "\n", $html);
	}
}