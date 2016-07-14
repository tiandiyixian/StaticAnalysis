<?php
namespace Scan\Checks;

use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\ClassMethod;
use Scan\Scope;
use Scan\Util;

class InterfaceCheck extends BaseCheck {
	function getCheckNodeTypes() {
		return [\PhpParser\Node\Stmt\Class_::class];
	}

	protected function checkMethod(Class_ $class, \Scan\Abstractions\ClassMethod $method, \Scan\Abstractions\ClassInterface $parentClass, \Scan\Abstractions\FunctionLikeInterface $parentMethod) {

		$visibility = $method->getAccessLevel();
		$oldVisibility = $parentMethod->getAccessLevel();

		$fileName = $this->symbolTable->getClassFile( strval($class->namespacedName) );
		$this->incTests();

		// "public" and "protected" can be redefined," private can not.
		if (
			$oldVisibility != $visibility && $oldVisibility == "private"
		) {
			$this->emitError($fileName, $class,"Signature mismatch", "Access level mismatch in ".$method->getName()."() ".$visibility." vs ".$oldVisibility);
		}

		$params = $method->getParameters();
		$parentMethodParams = $parentMethod->getParameters();
		$count1 = count($params);
		$count2 = count($parentMethodParams);
		if ($count1 != $count2) {
			$this->emitError($fileName,$class,"Signature mismatch", "Parameter count mismatch $count1 vs $count2 in method ".$class->namespacedName."->".$method->getName());
		} else foreach ($params as $index => $param) {
			$parentParam = $parentMethodParams[$index];
			$name1 = strval($param->getType());
			$name2 = strval($parentParam->getType());
			if (
				strcasecmp($name1, $name2) !== 0
			) {
				$this->emitError($fileName,$class,"Signature mismatch", "Parameter mismatch type mismatch $name1 vs $name2");
				break;
			}
		}
	}

	protected function implementsMethod( $fileName, Class_ $node, $interfaceMethod) {
		$current = new \Scan\Abstractions\Class_($node);
		while (true) {
			// Is it directly in the class
			$classMethod = $current->getMethod($interfaceMethod);
			if ($classMethod) {
				return $classMethod;
			}

			if ($current->getParentClassName()) {
				$current = $this->symbolTable->getAbstractedClass($current->getParentClassName());
			} else {
				return null;
			}
		}
	}

	/**
	 * @param $fileName
	 * @param \PhpParser\Node\Stmt\Class_ $node
	 */
	function run($fileName, $node, ClassLike $inside=null, Scope $scope=null) {

		if ($node->implements) {
			$arr = is_array($node->implements) ? $node->implements : [$node->implements];
			foreach ($arr as $interface) {
				$name = $interface->toString();
				$this->incTests();
				if ($name) {
					$interface = $this->symbolTable->getAbstractedClass($name);
					if (!$interface) {
						$this->emitError($fileName,$node,"Unknown interface",  $node->name . " implements unknown interface " . $name);
					} else {
						// Don't force abstract classes to implement all methods.
						if(!$node->isAbstract()) {
							foreach ($interface->getMethodNames() as $interfaceMethod) {
								$classMethod = $this->implementsMethod($fileName, $node, $interfaceMethod);
								if (!$classMethod) {
									if(!$node->isAbstract()) {
										$this->emitError($fileName,$node,"Missing implementation", $node->name . " does not implement method " . $interfaceMethod);
									}
								} else {
									$this->checkMethod(
										$node,$classMethod, $interface, $interface->getMethod($interfaceMethod)
									);
								}
							}
						}
					}
				}
			}
		}
	}
}
