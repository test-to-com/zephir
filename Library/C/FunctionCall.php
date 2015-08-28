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

namespace Zephir\C;

use Zephir\Utils;
use Zephir\C\Optimizers\OptimizerAbstract;
use Zephir\CompilerException;
use Zephir\CompilationContext;
use Zephir\ExpressionAbstract;
use Zephir\CompiledExpression;

/**
 * FunctionCall
 *
 * Call functions. By default functions are called in the PHP userland if an optimizer
 * was not found or there is not a user-handler for it
 */
class FunctionCall extends \Zephir\FunctionCallAbstract {

  /**
   * Resolves paramameters
   *
   * @param array $parameters
   * @param CompilationContext $compilationContext
   * @param array $expression
   * @param boolean $readOnly
   * @return array|null|CompiledExpression[]
   */
  public function getResolvedParamsAsExpr($parameters, CompilationContext $compilationContext, $expression, $readOnly = false) {
    if (!$this->_resolvedParams) {
      $hasParametersByName = false;
      foreach ($parameters as $parameter) {
        if (isset($parameter['name'])) {
          $hasParametersByName = true;
          break;
        }
      }

      /**
       * All parameters must be passed by name
       */
      if ($hasParametersByName) {
        foreach ($parameters as $parameter) {
          if (!isset($parameter['name'])) {
            throw new CompilerException('All parameters must use named', $parameter);
          }
        }
      }

      if ($hasParametersByName) {
        if ($this->_reflection) {
          $positionalParameters = array();
          foreach ($this->_reflection->getParameters() as $position => $reflectionParameter) {
            if (is_object($reflectionParameter)) {
              $positionalParameters[$reflectionParameter->getName()] = $position;
            } else {
              $positionalParameters[$reflectionParameter['name']] = $position;
            }
          }
          $orderedParameters = array();
          foreach ($parameters as $parameter) {
            if (isset($positionalParameters[$parameter['name']])) {
              $orderedParameters[$positionalParameters[$parameter['name']]] = $parameter;
            } else {
              throw new CompilerException('Named parameter "' . $parameter['name'] . '" is not a valid parameter name, available: ' . join(', ', array_keys($positionalParameters)), $parameter['parameter']);
            }
          }
          for ($i = 0; $i < count($parameters); $i++) {
            if (!isset($orderedParameters[$i])) {
              $orderedParameters[$i] = array('parameter' => array('type' => 'null'));
            }
          }
          $parameters = $orderedParameters;
        }
      }

      $params = array();
      foreach ($parameters as $parameter) {
        if (is_array($parameter['parameter'])) {
          $paramExpr = new Expression($parameter['parameter']);

          switch ($parameter['parameter']['type']) {
            case 'property-access':
            case 'array-access':
            case 'static-property-access':
              $paramExpr->setReadOnly(true);
              break;

            default:
              $paramExpr->setReadOnly($readOnly);
              break;
          }

          $params[] = $paramExpr->compile($compilationContext);
          continue;
        }

        if ($parameter['parameter'] instanceof CompiledExpression) {
          $params[] = $parameter['parameter'];
          continue;
        }

        throw new CompilerException("Invalid expression ", $expression);
      }
      $this->_resolvedParams = $params;
    }

    return $this->_resolvedParams;
  }

