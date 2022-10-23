<?php

namespace YahnisElsts\MiniWpHookParser;

use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\DocBlockFactoryInterface;
use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\NodeVisitorAbstract;
use PhpParser\PrettyPrinter\Standard;
use PhpParser\PrettyPrinterAbstract;
use PhpParser\Node\Stmt;

class HookExtractionVisitor extends NodeVisitorAbstract {
	const HOOK_FUNCTIONS = [
		'apply_filters'            => 'filter',
		'apply_filters_ref_array'  => 'filter',
		'apply_filters_deprecated' => 'filter',
		'do_action'                => 'action',
		'do_action_ref_array'      => 'action',
		'do_action_deprecated'     => 'action',
	];

	private bool $ignoreReusedHooks = true;

	private ?Doc $lastUnusedDoc = null;
	private PrettyPrinterAbstract $printer;
	private DocBlockFactoryInterface $docBlockFactory;

	private string $currentFilePath = '';
	private array $hooks = [];

	public function __construct(
		?PrettyPrinterAbstract    $printer = null,
		?DocBlockFactoryInterface $docBlockFactory = null
	) {
		$this->printer = $printer ?? new Standard();
		$this->docBlockFactory = $docBlockFactory ?? DocBlockFactory::createInstance();
	}

	public function setCurrentFilePath( string $filePath ) {
		$this->currentFilePath = $filePath;
	}

	public function beforeTraverse(array $nodes) {
		$this->hooks = [];
		return parent::beforeTraverse($nodes);
	}

	public function enterNode(Node $node) {
		//Like in WP Parser, let's save the last doc comment that was associated
		//with a node that usually isn't documented (e.g. a variable assignment
		//or a "return" statement). It might actually belong to the next hook.
		if ( ($docBlock = $node->getDocComment()) !== null ) {
			if ( !$this->canBeDocumented($node) && !$this->isHookCall($node) ) {
				$this->lastUnusedDoc = $docBlock;
			} else {
				//We've reached another correctly documented node, so the previous
				//doc comment is no longer relevant.
				$this->lastUnusedDoc = null;
			}
		}
		return parent::enterNode($node);
	}

	public function leaveNode(Node $node) {
		if ( $this->isHookCall($node) ) {
			assert($node instanceof FuncCall); //For IDE type hinting.

			$type = self::HOOK_FUNCTIONS[$node->name->toString()];
			$doc = $node->getDocComment();
			if ( $doc === null ) {
				if ( $this->lastUnusedDoc !== null ) {
					$doc = $this->lastUnusedDoc;
					$this->lastUnusedDoc = null;
				} else {
					//Ignore undocumented hooks.
					return parent::leaveNode($node);
				}
			}

			$parsedDoc = $this->docBlockFactory->create($doc->getText());

			//Skip hooks that are actually documented somewhere else.
			if (
				$this->ignoreReusedHooks
				&& preg_match('/This (?:action|filter) is documented in/', $parsedDoc->getSummary())
			) {
				return parent::leaveNode($node);
			}

			$hook = new HookDescriptor(
				$this->getHookName($node),
				$type,
				$parsedDoc,
				count($node->args) - 1,
				new CodeLocation(
					$this->currentFilePath,
					$node->getStartLine(),
					$node->getEndLine()
				)
			);
			$this->hooks[] = $hook;
		}

		return parent::leaveNode($node);
	}

	/**
	 * Is the given node something that can be documented by phpDocumentor?
	 *
	 * The latest version of phpDocumentor no longer has a method like this,
	 * and the list of supported nodes is technically not fixed. So this is
	 * a partial solution based on old phpDocumentor code and the predefined
	 * strategies registered in ProjectFactory::createInstance().
	 *
	 * @param \PhpParser\Node $node
	 * @return bool
	 */
	private function canBeDocumented(Node $node): bool {
		return ($node instanceof Stmt\Class_)
			|| ($node instanceof Stmt\Interface_)
			|| ($node instanceof Stmt\ClassConst)
			|| ($node instanceof Stmt\ClassMethod)
			|| ($node instanceof Stmt\Const_)
			|| ($node instanceof Stmt\Function_)
			|| ($node instanceof Stmt\Property)
			|| ($node instanceof Stmt\PropertyProperty)
			|| ($node instanceof Stmt\Trait_)
			|| ($node instanceof Stmt\Enum_)
			|| ($node instanceof Stmt\EnumCase)
			|| ($node instanceof Stmt\Expression
				&& $node->expr instanceof Node\Expr\Include_)
			|| ($node instanceof FuncCall
				&& ($node->name instanceof Name)
				&& $node->name == 'define');
	}

	private function isHookCall(Node $node): bool {
		return $node instanceof FuncCall
			&& $node->name instanceof Name
			&& array_key_exists($node->name->toString(), self::HOOK_FUNCTIONS);
	}

	/**
	 * Extract the name of the hook from a function call.
	 *
	 * To ensure consistency, this is pretty much a copy of the name parsing
	 * code in WP Parser.
	 *
	 * @param \PhpParser\Node\Expr\FuncCall $call
	 * @return string
	 */
	private function getHookName(FuncCall $call): string {
		if ( !isset($call->args[0]) ) {
			return '';
		}
		$name = $this->printer->prettyPrintExpr($call->args[0]->value);

		//Simple quoted string.
		if ( preg_match('/^([\'"])([^\'"]*)\1$/', $name, $matches) ) {
			return $matches[2];
		}

		//Two or three concatenated strings, one of which is a variable.
		if ( preg_match(
			'/(?:[\'"]([^\'"]*)[\'"]\s*\.\s*)?'
			. '(\$\S+)'
			. '(?:\s*\.\s*[\'"]([^\'"]*)[\'"])?/',
			$name,
			$matches
		) ) {
			if ( isset($matches[3]) ) {
				return $matches[1] . '{' . $matches[2] . '}' . $matches[3];
			} else {
				return $matches[1] . '{' . $matches[2] . '}';
			}
		}

		return $name;
	}

	/**
	 * @return array<HookDescriptor>
	 */
	public function getHooks(): array {
		return $this->hooks;
	}

	/**
	 * @param bool $ignoreReusedHooks
	 * @return $this
	 */
	public function setIgnoreReusedHooks(bool $ignoreReusedHooks): static {
		$this->ignoreReusedHooks = $ignoreReusedHooks;
		return $this;
	}
}