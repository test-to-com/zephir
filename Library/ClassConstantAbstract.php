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
 * ClassConstant
 *
 * Represents a class constant
 */
abstract class ClassConstantAbstract {

  protected $name;

  /**
   * @var array
   */
  protected $value;
  protected $docblock;

  /**
   * ClassConstant constructor
   *
   * @param $name
   * @param $value
   * @param $docBlock
   */
  public function __construct($name, $value, $docBlock) {
    $this->name = $name;
    $this->value = $value;
    $this->docblock = $docBlock;
  }

  /**
   * Returns the constant's name
   *
   * @return string
   */
  public function getName() {
    return $this->name;
  }

  /**
   * Returns the constant's value
   *
   * @todo Rewrite name
   *
   * @return array
   */
  public function getValue() {
    return $this->value;
  }

  /**
   * Get the type of the value of the constant
   *
   * @return string
   */
  public function getValueType() {
    return $this->value['type'];
  }

  /**
   * Get value of the value of the constant
   *
   * @return mixed
   */
  public function getValueValue() {
    if (isset($this->value['value'])) {
      return $this->value['value'];
    }

    return false;
  }

  /**
   * Returns the docblock related to the constant
   *
   * @return string
   */
  public function getDocBlock() {
    return $this->docblock;
  }

  /**
   * Get type of class constant
   *
   * @return string
   */
  public function getType() {
    return $this->value['type'];
  }

  /**
   * Process the value of the class constant if needed
   *
   * @param compilationContext $compilationContext
   */
  abstract public function processValue($compilationContext);

  /**
   * Produce the code to register a class constant
   *
   * @param CompilationContext $compilationContext
   * @throws CompilerException
   * @throws Exception
   */
  abstract public function compile(CompilationContext $compilationContext);
}