  /**
   * Resolve parameters getting aware that the target function/method could retain or change
   * the parameters
   *
   * @param array $parameters
   * @param CompilationContext $compilationContext
   * @param array $expression
   * @param array $calleeDefinition
   * @return array
   */
  public function getResolvedParams($parameters, CompilationContext $compilationContext, array $expression, $calleeDefinition = null) {
    $codePrinter = $compilationContext->codePrinter;
    $exprParams = $this->getResolvedParamsAsExpr($parameters, $compilationContext, $expression);

    /**
     * Static typed parameters in final/private methods are promotable to read only parameters
     * Recursive calls with static typed methods also also promotable
     */
    $isFinal = false;
    $readOnlyParameters = array();
    if (is_object($calleeDefinition)) {
      if ($calleeDefinition instanceof ClassMethod) {
        if ($calleeDefinition->isFinal() || $calleeDefinition->isPrivate() || $calleeDefinition->isInternal() || $compilationContext->currentMethod == $calleeDefinition) {
          $isFinal = true;
          foreach ($calleeDefinition->getParameters() as $position => $parameter) {
            if (isset($parameter['data-type'])) {
              switch ($parameter['data-type']) {
                case 'int':
                case 'uint':
                case 'double':
                case 'long':
                case 'char':
                case 'uchar':
                case 'boolean':
                case 'bool':
                  $readOnlyParameters[$position] = true;
                  break;
              }
            }
          }
        }
      }
    }

    $params = array();
    $types = array();
    $dynamicTypes = array();
    $mustCheck = array();
    foreach ($exprParams as $position => $compiledExpression) {
      $expression = $compiledExpression->getOriginal();
      switch ($compiledExpression->getType()) {
        case 'null':
          if (isset($readOnlyParameters[$position])) {
            $parameterVariable = $compilationContext->symbolTable->getTempLocalVariableForWrite('variable', $compilationContext, $expression);
            $params[] = '&' . $parameterVariable->getName();
            $codePrinter->output('ZVAL_NULL(&' . $parameterVariable->getName() . ');');
          } else {
            $parameterVariable = $compilationContext->symbolTable->getTempVariableForWrite('variable', $compilationContext, $expression);
            $params[] = $parameterVariable->getName();
            $codePrinter->output('ZVAL_NULL(' . $parameterVariable->getName() . ');');
          }
          $this->_temporalVariables[] = $parameterVariable;
          $types[] = $compiledExpression->getType();
          $dynamicTypes[] = $compiledExpression->getType();
          break;

        case 'int':
        case 'uint':
        case 'long':
          if (isset($readOnlyParameters[$position])) {
            $parameterVariable = $compilationContext->symbolTable->getTempLocalVariableForWrite('variable', $compilationContext, $expression);
            $codePrinter->output('ZVAL_LONG(&' . $parameterVariable->getName() . ', ' . $compiledExpression->getCode() . ');');
            $params[] = '&' . $parameterVariable->getName();
          } else {
            $parameterVariable = $compilationContext->symbolTable->getTempVariableForWrite('variable', $compilationContext, $expression);
            $codePrinter->output('ZVAL_LONG(' . $parameterVariable->getName() . ', ' . $compiledExpression->getCode() . ');');
            $params[] = $parameterVariable->getName();
          }
          $this->_temporalVariables[] = $parameterVariable;
          $types[] = $compiledExpression->getType();
          $dynamicTypes[] = $compiledExpression->getType();
          break;

        case 'double':
          if (isset($readOnlyParameters[$position])) {
            $parameterVariable = $compilationContext->symbolTable->getTempLocalVariableForWrite(
              'variable', $compilationContext, $expression
            );
            $codePrinter->output('ZVAL_DOUBLE(&' . $parameterVariable->getName() . ', ' . $compiledExpression->getCode() . ');');
            $params[] = '&' . $parameterVariable->getName();
          } else {
            $parameterVariable = $compilationContext->symbolTable->getTempVariableForWrite('variable', $compilationContext, $expression);
            $codePrinter->output('ZVAL_DOUBLE(' . $parameterVariable->getName() . ', ' . $compiledExpression->getCode() . ');');
            $params[] = $parameterVariable->getName();
          }
          $this->_temporalVariables[] = $parameterVariable;
          $types[] = $compiledExpression->getType();
          break;

        case 'bool':
          if ($compiledExpression->getCode() == 'true') {
            if (isset($readOnlyParameters[$position])) {
              $parameterVariable = $compilationContext->symbolTable->getTempLocalVariableForWrite('variable', $compilationContext, $expression);
              $codePrinter->output('ZVAL_BOOL(&' . $parameterVariable->getName() . ', 1);');
              $params[] = '&' . $parameterVariable->getName();
            } else {
              $parameterVariable = $compilationContext->symbolTable->getTempVariableForWrite('variable', $compilationContext, $expression);
              $codePrinter->output('ZVAL_BOOL(' . $parameterVariable->getName() . ', 1);');
              $params[] = $parameterVariable->getName();
            }
          } else {
            if ($compiledExpression->getCode() == 'false') {
              if (isset($readOnlyParameters[$position])) {
                $parameterVariable = $compilationContext->symbolTable->getTempLocalVariableForWrite('variable', $compilationContext, $expression);
                $codePrinter->output('ZVAL_BOOL(&' . $parameterVariable->getName() . ', 0);');
                $params[] = '&' . $parameterVariable->getName();
              } else {
                $parameterVariable = $compilationContext->symbolTable->getTempVariableForWrite('variable', $compilationContext, $expression);
                $codePrinter->output('ZVAL_BOOL(' . $parameterVariable->getName() . ', 0);');
                $params[] = $parameterVariable->getName();
              }
            } else {
              if (isset($readOnlyParameters[$position])) {
                $parameterVariable = $compilationContext->symbolTable->getTempLocalVariableForWrite('variable', $compilationContext, $expression);
                $codePrinter->output('ZVAL_BOOL(&' . $parameterVariable->getName() . ', ' . $compiledExpression->getBooleanCode() . ');');
                $params[] = '&' . $parameterVariable->getName();
              } else {
                $parameterVariable = $compilationContext->symbolTable->getTempVariableForWrite('variable', $compilationContext, $expression);
                $codePrinter->output('ZVAL_BOOL(' . $parameterVariable->getName() . ', ' . $compiledExpression->getBooleanCode() . ');');
                $params[] = $parameterVariable->getName();
              }
            }
          }

          $this->_temporalVariables[] = $parameterVariable;
          $types[] = $compiledExpression->getType();
          $dynamicTypes[] = $compiledExpression->getType();
          break;

        case 'ulong':
        case 'string':
        case 'istring':
          $parameterVariable = $compilationContext->symbolTable->getTempVariableForWrite('variable', $compilationContext, $expression);
          $codePrinter->output('ZVAL_STRING(' . $parameterVariable->getName() . ', "' . Utils::addSlashes($compiledExpression->getCode()) . '", ZEPHIR_TEMP_PARAM_COPY);');
          $this->_temporalVariables[] = $parameterVariable;
          $mustCheck[] = $parameterVariable->getName();
          $params[] = $parameterVariable->getName();
          $types[] = $compiledExpression->getType();
          $dynamicTypes[] = $compiledExpression->getType();
          break;

        case 'array':
          $parameterVariable = $compilationContext->symbolTable->getVariableForRead($compiledExpression->getCode(), $compilationContext, $expression);
          $params[] = $parameterVariable->getName();
          $types[] = $compiledExpression->getType();
          $dynamicTypes[] = $compiledExpression->getType();
          break;

        case 'variable':
          $parameterVariable = $compilationContext->symbolTable->getVariableForRead($compiledExpression->getCode(), $compilationContext, $expression);
          switch ($parameterVariable->getType()) {
            case 'int':
            case 'uint':
            case 'long':
            /* ulong must be stored in string */
            case 'ulong':
              if (isset($readOnlyParameters[$position])) {
                $parameterTempVariable = $compilationContext->symbolTable->getTempLocalVariableForWrite('variable', $compilationContext, $expression);
                $codePrinter->output('ZVAL_LONG(&' . $parameterTempVariable->getName() . ', ' . $parameterVariable->getName() . ');');
                $params[] = '&' . $parameterTempVariable->getName();
              } else {
                $parameterTempVariable = $compilationContext->symbolTable->getTempVariableForWrite('variable', $compilationContext, $expression);
                $codePrinter->output('ZVAL_LONG(' . $parameterTempVariable->getName() . ', ' . $parameterVariable->getName() . ');');
                $params[] = $parameterTempVariable->getName();
              }
              $this->_temporalVariables[] = $parameterTempVariable;
              $types[] = $parameterVariable->getType();
              $dynamicTypes[] = $parameterVariable->getType();
              break;

            case 'double':
              if (isset($readOnlyParameters[$position])) {
                $parameterTempVariable = $compilationContext->symbolTable->getTempLocalVariableForWrite('variable', $compilationContext, $expression);
                $codePrinter->output('ZVAL_DOUBLE(&' . $parameterTempVariable->getName() . ', ' . $parameterVariable->getName() . ');');
                $params[] = '&' . $parameterTempVariable->getName();
              } else {
                $parameterTempVariable = $compilationContext->symbolTable->getTempVariableForWrite('variable', $compilationContext, $expression);
                $codePrinter->output('ZVAL_DOUBLE(' . $parameterTempVariable->getName() . ', ' . $parameterVariable->getName() . ');');
                $params[] = $parameterTempVariable->getName();
              }
              $this->_temporalVariables[] = $parameterTempVariable;
              $types[] = $parameterVariable->getType();
              $dynamicTypes[] = $parameterVariable->getType();
              break;

            case 'bool':
              $params[] = '(' . $parameterVariable->getName() . ' ? ZEPHIR_GLOBAL(global_true) : ZEPHIR_GLOBAL(global_false))';
              $types[] = $parameterVariable->getType();
              $dynamicTypes[] = $parameterVariable->getType();
              break;

            case 'string':
              $params[] = $parameterVariable->getName();
              $types[] = $parameterVariable->getType();
              $dynamicTypes[] = $parameterVariable->getType();
              break;

            case 'array':
              $params[] = $parameterVariable->getName();
              $types[] = $parameterVariable->getType();
              $dynamicTypes[] = $parameterVariable->getType();
              break;

            case 'variable':
              $params[] = $parameterVariable->getName();
              $types[] = $parameterVariable->getType();
              $dynamicTypes[] = $parameterVariable->getDynamicTypes();
              break;

            default:
              throw new CompilerException("Cannot use variable type: " . $parameterVariable->getType() . " as parameter", $expression);
          }
          break;

        default:
          throw new CompilerException("Cannot use value type: " . $compiledExpression->getType() . " as parameter", $expression);
      }
    }

    $this->_resolvedTypes = $types;
    $this->_resolvedDynamicTypes = $dynamicTypes;
    $this->_mustCheckForCopy = $mustCheck;
    return $params;
  }

