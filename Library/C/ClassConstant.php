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

use Zephir\C\Expression\Constants;
use Zephir\CompilerException;
use Zephir\CompilationContext;
use Zephir\Utils;

/**
 * ClassConstant
 *
 * Represents a class constant
 */
class ClassConstant extends \Zephir\ClassConstantAbstract {

  /**
   * Process the value of the class constant if needed
   *
   * @param compilationContext $compilationContext
   */
  public function processValue($compilationContext) {
    if ($this->value['type'] == 'constant') {
      $constant = new Constants();
      $compiledExpression = $constant->compile($this->value, $compilationContext);

      $this->value = array(
        'type' => $compiledExpression->getType(),
        'value' => $compiledExpression->getCode()
      );
      return;
    }

    if ($this->value['type'] == 'static-constant-access') {
      $expression = new Expression($this->value);
      $compiledExpression = $expression->compile($compilationContext);

      $this->value = array(
        'type' => $compiledExpression->getType(),
        'value' => $compiledExpression->getCode()
      );
      return;
    }
  }

  /**
   * Produce the code to register a class constant
   *
   * @param CompilationContext $compilationContext
   * @throws CompilerException
   * @throws Exception
   */
  public function compile(CompilationContext $compilationContext) {
    $this->processValue($compilationContext);

    switch ($this->value['type']) {
      case 'long':
      case 'int':
        $compilationContext->codePrinter->output(
          "zend_declare_class_constant_long(" .
          $compilationContext->classDefinition->getClassEntry($compilationContext) .
          ", SL(\"" . $this->getName() . "\"), " .
          $this->value['value'] . " TSRMLS_CC);"
        );
        break;

      case 'double':
        $compilationContext->codePrinter->output(
          "zend_declare_class_constant_double(" .
          $compilationContext->classDefinition->getClassEntry($compilationContext) .
          ", SL(\"" . $this->getName() . "\"), " .
          $this->value['value'] . " TSRMLS_CC);"
        );
        break;

      case 'bool':
        if ($this->value['value'] == 'false') {
          $compilationContext->codePrinter->output(
            "zend_declare_class_constant_bool(" .
            $compilationContext->classDefinition->getClassEntry($compilationContext) .
            ", SL(\"" . $this->getName() . "\"), 0 TSRMLS_CC);"
          );
        } else {
          $compilationContext->codePrinter->output(
            "zend_declare_class_constant_bool(" .
            $compilationContext->classDefinition->getClassEntry($compilationContext) .
            ", SL(\"" . $this->getName() . "\"), 1 TSRMLS_CC);"
          );
        }
        break;

      case 'string':
      case 'char':
        $compilationContext->codePrinter->output(
          "zend_declare_class_constant_string(" .
          $compilationContext->classDefinition->getClassEntry($compilationContext) .
          ", SL(\"" . $this->getName() . "\"), \"" .
          Utils::addSlashes($this->value['value']) . "\" TSRMLS_CC);"
        );
        break;

      case 'null':
        $compilationContext->codePrinter->output(
          "zend_declare_class_constant_null(" .
          $compilationContext->classDefinition->getClassEntry($compilationContext) .
          ", SL(\"" . $this->getName() . "\") TSRMLS_CC);"
        );
        break;

      default:
        throw new CompilerException('Type "' . $this->value['type'] . '" is not supported.');
    }
  }

}
