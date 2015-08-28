<?php

/*
  +--------------------------------------------------------------------------+
  | Zephir Language                                                          |
  +--------------------------------------------------------------------------+
  | Copyright (c) 2013-2015 Zephir Team and contributors                     |
  +--------------------------------------------------------------------------+
  | This source file is subject the MIT license, that is bundled with        |
  | this package in the file LICENSE, and is available through the           |
  | world-wide-web at the following url:                                     |
  | http://zephir-lang.com/license.html                                      |
  |                                                                          |
  | If you did not receive a copy of the MIT license and are unable          |
  | to obtain it through the world-wide-web, please send a note to           |
  | license@zephir-lang.com so we can mail you a copy immediately.           |
  +--------------------------------------------------------------------------+
 */

namespace Zephir;

use Zephir\CompilerException;
use Zephir\CompilationContext;

/**
 * FunctionCall
 *
 * Call functions. By default functions are called in the PHP userland if an optimizer
 * was not found or there is not a user-handler for it
 */
abstract class FunctionCallAbstract extends CallAbstract {

  /**
   * Function is called using a normal method name
   */
  const CALL_NORMAL = 1;

  /**
   * Function is called using a dynamic variable as method name
   */
  const CALL_DYNAMIC = 2;

  /**
   * Function is called using a dynamic string as method name
   */
  const CALL_DYNAMIC_STRING = 3;

  protected static $_optimizers = array();
  protected static $_functionReflection = array();
  protected static $_optimizerDirectories = array();
  private static $_functionCache = null;

  /**
   * Process the ReflectionFunction for the specified function name
   *
   * @param string $funcName
   * @return \ReflectionFunction
   */
  public function getReflector($funcName) {
    /**
     * Check if the optimizer is already cached
     */
    if (!isset(self::$_functionReflection[$funcName])) {
      try {
        $reflectionFunction = new \ReflectionFunction($funcName);
      } catch (\Exception $e) {
        $reflectionFunction = null;
      }
      self::$_functionReflection[$funcName] = $reflectionFunction;
      $this->_reflection = $reflectionFunction;
      return $reflectionFunction;
    }
    $reflectionFunction = self::$_functionReflection[$funcName];
    $this->_reflection = $reflectionFunction;
    return $reflectionFunction;
  }

  /**
   * This method gets the reflection of a function
   * to check if any of their parameters are passed by reference
   * Built-in functions rarely change the parameters if they aren't passed by reference
   *
   * @param string $funcName
   * @param array $expression
   * @return boolean
   */
  protected function isReadOnly($funcName, array $expression) {
    if ($this->isBuiltInFunction($funcName)) {
      return false;
    }

    /**
     * These functions are supposed to be read-only but they change parameters ref-count
     */
    switch ($funcName) {
      case 'min':
      case 'max':
      case 'array_fill':
      case 'array_pad':
      case 'call_user_func':
      case 'call_user_func_array':
        return false;
    }

    $reflector = $this->getReflector($funcName);
    if ($reflector) {
      if (isset($expression['parameters'])) {
        /**
         * Check if the number of parameters
         */
        $numberParameters = count($expression['parameters']);
        switch ($funcName) {
          case 'strtok' : // Refleection Returns 2 Required Parameters even though Documentation says 1 is enough
            $requiredNoP = $numberParameters < 2 ? 1 : 2;
            break;
          default:
            $requiredNoP = $reflector->getNumberOfRequiredParameters();
        }
        if ($numberParameters < $requiredNoP) {
          throw new CompilerException("The number of parameters passed is lesser than the number of required parameters by '" . $funcName . "'", $expression);
        }
      } else {
        $numberParameters = 0;
        if ($reflector->getNumberOfRequiredParameters() > 0) {
          throw new CompilerException("The number of parameters passed is lesser than the number of required parameters by '" . $funcName . "'", $expression);
        }
      }

      if ($reflector->getNumberOfParameters() > 0) {
        foreach ($reflector->getParameters() as $parameter) {
          if ($parameter->isPassedByReference()) {
            return false;
          }
        }
      }
      return true;
    }

    return false;
  }

  /**
   * Once the function processes the parameters we should mark
   * specific parameters to be passed by reference
   *
   * @param string $funcName
   * @param array $parameters
   * @param CompilationContext $compilationContext
   * @param array $references
   * @param array $expression
   * @return boolean
   */
  protected function markReferences($funcName, $parameters, CompilationContext $compilationContext, &$references, $expression) {
    if ($this->isBuiltInFunction($funcName)) {
      return false;
    }

    $reflector = $this->getReflector($funcName);
    if ($reflector) {
      $numberParameters = count($parameters);
      if ($numberParameters > 0) {
        $n = 1;
        $funcParameters = $reflector->getParameters();
        foreach ($funcParameters as $parameter) {
          if ($numberParameters >= $n) {
            if ($parameter->isPassedByReference()) {
              if (!preg_match('/^[a-zA-Z0-9\_]+$/', $parameters[$n - 1])) {
                $compilationContext->logger->warning("Cannot mark complex expression as reference", "invalid-reference", $expression);
                continue;
              }

              $variable = $compilationContext->symbolTable->getVariable($parameters[$n - 1]);
              if ($variable) {
                $variable->setDynamicTypes('undefined');
                $compilationContext->codePrinter->output('Z_SET_ISREF_P(' . $parameters[$n - 1] . ');');
                $references[] = $parameters[$n - 1];
                return false;
              }
            }
          }
          $n++;
        }
      }
    }
  }

  /**
   * Checks if the function is a built-in provided by Zephir
   *
   * @param string $functionName
   */
  public function isBuiltInFunction($functionName) {
    switch ($functionName) {
      case 'memstr':
      case 'get_class_ns':
      case 'get_ns_class':
      case 'camelize':
      case 'uncamelize':
      case 'starts_with':
      case 'ends_with':
      case 'prepare_virtual_path':
      case 'create_instance':
      case 'create_instance_params':
      case 'create_symbol_table':
      case 'globals_get':
      case 'globals_set':
      case 'merge_append':
      case 'get_class_lower':
        return true;
    }
    return false;
  }

  /**
   * Checks if a function exists or is a built-in Zephir function
   *
   * @param string $functionName
   * @return boolean
   */
  public function functionExists($functionName, CompilationContext $context) {
    if (function_exists($functionName)) {
      return true;
    }
    if ($this->isBuiltInFunction($functionName)) {
      return true;
    }

    $internalName = array('f__' . $functionName);
    if (isset($context->classDefinition)) {
      $internalName[] = 'f_' . str_replace('\\', '_', strtolower($context->classDefinition->getNamespace())) . '_' . $functionName;
    }
    foreach ($internalName as $name) {
      if (isset($context->compiler->functionDefinitions[$name])) {
        return true;
      }
    }
    return false;
  }

  /**
   * Compiles a function
   *
   * @param Expression $expr
   * @param CompilationContext $compilationContext
   * @return CompiledExpression
   * @throws CompilerException
   */
  abstract public function compile(ExpressionAbstract $expr, CompilationContext $compilationContext);

  /**
   * Appends an optimizer directory to the directory list
   *
   * @param string $directory
   */
  public static function addOptimizerDir($directory) {
    self::$_optimizerDirectories[] = $directory;
  }

}
