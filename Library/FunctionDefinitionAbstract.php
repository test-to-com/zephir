<?php

/*
  +----------------------------------------------------------------------+
  | Zephir Language                                                      |
  +----------------------------------------------------------------------+
  | Copyright (c) 2013-2015 Zephir Team                                  |
  +----------------------------------------------------------------------+
  | This source file is subject to version 1.0 of the MIT license,       |
  | that is bundled with this package in the file LICENSE, and is        |
  | available through the world-wide-web at the following url:           |
  | http://www.zephir-lang.com/license                                   |
  |                                                                      |
  | If you did not receive a copy of the MIT license and are unable      |
  | to obtain it through the world-wide-web, please send a note to       |
  | license@zephir-lang.com so we can mail you a copy immediately.       |
  +----------------------------------------------------------------------+
 */

namespace Zephir;

use Zephir\Passes\LocalContextPass;
use Zephir\Passes\StaticTypeInference;
use Zephir\Passes\CallGathererPass;
use Zephir\CompilerException;
use Zephir\CompilationContext;
use Zephir\SymbolTable;
use Zephir\Statements\StatementsBlockAbstract;

/**
 * FunctionDefinition
 *
 * Represents a function (method)
 */
abstract class FunctionDefinitionAbstract {

  /**
   * @var string
   */
  protected $namespace;

  /**
   * @var string
   */
  protected $name;

  /**
   * @var ClassMethodParameters
   */
  protected $parameters;
  protected $statements;

  /**
   * Types returned by the method
   *
   * @var array
   */
  protected $returnTypes;

  /**
   * Class type hints returned by the method
   */
  protected $returnClassTypes;

  /**
   * Whether the variable is void
   *
   * @var boolean
   */
  protected $void = false;

  /**
   * Whether the variable is global
   * or namespaced
   *
   * @var boolean
   */
  protected $isGlobal = false;

  /**
   * @var array|null
   *
   * @var boolean
   */
  protected $expression;

  /**
   * LocalContextPass
   *
   * @var LocalContextPass
   */
  protected $localContext;

  /**
   * Static Type Inference Pass
   *
   * @var StaticTypeInferencePass
   */
  protected $typeInference;

  /**
   * Call Gatherer Pass
   *
   * @var CallGathererPass
   */
  protected $callGathererPass;

  /**
   * FunctionDefinition constructor
   *
   * @param $name
   * @param $parameters
   * @param StatementsBlockAbstract $statements
   * @param null $returnType
   * @param array $original
   */
  public function __construct($namespace, $name, $parameters, StatementsBlockAbstract $statements = null, $returnType = null, array $original = null) {
    $this->namespace = $namespace;
    $this->name = $name;
    $this->parameters = $parameters;
    $this->statements = $statements;
    $this->expression = $original;

    if ($returnType['void']) {
      $this->void = true;
      return;
    }

    if (isset($returnType['list'])) {
      $types = array();
      $castTypes = array();
      foreach ($returnType['list'] as $returnTypeItem) {
        if (isset($returnTypeItem['cast'])) {
          if (isset($returnTypeItem['cast']['collection'])) {
            continue;
          }
          $castTypes[$returnTypeItem['cast']['value']] = $returnTypeItem['cast']['value'];
        } else {
          $types[$returnTypeItem['data-type']] = $returnTypeItem;
        }
      }
      if (count($castTypes)) {
        $types['object'] = array();
        $this->returnClassTypes = $castTypes;
      }
      if (count($types)) {
        $this->returnTypes = $types;
      }
    }
  }

  /**
   * Getter for statements block
   *
   * @return StatementsBlockAbstract $statements Statements block
   */
  public function getStatementsBlockAbstract() {
    return $this->statements;
  }

  /**
   * Setter for statements block
   *
   * @param StatementsBlockAbstract $statementsBlock
   */
  public function setStatementsBlockAbstract(StatementsBlockAbstract $statementsBlock) {
    $this->statements = $statementsBlock;
  }

  public function getNamespace() {
    return $this->namespace;
  }

  public function setNamespace($namespace) {
    $this->namespace = $namespace;
  }

  /**
   * Returns the method name
   *
   * @return string
   */
  public function getName() {
    return $this->name;
  }

  /**
   * Get the internal name used in generated C code
   */
  public function getInternalName() {
    return ($this->isGlobal() ? 'g_' : 'f_') . str_replace('\\', '_', $this->namespace) . '_' . $this->getName();
  }

  public function isGlobal() {
    return $this->isGlobal;
  }

  public function setGlobal($global) {
    $this->isGlobal = $global;
  }