  /**
   * Resolve parameters using zvals in the stack and without allocating memory for constants
   *
   * @param array $parameters
   * @param CompilationContext $compilationContext
   * @param array $expression
   * @return array
   */
  public function getReadOnlyResolvedParams($parameters, CompilationContext $compilationContext, array $expression) {
    $codePrinter = $compilationContext->codePrinter;
    $exprParams = $this->getResolvedParamsAsExpr($parameters, $compilationContext, $expression, true);

    $params = array();
    $types = array();
    $dynamicTypes = array();

    foreach ($exprParams as $compiledExpression) {
      $expression = $compiledExpression->getOriginal();
      switch ($compiledExpression->getType()) {
        case 'null':
          $params[] = 'ZEPHIR_GLOBAL(global_null)';
          $types[] = 'null';
          $dynamicTypes[] = 'null';
          break;

        case 'int':
        case 'uint':
        case 'long':
          $parameterVariable = $compilationContext->symbolTable->getTempLocalVariableForWrite('variable', $compilationContext, $expression);
          $codePrinter->output('ZVAL_LONG(&' . $parameterVariable->getName() . ', ' . $compiledExpression->getCode() . ');');
          $this->_temporalVariables[] = $parameterVariable;
          $params[] = '&' . $parameterVariable->getName();
          $types[] = $parameterVariable->getType();
          $dynamicTypes[] = $parameterVariable->getType();
          break;

        case 'char':
        case 'uchar':
          $parameterVariable = $compilationContext->symbolTable->getTempLocalVariableForWrite('variable', $compilationContext, $expression);
          $codePrinter->output('ZVAL_LONG(&' . $parameterVariable->getName() . ', \'' . $compiledExpression->getCode() . '\');');
          $this->_temporalVariables[] = $parameterVariable;
          $params[] = '&' . $parameterVariable->getName();
          $types[] = $parameterVariable->getType();
          $dynamicTypes[] = $parameterVariable->getType();
          break;

        case 'double':
          $parameterVariable = $compilationContext->symbolTable->getTempLocalVariableForWrite('variable', $compilationContext, $expression);
          $codePrinter->output('ZVAL_DOUBLE(&' . $parameterVariable->getName() . ', ' . $compiledExpression->getCode() . ');');
          $this->_temporalVariables[] = $parameterVariable;
          $params[] = '&' . $parameterVariable->getName();
          $types[] = $parameterVariable->getType();
          $dynamicTypes[] = $parameterVariable->getType();
          break;

        case 'bool':
          if ($compiledExpression->getCode() == 'true') {
            $params[] = 'ZEPHIR_GLOBAL(global_true)';
          } else {
            if ($compiledExpression->getCode() == 'false') {
              $params[] = 'ZEPHIR_GLOBAL(global_false)';
            } else {
              throw new \Exception('?');
            }
          }
          $types[] = 'bool';
          $dynamicTypes[] = 'bool';
          break;

        case 'ulong':
        case 'string':
        case 'istring':
          $parameterVariable = $compilationContext->symbolTable->getTempLocalVariableForWrite('variable', $compilationContext, $expression);
          $codePrinter->output('ZVAL_STRING(&' . $parameterVariable->getName() . ', "' . Utils::addSlashes($compiledExpression->getCode()) . '", 0);');
          $this->_temporalVariables[] = $parameterVariable;
          $params[] = '&' . $parameterVariable->getName();
          $types[] = $parameterVariable->getType();
          $dynamicTypes[] = $parameterVariable->getType();
          break;

        case 'array':
          $parameterVariable = $compilationContext->symbolTable->getVariableForRead($compiledExpression->getCode(), $compilationContext, $expression);
          $params[] = $parameterVariable->getName();
          $types[] = $parameterVariable->getType();
          $dynamicTypes[] = $parameterVariable->getType();
          break;

        case 'variable':
          $parameterVariable = $compilationContext->symbolTable->getVariableForRead($compiledExpression->getCode(), $compilationContext, $expression);
          switch ($parameterVariable->getType()) {
            case 'int':
            case 'uint':
            case 'long':
            case 'ulong':
              $parameterTempVariable = $compilationContext->symbolTable->getTempLocalVariableForWrite('variable', $compilationContext, $expression);
              $codePrinter->output('ZVAL_LONG(&' . $parameterTempVariable->getName() . ', ' . $compiledExpression->getCode() . ');');
              $params[] = '&' . $parameterTempVariable->getName();
              $types[] = $parameterVariable->getType();
              $dynamicTypes[] = $parameterVariable->getType();
              $this->_temporalVariables[] = $parameterTempVariable;
              break;

            case 'char':
            case 'uchar':
              $parameterTempVariable = $compilationContext->symbolTable->getTempLocalVariableForWrite('variable', $compilationContext, $expression);
              $codePrinter->output('ZVAL_LONG(&' . $parameterTempVariable->getName() . ', ' . $compiledExpression->getCode() . ');');
              $params[] = '&' . $parameterTempVariable->getName();
              $types[] = $parameterVariable->getType();
              $dynamicTypes[] = $parameterVariable->getType();
              $this->_temporalVariables[] = $parameterTempVariable;
              break;

            case 'double':
              $parameterTempVariable = $compilationContext->symbolTable->getTempLocalVariableForWrite('variable', $compilationContext, $expression);
              $codePrinter->output('ZVAL_DOUBLE(&' . $parameterTempVariable->getName() . ', ' . $compiledExpression->getCode() . ');');
              $params[] = '&' . $parameterTempVariable->getName();
              $types[] = $parameterVariable->getType();
              $dynamicTypes[] = $parameterVariable->getType();
              $this->_temporalVariables[] = $parameterTempVariable;
              break;

            case 'bool':
              $params[] = '(' . $parameterVariable->getName() . ' ? ZEPHIR_GLOBAL(global_true) : ZEPHIR_GLOBAL(global_false))';
              $dynamicTypes[] = $parameterVariable->getType();
              $types[] = $parameterVariable->getType();
              break;

            case 'string':
            case 'variable':
            case 'array':
              if ($parameterVariable->isLocalOnly()) {
                $params[] = '&' . $parameterVariable->getName();
              } else {
                $params[] = $parameterVariable->getName();
              }
              $dynamicTypes[] = $parameterVariable->getType();
              $types[] = $parameterVariable->getType();
              break;

            default:
              throw new CompilerException("Cannot use variable type: " . $parameterVariable->getType() . " as parameter", $expression);
          }
          break;

        default:
          throw new CompilerException("Cannot use value type: " . $compiledExpression->getType() . " as parameter", $expression);
      }
    }

    $this->_resolvedTypes = $types;
    $this->_resolvedDynamicTypes = $dynamicTypes;

    return $params;
  }

