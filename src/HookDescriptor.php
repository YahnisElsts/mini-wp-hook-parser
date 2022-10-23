<?php

namespace YahnisElsts\MiniWpHookParser;

use phpDocumentor\Reflection\DocBlock;

class HookDescriptor {
	protected string $name;
	protected string $type;
	protected DocBlock $docBlock;
	protected int $numArgs;
	protected CodeLocation $location;

	public function __construct(
		string $name,
		string $type,
		DocBlock $docBlock,
		int $numArgs,
		CodeLocation $location
	) {
		$this->name = $name;
		$this->type = $type;
		$this->docBlock = $docBlock;
		$this->numArgs = $numArgs;
		$this->location = $location;
	}

	/**
	 * @return string
	 */
	public function getName(): string {
		return $this->name;
	}

	/**
	 * @return string
	 */
	public function getType(): string {
		return $this->type;
	}

	/**
	 * @return \phpDocumentor\Reflection\DocBlock
	 */
	public function getDocBlock(): DocBlock {
		return $this->docBlock;
	}

	/**
	 * @return int
	 */
	public function getNumArgs(): int {
		return $this->numArgs;
	}

	/**
	 * @return \YahnisElsts\MiniWpHookParser\CodeLocation
	 */
	public function getLocation(): CodeLocation {
		return $this->location;
	}
}