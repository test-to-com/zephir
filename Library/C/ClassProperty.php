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

use Zephir\Builder\StatementsBlockBuilder;
use Zephir\CompilerException;
use Zephir\CompilationContext;
use Zephir\Utils;
use Zephir\Types;

/**
 * ClassProperty
 *
 * Represents a property class
 */
class ClassProperty extends \Zephir\ClassPropertyAbstract {

  /**
   * Returns the C-visibility accessors for the model
   *
   * @return string
   */
  public function getVisibilityAccesor() {
    $modifiers = array();

    foreach ($this->visibility as $visibility) {
      switch ($visibility) {
        case 'protected':
          $modifiers['ZEND_ACC_PROTECTED'] = true;
          break;

        case 'private':
          $modifiers['ZEND_ACC_PRIVATE'] = true;
          break;

        case 'public':
          $modifiers['ZEND_ACC_PUBLIC'] = true;
          break;

        case 'static':
          $modifiers['ZEND_ACC_STATIC'] = true;
          break;

        default:
          throw new \Exception("Unknown modifier " . $visibility);
      }
    }
    return join('|', array_keys($modifiers));
  }

  private function initializeArray($compilationContext) {
    $classDefinition = $this->classDefinition;
    $parentClassDefinition = $classDefinition->getExtendsClassDefinition();

    if (!$this->isStatic()) {
      $constructParentMethod = $parentClassDefinition ? $parentClassDefinition->getInitMethod() : null;
      $constructMethod = $classDefinition->getInitMethod();
    } else {
      $constructParentMethod = $parentClassDefinition ? $parentClassDefinition->getStaticInitMethod() : null;
      $constructMethod = $classDefinition->getStaticInitMethod();
    }

    if ($constructMethod) {
      $statementsBlock = $constructMethod->getStatementsBlock();
      if ($statementsBlock) {
        $statements = $statementsBlock->getStatements();
        $letStatement = $this->getLetStatement()->get();

        $needLetStatementAdded = true;
        foreach ($statements as $statement) {
          if ($statement === $letStatement) {
            $needLetStatementAdded = false;
            break;
          }
        }

        $this->removeInitializationStatements($statements);
        if ($needLetStatementAdded) {
          $newStatements = array();

          /**
           * Start from let statement
           */
          $newStatements[] = $letStatement;

          foreach ($statements as $statement) {
            $newStatements[] = $statement;
          }

          $statementsBlock->setStatements($newStatements);
          $constructMethod->setStatementsBlock($statementsBlock);
          $classDefinition->updateMethod($constructMethod);
        }
      } else {
        $statementsBlockBuilder = new StatementsBlockBuilder(array($this->getLetStatement()), false);
        $constructMethod->setStatementsBlock(new StatementsBlock($statementsBlockBuilder->get()));
        $classDefinition->updateMethod($constructMethod);
      }
    } else {
      $statements = array();
      if ($constructParentMethod) {
        $statements = $constructParentMethod->getStatementsBlock()->getStatements();
      }
      $this->removeInitializationStatements($statements);
      $statements[] = $this->getLetStatement()->get();
      $statementsBlock = new StatementsBlock($statements);

      if ($this->isStatic()) {
        $classDefinition->addStaticInitMethod($statementsBlock);
      } else {
        $classDefinition->addInitMethod($statementsBlock);
      }
    }
  }

  /**
   * Produce the code to register a property
   *
   * @param CompilationContext $compilationContext
   * @throws CompilerException
   */
  public function compile(CompilationContext $compilationContext) {
    switch ($this->defaultValue['type']) {
      case 'long':
      case 'int':
      case 'string':
      case 'double':
      case 'bool':
        $this->declareProperty($compilationContext, $this->defaultValue['type'], $this->defaultValue['value']);
        break;

      case 'array':
      case 'empty-array':
        $this->initializeArray($compilationContext);
      //continue

      case 'null':
        $this->declareProperty($compilationContext, $this->defaultValue['type'], null);
        break;

      case 'static-constant-access':
        $expression = new Expression($this->defaultValue);
        $compiledExpression = $expression->compile($compilationContext);

        $this->declareProperty($compilationContext, $compiledExpression->getType(), $compiledExpression->getCode());
        break;

      default:
        throw new CompilerException('Unknown default type: ' . $this->defaultValue['type'], $this->original);
    }
  }

  /**
   * Declare class property with default value
   *
   * @param CompilationContext $compilationContext
   * @param string $type
   * @param $value
   * @throws CompilerException
   */
  protected function declareProperty(CompilationContext $compilationContext, $type, $value) {
    $codePrinter = $compilationContext->codePrinter;

    if (is_object($value)) {
      return;
    }

    $classEntry = $compilationContext->classDefinition->getClassEntry();

    switch ($type) {
      case 'long':
      case 'int':
        $codePrinter->output("zend_declare_property_long(" . $classEntry . ", SL(\"" . $this->getName() . "\"), " . $value . ", " . $this->getVisibilityAccesor() . " TSRMLS_CC);");
        break;

      case 'double':
        $codePrinter->output("zend_declare_property_double(" . $classEntry . ", SL(\"" . $this->getName() . "\"), " . $value . ", " . $this->getVisibilityAccesor() . " TSRMLS_CC);");
        break;

      case 'bool':
        $codePrinter->output("zend_declare_property_bool(" . $classEntry . ", SL(\"" . $this->getName() . "\"), " . $this->getBooleanCode($value) . ", " . $this->getVisibilityAccesor() . " TSRMLS_CC);");
        break;

      case Types::CHAR:
      case Types::STRING:
        $codePrinter->output("zend_declare_property_string(" . $classEntry . ", SL(\"" . $this->getName() . "\"), \"" . Utils::addSlashes($value, true, $type) . "\", " . $this->getVisibilityAccesor() . " TSRMLS_CC);");
        break;

      case 'array':
      case 'empty-array':
      case 'null':
        $codePrinter->output("zend_declare_property_null(" . $classEntry . ", SL(\"" . $this->getName() . "\"), " . $this->getVisibilityAccesor() . " TSRMLS_CC);");
        break;

      default:
        throw new CompilerException('Unknown default type: ' . $type, $this->original);
    }
  }
}
