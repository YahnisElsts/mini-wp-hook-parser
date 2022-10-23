<?php

namespace YahnisElsts\MiniWpHookParser;

interface FormatterInterface {
	public function formatSummary(string $summary): string;

	public function formatLongDescription(string $description): string;

	public function formatTagDescription(string $description): string;
}