  /**
   * Returns the class name including its namespace
   *
   * @return string
   */
  public function getCompleteName() {
    return $this->namespace . '\\' . $this->name;
  }

  /**
   * Returns the parameters
   *
   * @return ClassMethodParameters
   */
  public function getParameters() {
    return $this->parameters;
  }

  /**
   * Checks if the method has return-type or cast hints
   *
   * @return boolean
   */
  public function hasReturnTypes() {
    if (count($this->returnTypes)) {
      return true;
    }

    if (count($this->returnClassTypes)) {
      return true;
    }

    return false;
  }

  /**
   * Checks whether at least one return type hint is null compatible
   *
   * @param string $type
   * @return boolean
   */
  public function areReturnTypesNullCompatible($type = null) {
    if (count($this->returnTypes)) {
      foreach ($this->returnTypes as $returnType => $definition) {
        switch ($returnType) {
          case 'null':
            return true;
        }
      }
    }
    return false;
  }

  /**
   * Checks whether at least one return type hint is integer compatible
   *
   * @param string $type
   * @return boolean
   */
  public function areReturnTypesIntCompatible($type = null) {
    if (count($this->returnTypes)) {
      foreach ($this->returnTypes as $returnType => $definition) {
        switch ($returnType) {
          case 'int':
          case 'uint':
          case 'char':
          case 'uchar':
          case 'long':
          case 'ulong':
            return true;
        }
      }
    }
    return false;
  }

  /**
   * Checks whether at least one return type hint is double compatible
   *
   * @param string $type
   * @return boolean
   */
  public function areReturnTypesDoubleCompatible($type = null) {
    if (count($this->returnTypes)) {
      foreach ($this->returnTypes as $returnType => $definition) {
        switch ($returnType) {
          case 'double':
            return true;
        }
      }
    }
    return false;
  }

  /**
   * Checks whether at least one return type hint is integer compatible
   *
   * @param string $type
   * @return boolean
   */
  public function areReturnTypesBoolCompatible($type = null) {
    if (count($this->returnTypes)) {
      foreach ($this->returnTypes as $returnType => $definition) {
        switch ($returnType) {
          case 'bool':
            return true;
        }
      }
    }
    return false;
  }

  /**
   * Checks whether at least one return type hint is integer compatible
   *
   * @param string $type
   * @return boolean
   */
  public function areReturnTypesStringCompatible($type = null) {
    if (count($this->returnTypes)) {
      foreach ($this->returnTypes as $returnType => $definition) {
        switch ($returnType) {
          case 'string':
            return true;
        }
      }
    }
    return false;
  }

  /**
   * Returned type hints by the method
   *
   * @return array
   */
  public function getReturnTypes() {
    return $this->returnTypes;
  }

  /**
   * Returned class-type hints by the method
   *
   * @return array
   */
  public function getReturnClassTypes() {
    return $this->returnClassTypes;
  }

  public function isConstructor() {
    return false;
  }

  /**
   * Returns the number of parameters the method has
   *
   * @return boolean
   */
  public function hasParameters() {
    if (is_object($this->parameters)) {
      return count($this->parameters->getParameters()) > 0;
    }
    return false;
  }

  /**
   * Returns the number of parameters the method has
   *
   * @return int
   */
  public function getNumberOfParameters() {
    if (is_object($this->parameters)) {
      return count($this->parameters->getParameters());
    }
    return 0;
  }

  /**
   * Returns the number of required parameters the method has
   *
   * @return int
   */
  public function getNumberOfRequiredParameters() {
    if (is_object($this->parameters)) {
      $parameters = $this->parameters->getParameters();
      if (count($parameters)) {
        $required = 0;
        foreach ($parameters as $parameter) {
          if (!isset($parameter['default'])) {
            $required++;
          }
        }
        return $required;
      }
    }
    return 0;
  }

  /**
   * Returns the number of required parameters the method has
   *
   * @return string
   */
  public function getInternalParameters() {
    if (is_object($this->parameters)) {
      $parameters = $this->parameters->getParameters();
      if (count($parameters)) {
        return count($parameters) . ', ...';
      }
    }
    return "";
  }

  /**
   * Checks if the method must not return any value
   *
   * @return boolean
   */
  public function isVoid() {
    return $this->void;
  }

  /**
   * Checks if method is a shortcut
   *
   * @return bool
   */
  public function isShortcut() {
    return $this->expression && $this->expression['type'] == 'shortcut';
  }

  /**
   * Return shortcut method name
   *
   * @return mixed
   */
  public function getShortcutName() {
    return $this->expression['name'];
  }

