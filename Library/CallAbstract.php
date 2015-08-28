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

use Zephir\Detectors\ReadDetector;

/**
 * Call
 *
 * Base class for common functionality in functions/calls
 */
abstract class CallAbstract {

  /**
   * Call expression
   * @var Expression
   */
  protected $_expression;
  protected $_mustInit;
  protected $_symbolVariable;
  protected $_isExpecting = false;
  protected $_resolvedParams;
  protected $_reflection;
  protected $_resolvedTypes = array();
  protected $_resolvedDynamicTypes = array();
  protected $_temporalVariables = array();
  protected $_mustCheckForCopy = array();

  /**
   * Processes the symbol variable that will be used to return
   * the result of the symbol call
   *
   * @param CompilationContext $compilationContext
   */
  public function processExpectedReturn(CompilationContext $compilationContext) {
    $expr = $this->_expression;
    $expression = $expr->getExpression();

    /**
     * Create temporary variable if needed
     */
    $mustInit = false;
    $symbolVariable = null;
    $isExpecting = $expr->isExpectingReturn();
    if ($isExpecting) {
      $symbolVariable = $expr->getExpectingVariable();
      if (is_object($symbolVariable)) {
        $readDetector = new ReadDetector($expression);
        if ($readDetector->detect($symbolVariable->getName(), $expression)) {
          $symbolVariable = $compilationContext->symbolTable->getTempVariableForWrite(
            'variable', $compilationContext, $expression
          );
        } else {
          $mustInit = true;
        }
      } else {
        $symbolVariable = $compilationContext->symbolTable->getTempVariableForWrite('variable', $compilationContext, $expression);
      }
    }

    $this->_mustInit = $mustInit;
    $this->_symbolVariable = $symbolVariable;
    $this->_isExpecting = $isExpecting;
  }

  /**
   * Processes the symbol variable that will be used to return
   * the result of the symbol call
   *
   * @param CompilationContext $compilationContext
   */
  public function processExpectedObservedReturn(CompilationContext $compilationContext) {
    $expr = $this->_expression;
    $expression = $expr->getExpression();

    /**
     * Create temporary variable if needed
     */
    $mustInit = false;
    $symbolVariable = null;
    $isExpecting = $expr->isExpectingReturn();
    if ($isExpecting) {
      $symbolVariable = $expr->getExpectingVariable();
      if (is_object($symbolVariable)) {
        $readDetector = new ReadDetector($expression);
        if ($readDetector->detect($symbolVariable->getName(), $expression)) {
          $symbolVariable = $compilationContext->symbolTable->getTempVariableForObserveOrNullify('variable', $compilationContext, $expression);
        } else {
          $mustInit = true;
        }
      } else {
        $symbolVariable = $compilationContext->symbolTable->getTempVariableForObserveOrNullify('variable', $compilationContext, $expression);
      }
    }

    $this->_mustInit = $mustInit;
    $this->_symbolVariable = $symbolVariable;
    $this->_isExpecting = $isExpecting;
  }

  /**
   * Processes the symbol variable that will be used to return
   * the result of the symbol call. If a temporal variable is used
   * as returned value only the body is freed between calls
   *
   * @param CompilationContext $compilationContext
   */
  public function processExpectedComplexLiteralReturn(CompilationContext $compilationContext) {
    $expr = $this->_expression;
    $expression = $expr->getExpression();

    /**
     * Create temporary variable if needed
     */
    $mustInit = false;
    $isExpecting = $expr->isExpectingReturn();
    if ($isExpecting) {
      $symbolVariable = $expr->getExpectingVariable();
      if (is_object($symbolVariable)) {
        $readDetector = new ReadDetector($expression);
        if ($readDetector->detect($symbolVariable->getName(), $expression)) {
          $symbolVariable = $compilationContext->symbolTable->getTempComplexLiteralVariableForWrite('variable', $compilationContext, $expression);
        } else {
          $mustInit = true;
        }
      } else {
        $symbolVariable = $compilationContext->symbolTable->getTempComplexLiteralVariableForWrite('variable', $compilationContext, $expression);
      }
    }

    $this->_mustInit = $mustInit;
    $this->_symbolVariable = $symbolVariable;
    $this->_isExpecting = $isExpecting;
  }

