<?php

namespace YahnisElsts\MiniWpHookParser;

class CodeLocation {
	private string $path;
	private int $line;
	private int $endLine;

	public function __construct(string $path, int $line, int $endLine) {
		$this->path = $path;
		$this->line = $line;
		$this->endLine = $endLine;
	}

	public function getPath(): string {
		return $this->path;
	}

	public function getLine(): int {
		return $this->line;
	}

	public function getEndLine(): int {
		return $this->endLine;
	}
}