  /**
   * Returns the local context pass information
   *
   * @return LocalContextPass
   */
  public function getLocalContextPass() {
    return $this->localContext;
  }

  /**
   * Returns the static type inference pass information
   *
   * @return StaticTypeInference
   */
  public function getStaticTypeInferencePass() {
    return $this->typeInference;
  }

  /**
   * Returns the call gatherer pass information
   *
   * @return CallGathererPass
   */
  public function getCallGathererPass() {
    return $this->callGathererPass;
  }

  /**
   * Replace macros
   *
   * @param SymbolTable $symbolTable
   * @param string $containerCode
   */
  abstract public function removeMemoryStackReferences(SymbolTable $symbolTable, $containerCode);

  /**
   * Assigns a default value
   *
   * @param array $parameter
   * @param CompilationContext $compilationContext
   * @return string
   * @throws CompilerException
   */
  abstract public function assignDefaultValue(array $parameter, CompilationContext $compilationContext);

  /**
   * Assigns a zval value to a static low-level type
   *
   * @todo rewrite this to build ifs and throw from builders
   *
   * @param array $parameter
   * @param CompilationContext $compilationContext
   * @return string
   * @throws CompilerException
   */
  abstract public function checkStrictType(array $parameter, CompilationContext $compilationContext);

  /**
   * Assigns a zval value to a static low-level type
   *
   * @param array $parameter
   * @param CompilationContext $compilationContext
   * @return string
   * @throws CompilerException
   */
  abstract public function assignZvalValue(array $parameter, CompilationContext $compilationContext);

  /**
   * Pre-compiles the method making compilation pass data (static inference, local-context-pass) available to other methods
   *
   * @param CompilationContext $compilationContext
   * @return null
   * @throws CompilerException
   */
  public function preCompile(CompilationContext $compilationContext) {
    $localContext = null;
    $typeInference = null;
    $callGathererPass = null;

    if (is_object($this->statements)) {
      /**
       * This pass checks for zval variables than can be potentially
       * used without allocating memory and track it
       * these variables are stored in the stack
       */
      if ($compilationContext->config->get('local-context-pass', 'optimizations')) {
        $localContext = new LocalContextPass();
        $localContext->pass($this->statements);
      }

      /**
       * This pass tries to infer types for dynamic variables
       * replacing them by low level variables
       */
      if ($compilationContext->config->get('static-type-inference', 'optimizations')) {
        $typeInference = new StaticTypeInference();
        $typeInference->pass($this->statements);
        if ($compilationContext->config->get('static-type-inference-second-pass', 'optimizations')) {
          $typeInference->reduce();
          $typeInference->pass($this->statements);
        }
      }

      /**
       * This pass counts how many times a specific
       */
      if ($compilationContext->config->get('call-gatherer-pass', 'optimizations')) {
        $callGathererPass = new CallGathererPass($compilationContext);
        $callGathererPass->pass($this->statements);
      }
    }

    $this->localContext = $localContext;
    $this->typeInference = $typeInference;
    $this->callGathererPass = $callGathererPass;
  }

  /**
   * Compiles the method
   *
   * @param CompilationContext $compilationContext
   * @return null
   * @throws CompilerException
   */
  abstract public function compile(CompilationContext $compilationContext);

  /**
   * Simple method to check if one of the paths are returning the right expected type
   *
   * @param array $statement
   * @return boolean
   */
  public function hasChildReturnStatementType($statement) {
    if (!isset($statement['statements']) || !is_array($statement['statements'])) {
      return false;
    }

    if ($statement['type'] == 'if') {
      $ret = false;

      $statements = $statement['statements'];
      foreach ($statements as $item) {
        $type = isset($item['type']) ? $item['type'] : null;
        if ($type == 'return' || $type == 'throw') {
          $ret = true;
        } else {
          $ret = $this->hasChildReturnStatementType($item);
        }
      }

      if (!$ret || !isset($statement['else_statements'])) {
        return false;
      }

      $statements = $statement['else_statements'];
      foreach ($statements as $item) {
        $type = isset($item['type']) ? $item['type'] : null;
        if ($type == 'return' || $type == 'throw') {
          return true;
        } else {
          return $this->hasChildReturnStatementType($item);
        }
      }
    } else {
      $statements = $statement['statements'];
      foreach ($statements as $item) {
        $type = isset($item['type']) ? $item['type'] : null;
        if ($type == 'return' || $type == 'throw') {
          return true;
        } else {
          return $this->hasChildReturnStatementType($item);
        }
      }
    }

    return false;
  }

}
