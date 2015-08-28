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

/**
 * Expressions
 *
 * Represents an expression. Most language constructs in a language are expressions
 */
abstract class ExpressionAbstract {

  protected $_expression;
  protected $_expecting = true;
  protected $_readOnly = false;
  protected $_noisy = true;
  protected $_stringOperation = false;
  protected $_expectingVariable;
  protected $_evalMode = false;

  /**
   * Expression constructor
   *
   * @param array $expression
   */
  public function __construct(array $expression) {
    $this->_expression = $expression;
  }

  /**
   * Returns the original expression
   *
   * @return array
   */
  public function getExpression() {
    return $this->_expression;
  }

  /**
   * Sets if the variable must be resolved into a direct variable symbol
   * create a temporary value or ignore the return value
   *
   * @param boolean $expecting
   * @param Variable $expectingVariable
   */
  public function setExpectReturn($expecting, Variable $expectingVariable = null) {
    $this->_expecting = $expecting;
    $this->_expectingVariable = $expectingVariable;
  }

  /**
   * Sets if the result of the evaluated expression is read only
   *
   * @param boolean $readOnly
   */
  public function setReadOnly($readOnly) {
    $this->_readOnly = $readOnly;
  }

  /**
   * Checks if the result of the evaluated expression is read only
   *
   * @return boolean
   */
  public function isReadOnly() {
    return $this->_readOnly;
  }

  /**
   * Checks if the returned value by the expression
   * is expected to be assigned to an external symbol
   *
   * @return boolean
   */
  public function isExpectingReturn() {
    return $this->_expecting;
  }

  /**
   * Returns the variable which is expected to return the
   * result of the expression evaluation
   *
   * @return Variable
   */
  public function getExpectingVariable() {
    return $this->_expectingVariable;
  }

  /**
   * Sets whether the expression must be resolved in "noisy" mode
   *
   * @param boolean $noisy
   */
  public function setNoisy($noisy) {
    $this->_noisy = $noisy;
  }

  /**
   * Checks whether the expression must be resolved in "noisy" mode
   *
   * @return boolean
   */
  public function isNoisy() {
    return $this->_noisy;
  }

  /**
   * Sets if current operation is a string operation like "concat"
   * thus avoiding promote numeric strings to longs
   *
   * @param boolean $stringOperation
   */
  public function setStringOperation($stringOperation) {
    $this->_stringOperation = $stringOperation;
  }

  /**
   * Checks if the result of the evaluated expression is intended to be used
   * in a string operation like "concat"
   *
   * @return boolean
   */
  public function isStringOperation() {
    return $this->_stringOperation;
  }

  /**
   * Sets if the expression is being evaluated in an evaluation like the ones in 'if' and 'while' statements
   *
   * @param boolean $evalMode
   */
  public function setEvalMode($evalMode) {
    $this->_evalMode = $evalMode;
  }

  /**
   * Compiles foo = []
   *
   * @param array $expression
   * @param CompilationContext $compilationContext
   * @return CompiledExpression
   */
  public function emptyArray($expression, CompilationContext $compilationContext) {
    /**
     * Resolves the symbol that expects the value
     */
    if ($this->_expecting) {
      if ($this->_expectingVariable) {
        $symbolVariable = & $this->_expectingVariable;
        $symbolVariable->initVariant($compilationContext);
      } else {
        $symbolVariable = $compilationContext->symbolTable->getTempVariableForWrite('variable', $compilationContext, $expression);
      }
    } else {
      $symbolVariable = $compilationContext->symbolTable->getTempVariableForWrite('variable', $compilationContext, $expression);
    }

    /**
     * Variable that receives property accesses must be polymorphic
     */
    if (!$symbolVariable->isVariable() && $symbolVariable->getType() != 'array') {
      throw new CompilerException("Cannot use variable: " . $symbolVariable->getName() . '(' . $symbolVariable->getType() . ") to create empty array", $expression);
    }

    /**
     * Mark the variable as an 'array'
     */
    $symbolVariable->setDynamicTypes('array');

    $compilationContext->codePrinter->output('array_init(' . $symbolVariable->getName() . ');');

    return new CompiledExpression('array', $symbolVariable->getRealName(), $expression);
  }

  /**
   *
   *
   * @param array $expression
   * @param CompilationContext $compilationContext
   * @return CompiledExpression
   */
  abstract public function compileTypeHint($expression, CompilationContext $compilationContext);

  /**
   * Resolves an expression
   *
   * @param CompilationContext $compilationContext
   * @return bool|CompiledExpression|mixed
   * @throws CompilerException
   */
  abstract public function compile(CompilationContext $compilationContext);
}