  /**
   * Tries to find specific an specialized optimizer for function calls
   *
   * @param string $funcName
   * @param array $expression
   * @param Call $call
   * @param CompilationContext $compilationContext
   */
  protected function optimize($funcName, array $expression, FunctionCall $call, CompilationContext $compilationContext) {
    $optimizer = false;

    /**
     * Check if the optimizer is already cached
     */
    if (!isset(self::$_optimizers[$funcName])) {
      $camelizeFunctionName = Utils::camelize($funcName);

      /**
       * Check every optimizer directory for an optimizer
       */
      foreach (self::$_optimizerDirectories as $directory) {
        $path = $directory . DIRECTORY_SEPARATOR . $camelizeFunctionName . 'Optimizer.php';
        if (file_exists($path)) {
          require_once $path;

          $className = 'Zephir\Optimizers\FunctionCall\\' . $camelizeFunctionName . 'Optimizer';
          if (!class_exists($className, false)) {
            throw new \Exception('Class ' . $className . ' cannot be loaded');
          }

          $optimizer = new $className();

          if (!($optimizer instanceof OptimizerAbstract)) {
            throw new \Exception('Class ' . $className . ' must be instance of OptimizerAbstract');
          }

          break;
        }
      }

      self::$_optimizers[$funcName] = $optimizer;
    } else {
      $optimizer = self::$_optimizers[$funcName];
    }

    if ($optimizer) {
      return $optimizer->optimize($expression, $call, $compilationContext);
    }

    return false;
  }