  /**
   * Check if an external expression is expecting the call return a value
   *
   * @return boolean
   */
  public function isExpectingReturn() {
    return $this->_isExpecting;
  }

  /**
   * Returns if the symbol to be returned by the call must be initialized
   *
   * @return boolean
   */
  public function mustInitSymbolVariable() {
    return $this->_mustInit;
  }

  /**
   * Returns the symbol variable that must be returned by the call
   *
   * @param boolean $useTemp
   * @param CompilationContext $compilationContext
   * @return Variable
   */
  public function getSymbolVariable($useTemp = false, CompilationContext $compilationContext = null) {
    $symbolVariable = $this->_symbolVariable;
    if ($useTemp && !is_object($symbolVariable)) {
      return $compilationContext->symbolTable->getTempVariableForWrite('variable', $compilationContext);
    }
    return $symbolVariable;
  }

  /**
   * Resolves paramameters
   *
   * @param array $parameters
   * @param CompilationContext $compilationContext
   * @param array $expression
   * @param boolean $readOnly
   * @return array|null|CompiledExpression[]
   */
  abstract public function getResolvedParamsAsExpr($parameters, CompilationContext $compilationContext, $expression, $readOnly = false);

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
  abstract public function getResolvedParams($parameters, CompilationContext $compilationContext, array $expression, $calleeDefinition = null);

  /**
   * Resolve parameters using zvals in the stack and without allocating memory for constants
   *
   * @param array $parameters
   * @param CompilationContext $compilationContext
   * @param array $expression
   * @return array
   */
  abstract public function getReadOnlyResolvedParams($parameters, CompilationContext $compilationContext, array $expression);

  /**
   * Add the last-call-status flag to the current symbol table
   *
   * @param CompilationContext $compilationContext
   */
  public function addCallStatusFlag(CompilationContext $compilationContext) {
    if (!$compilationContext->symbolTable->hasVariable('ZEPHIR_LAST_CALL_STATUS')) {
      $callStatus = new Variable('int', 'ZEPHIR_LAST_CALL_STATUS', $compilationContext->currentBranch);
      $callStatus->setIsInitialized(true, $compilationContext, array());
      $callStatus->increaseUses();
      $callStatus->setReadOnly(true);
      $compilationContext->symbolTable->addRawVariable($callStatus);
    }
  }

  /**
   * Checks the last call status or make a label jump to the next catch block
   *
   * @param CompilationContext $compilationContext
   */
  public function addCallStatusOrJump(CompilationContext $compilationContext) {
    $compilationContext->headersManager->add('kernel/fcall');
    if (!$compilationContext->insideTryCatch) {
      $compilationContext->codePrinter->output('zephir_check_call_status();');
    } else {
      $compilationContext->codePrinter->output(
        'zephir_check_call_status_or_jump(try_end_' . $compilationContext->insideTryCatch . ');'
      );
    }
  }

  /**
   * Checks if temporary parameters must be copied or not
   *
   * @param CompilationContext $compilationContext
   */
  public function checkTempParameters(CompilationContext $compilationContext) {
    $compilationContext->headersManager->add('kernel/fcall');
    foreach ($this->getMustCheckForCopyVariables() as $checkVariable) {
      $compilationContext->codePrinter->output('zephir_check_temp_parameter(' . $checkVariable . ');');
    }
  }

  /**
   * Return resolved parameter types
   *
   * @return array
   */
  public function getResolvedTypes() {
    return $this->_resolvedTypes;
  }

  /**
   * Return resolved parameter dynamic types
   *
   * @return array
   */
  public function getResolvedDynamicTypes() {
    return $this->_resolvedDynamicTypes;
  }

  /**
   * Returns the temporal variables generated during the parameter resolving
   *
   * @return Variable[]
   */
  public function getTemporalVariables() {
    return $this->_temporalVariables;
  }

  /**
   * Parameters to check if they must be copied
   *
   * @return array
   */
  public function getMustCheckForCopyVariables() {
    return $this->_mustCheckForCopy;
  }

}
