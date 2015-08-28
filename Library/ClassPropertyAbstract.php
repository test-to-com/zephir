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

use Zephir\Builder\LiteralBuilder;
use Zephir\Builder\VariableBuilder;
use Zephir\Builder\StatementsBlockBuilder;
use Zephir\Builder\Statements\LetStatementBuilder;
use Zephir\Builder\Statements\IfStatementBuilder;
use Zephir\Builder\Operators\BinaryOperatorBuilder;
use Zephir\CompilerException;
use Zephir\CompilationContext;

/**
 * ClassProperty
 *
 * Represents a property class
 */
abstract class ClassPropertyAbstract {

  /**
   * @var ClassDefinition
   */
  protected $classDefinition;
  protected $visibility;
  protected $name;
  protected $defaultValue;
  protected $docblock;
  protected $original;

  /**
   *
   * @param ClassDefinition $classDefinition
   * @param array $visibility
   * @param string $name
   * @param mixed $defaultValue
   * @param string $docBlock
   * @param array $original
   */
  public function __construct(ClassDefinitionAbstract $classDefinition, $visibility, $name, $defaultValue, $docBlock, $original) {
    $this->checkVisibility($visibility, $name, $original);

    $this->classDefinition = $classDefinition;
    $this->visibility = $visibility;
    $this->name = $name;
    $this->defaultValue = $defaultValue;
    $this->docblock = $docBlock;
    $this->original = $original;

    if (!is_array($this->defaultValue)) {
      $this->defaultValue = array();
      $this->defaultValue['type'] = 'null';
      $this->defaultValue['value'] = null;
    }
  }

  /**
   * Returns the class definition where the method was declared
   *
   * @return ClassDefinition
   */
  public function getClassDefinition() {
    return $this->classDefinition;
  }

  /**
   * Returns the property name
   *
   * @return string
   */
  public function getName() {
    return $this->name;
  }

  /**
   * @return mixed
   */
  public function getValue() {
    if ($this->defaultValue['type'] == 'array') {
      $result = array();

      foreach ($this->original['default']['left'] as $key) {
        $result[] = $key['value']['value'];
      }

      $this->defaultValue['value'] = $result;
    }

    return $this->defaultValue['value'];
  }

  public function getType() {
    return $this->defaultValue['type'];
  }

  /**
   * @return mixed
   */
  public function getOriginal() {
    return $this->original;
  }

  /**
   * Checks for visibility congruence
   *
   * @param array $visibility
   * @param string $name
   * @param array $original
   */
  public function checkVisibility($visibility, $name, $original) {
    if (in_array('public', $visibility) && in_array('protected', $visibility)) {
      throw new CompilerException("Property '$name' cannot be 'public' and 'protected' at the same time", $original);
    }
    if (in_array('public', $visibility) && in_array('private', $visibility)) {
      throw new CompilerException("Property '$name' cannot be 'public' and 'private' at the same time", $original);
    }
    if (in_array('private', $visibility) && in_array('protected', $visibility)) {
      throw new CompilerException("Property '$name' cannot be 'protected' and 'private' at the same time", $original);
    }
  }

  /**
   * Returns the C-visibility accessors for the model
   *
   * @return string
   */
  abstract public function getVisibilityAccesor();

  /**
   * Returns the docblock related to the property
   *
   * @return string
   */
  public function getDocBlock() {
    return $this->docblock;
  }

  /**
   * Checks whether the variable is static
   *
   * @return boolean
   */
  public function isStatic() {
    return in_array('static', $this->visibility);
  }

  /**
   * Checks whether the variable is public
   *
   * @return boolean
   */
  public function isPublic() {
    return in_array('public', $this->visibility);
  }

  /**
   * Checks whether the variable is protected
   *
   * @return boolean
   */
  public function isProtected() {
    return in_array('protected', $this->visibility);
  }

  /**
   * Checks whether the variable is private
   *
   * @return boolean
   */
  public function isPrivate() {
    return in_array('private', $this->visibility);
  }

  /**
   * Produce the code to register a property
   *
   * @param CompilationContext $compilationContext
   * @throws CompilerException
   */
  abstract public function compile(CompilationContext $compilationContext);

  /**
   * Removes all initialization statements related to this property
   */
  protected function removeInitializationStatements(&$statements) {
    foreach ($statements as $index => $statement) {
      if (!$this->isStatic()) {
        if ($statement['expr']['left']['right']['value'] == $this->name) {
          unset($statements[$index]);
        }
      } else {
        if ($statement['assignments'][0]['property'] == $this->name) {
          unset($statements[$index]);
        }
      }
    }
  }

  /**
   * @return LetStatementBuilder
   */
  protected function getLetStatement() {
    if ($this->isStatic()) {
      return new LetStatementBuilder(array(
        'assign-type' => 'static-property',
        'operator' => 'assign',
        'variable' => '\\' . $this->classDefinition->getCompleteName(),
        'property' => $this->name,
        'file' => $this->original['default']['file'],
        'line' => $this->original['default']['line'],
        'char' => $this->original['default']['char'],
        ), $this->original['default']);
    }
    $lsb = new LetStatementBuilder(array(
      'assign-type' => 'object-property',
      'operator' => 'assign',
      'variable' => 'this',
      'property' => $this->name,
      'file' => $this->original['default']['file'],
      'line' => $this->original['default']['line'],
      'char' => $this->original['default']['char'],
      ), $this->original['default']);
    return new IfStatementBuilder(
      new BinaryOperatorBuilder(
      'equals', new BinaryOperatorBuilder(
      'property-access', new VariableBuilder('this'), new LiteralBuilder('string', $this->name)
      ), new LiteralBuilder('null', null)
      ), new StatementsBlockBuilder(array($lsb))
    );
  }

  /**
   * @param $value
   * @return bool|string
   */
  protected function getBooleanCode($value) {
    if ($value && ($value == 'true' || $value === true)) {
      return '1';
    } else {
      if ($value == 'false' || $value === false) {
        return '0';
      }
    }

    return (boolean) $value;
  }

  /**
   * Declare class property with default value
   *
   * @param CompilationContext $compilationContext
   * @param string $type
   * @param $value
   * @throws CompilerException
   */
  abstract protected function declareProperty(CompilationContext $compilationContext, $type, $value);
}