  /**
   * @param array $expression
   * @param CompilationContext $compilationContext
   */
  protected function _callNormal(array $expression, CompilationContext $compilationContext) {
    $funcName = strtolower($expression['name']);

    if ($funcName == 'array') {
      throw new CompilerException("Cannot use 'array' as a function call", $expression);
    }

    /**
     * Try to optimize function calls using existing optimizers
     */
    $compiledExpr = $this->optimize($funcName, $expression, $this, $compilationContext);
    if (is_object($compiledExpr)) {
      return $compiledExpr;
    }

    $exists = true;
    if (!$this->functionExists($funcName, $compilationContext)) {
      $compilationContext->logger->warning("Function \"$funcName\" does not exist at compile time", "nonexistent-function", $expression);
      $exists = false;
    }

    /**
     * Static variables can be passed using local variables saving memory if the function is read only
     */
    if ($exists) {
      $readOnly = $this->isReadOnly($funcName, $expression);
    } else {
      $readOnly = false;
    }

    /**
     * Resolve parameters
     */
    if (isset($expression['parameters'])) {
      if ($readOnly) {
        $params = $this->getReadOnlyResolvedParams($expression['parameters'], $compilationContext, $expression);
      } else {
        $params = $this->getResolvedParams($expression['parameters'], $compilationContext, $expression);
      }
    } else {
      $params = array();
    }

    /**
     * Some functions receive parameters as references
     * We mark those parameters temporary as references to properly pass them
     */
    $this->markReferences($funcName, $params, $compilationContext, $references, $expression);
    $codePrinter = $compilationContext->codePrinter;

    /**
     * Process the expected symbol to be returned
     */
    $this->processExpectedObservedReturn($compilationContext);

    /**
     * At this point the function will be called in the PHP userland.
     * PHP functions only return zvals so we need to validate the target variable is also a zval
     */
    $symbolVariable = $this->getSymbolVariable();
    if ($symbolVariable) {
      if (!$symbolVariable->isVariable()) {
        throw new CompilerException("Returned values by functions can only be assigned to variant variables", $expression);
      }

      /**
       * We don't know the exact dynamic type returned by the method call
       */
      $symbolVariable->setDynamicTypes('undefined');
    }

    /**
     * Include fcall header
     */
    $compilationContext->headersManager->add('kernel/fcall');

    /**
     * Call functions must grown the stack
     */
    $compilationContext->symbolTable->mustGrownStack(true);

    /**
     * Check if the function can have an inline cache
     */
    $functionCache = $compilationContext->cacheManager->getFunctionCache();
    $cachePointer = $functionCache->get($funcName, $compilationContext, $this, $exists);

    /**
     * Add the last call status to the current symbol table
     */
    $this->addCallStatusFlag($compilationContext);

    if (!count($params)) {
      if ($this->isExpectingReturn()) {
        if ($symbolVariable->getName() == 'return_value') {
          $codePrinter->output('ZEPHIR_RETURN_CALL_FUNCTION("' . $funcName . '", ' . $cachePointer . ');');
        } else {
          if ($this->mustInitSymbolVariable()) {
            $symbolVariable->setMustInitNull(true);
            $symbolVariable->trackVariant($compilationContext);
          }
          $codePrinter->output('ZEPHIR_CALL_FUNCTION(&' . $symbolVariable->getName() . ', "' . $funcName . '", ' . $cachePointer . ');');
        }
      } else {
        $codePrinter->output('ZEPHIR_CALL_FUNCTION(NULL, "' . $funcName . '", ' . $cachePointer . ');');
      }
    } else {
      if ($this->isExpectingReturn()) {
        if ($symbolVariable->getName() == 'return_value') {
          $codePrinter->output('ZEPHIR_RETURN_CALL_FUNCTION("' . $funcName . '", ' . $cachePointer . ', ' . join(', ', $params) . ');');
        } else {
          if ($this->mustInitSymbolVariable()) {
            $symbolVariable->setMustInitNull(true);
            $symbolVariable->trackVariant($compilationContext);
          }
          $codePrinter->output('ZEPHIR_CALL_FUNCTION(&' . $symbolVariable->getName() . ', "' . $funcName . '", ' . $cachePointer . ', ' . join(', ', $params) . ');');
        }
      } else {
        $codePrinter->output('ZEPHIR_CALL_FUNCTION(NULL, "' . $funcName . '", ' . $cachePointer . ', ' . join(', ', $params) . ');');
      }
    }

    /**
     * Temporary variables must be copied if they have more than one reference
     */
    foreach ($this->getMustCheckForCopyVariables() as $checkVariable) {
      $codePrinter->output('zephir_check_temp_parameter(' . $checkVariable . ');');
    }

    if (is_array($references)) {
      foreach ($references as $reference) {
        $compilationContext->codePrinter->output('Z_UNSET_ISREF_P(' . $reference . ');');
      }
    }

    $this->addCallStatusOrJump($compilationContext);

    /**
     * We can mark temporary variables generated as idle
     */
    foreach ($this->getTemporalVariables() as $tempVariable) {
      $tempVariable->setIdle(true);
    }

    if ($this->isExpectingReturn()) {
      return new CompiledExpression('variable', $symbolVariable->getRealName(), $expression);
    }

    return new CompiledExpression('null', null, $expression);
  }

