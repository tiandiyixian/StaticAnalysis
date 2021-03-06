<?php namespace Guardrail\NodeVisitors;

use PhpParser\Node;
use PhpParser\NodeTraverserInterface;
use PhpParser\NodeVisitor;
use PhpParser\NodeTraverser;

class VariadicCheckVisitor implements NodeVisitor
{
	private $foundVariatic = false;

	/**
	 * @return bool
	 */
	function getIsVariadic() {
		return $this->foundVariatic;
	}

	function beforeTraverse(array $nodes) {
		return null;
	}

	function enterNode(Node $node) {
		if($node instanceof Node\FunctionLike) {
			return NodeTraverserInterface::DONT_TRAVERSE_CHILDREN;
		}

		if (
			$node instanceof Node\Expr\FuncCall &&
			$node->name instanceof Node\Name &&
			(
				strcasecmp(strval($node->name), "func_get_args") == 0 ||
				strcasecmp(strval($node->name), "func_num_args") == 0 ||
				strcasecmp(strval($node->name), "func_get_arg") == 0
			)
		) {
			$this->foundVariatic = true;
		}
	}

	function leaveNode(Node $node) {
		return null;
	}

	function afterTraverse(array $nodes) {
		return null;
	}

	static function isVariadic($stmts) {
		if(!is_array($stmts)) {
			return false;
		}
		$visitor = new self;
		$traverser = new NodeTraverser();
		$traverser->addVisitor($visitor);
		$traverser->traverse($stmts);
		return $visitor->getIsVariadic();
	}
}

?>