  /**
   *
   * @param array $expression
   * @param CompilationContext $compilationContext
   */
  protected function _callDynamic(array $expression, CompilationContext $compilationContext) {
    $variable = $compilationContext->symbolTable->getVariableForRead($expression['name'], $compilationContext, $expression);
    switch ($variable->getType()) {
      case 'variable':
      case 'string':
        break;

      default:
        throw new CompilerException("Variable type: " . $variable->getType() . " cannot be used as dynamic caller", $expression['left']);
    }

    /**
     * Resolve parameters
     */
    if (isset($expression['parameters'])) {
      $params = $this->getResolvedParams($expression['parameters'], $compilationContext, $expression);
    } else {
      $params = array();
    }

    $codePrinter = $compilationContext->codePrinter;

    /**
     * Process the expected symbol to be returned
     */
    $this->processExpectedObservedReturn($compilationContext);

    /**
     * At this point the function will be called in the PHP userland.
     * PHP functions only return zvals so we need to validate the target variable is also a zval
     */
    $symbolVariable = $this->getSymbolVariable();
    if ($symbolVariable) {
      if (!$symbolVariable->isVariable()) {
        throw new CompilerException("Returned values by functions can only be assigned to variant variables", $expression);
      }

      /**
       * We don't know the exact dynamic type returned by the method call
       */
      $symbolVariable->setDynamicTypes('undefined');
    }

    /**
     * Include fcall header
     */
    $compilationContext->headersManager->add('kernel/fcall');

    /**
     * Add the last call status to the current symbol table
     */
    $this->addCallStatusFlag($compilationContext);

    /**
     * Call functions must grown the stack
     */
    $compilationContext->symbolTable->mustGrownStack(true);

    if (!isset($expression['parameters'])) {
      if ($this->isExpectingReturn()) {
        if ($symbolVariable->getName() == 'return_value') {
          $codePrinter->output('ZEPHIR_RETURN_CALL_ZVAL_FUNCTION(' . $variable->getName() . ', NULL, 0);');
        } else {
          if ($this->mustInitSymbolVariable()) {
            $symbolVariable->setMustInitNull(true);
            $symbolVariable->trackVariant($compilationContext);
          }
          $codePrinter->output('ZEPHIR_CALL_ZVAL_FUNCTION(&' . $symbolVariable->getName() . ', ' . $variable->getName() . ', NULL, 0);');
        }
      } else {
        $codePrinter->output('ZEPHIR_CALL_ZVAL_FUNCTION(NULL, ' . $variable->getName() . ', NULL, 0);');
      }
    } else {
      if (count($params)) {
        if ($this->isExpectingReturn()) {
          if ($symbolVariable->getName() == 'return_value') {
            $codePrinter->output('ZEPHIR_RETURN_CALL_ZVAL_FUNCTION(' . $variable->getName() . ', NULL, 0, ' . join(', ', $params) . ');');
          } else {
            if ($this->mustInitSymbolVariable()) {
              $symbolVariable->setMustInitNull(true);
              $symbolVariable->trackVariant($compilationContext);
            }
            $codePrinter->output('ZEPHIR_CALL_ZVAL_FUNCTION(&' . $symbolVariable->getName() . ', ' . $variable->getName() . ', NULL, 0, ' . join(', ', $params) . ');');
          }
        } else {
          $codePrinter->output('ZEPHIR_CALL_ZVAL_FUNCTION(NULL, ' . $variable->getName() . ', NULL, 0, ' . join(', ', $params) . ');');
        }
      } else {
        if ($this->isExpectingReturn()) {
          if ($symbolVariable->getName() == 'return_value') {
            $codePrinter->output('ZEPHIR_RETURN_CALL_ZVAL_FUNCTION(' . $variable->getName() . ', NULL, 0);');
          } else {
            if ($this->mustInitSymbolVariable()) {
              $symbolVariable->setMustInitNull(true);
              $symbolVariable->trackVariant($compilationContext);
            }
            $codePrinter->output('ZEPHIR_CALL_ZVAL_FUNCTION(&' . $symbolVariable->getName() . ', ' . $variable->getName() . ', NULL, 0);');
          }
        } else {
          $codePrinter->output('ZEPHIR_CALL_ZVAL_FUNCTION(NULL, ' . $variable->getName() . ', NULL, 0);');
        }
      }
    }

    /**
     * Temporary variables must be copied if they have more than one reference
     */
    foreach ($this->getMustCheckForCopyVariables() as $checkVariable) {
      $codePrinter->output('zephir_check_temp_parameter(' . $checkVariable . ');');
    }

    $this->addCallStatusOrJump($compilationContext);

    /**
     * We can mark temporary variables generated as idle
     */
    foreach ($this->getTemporalVariables() as $tempVariable) {
      $tempVariable->setIdle(true);
    }

    if ($this->isExpectingReturn()) {
      return new CompiledExpression('variable', $symbolVariable->getRealName(), $expression);
    }

    return new CompiledExpression('null', null, $expression);
  }

  /**
   * Compiles a function
   *
   * @param Expression $expr
   * @param CompilationContext $compilationContext
   * @return CompiledExpression
   * @throws CompilerException
   */
  public function compile(ExpressionAbstract $expr, CompilationContext $compilationContext) {
    $this->_expression = $expr;
    $expression = $expr->getExpression();

    switch ($expression['call-type']) {
      case self::CALL_NORMAL:
        return $this->_callNormal($expression, $compilationContext);
      case self::CALL_DYNAMIC:
        return $this->_callDynamic($expression, $compilationContext);
    }

    return new CompiledExpression('null', null, $expression);
  }

}
