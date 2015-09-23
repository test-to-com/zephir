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

namespace Zephir\PHP\Phases;

use Zephir\Common\Phase as IPhase;

/**
 * Normalizes the AST, in doing so it performs the following functions,
 * (among others):
 * 1. Performs expansion of expressions, so as to leave only the base AST
 * required to evaluate the expression (example: closure-arrow is replaced by
 * a closure function, with the required statements).
 * 2. Expands sudo objects methods, into actual PHP function calls.
 * 3. Removes 'let' assignment and replaces it by assignments statements.
 * 
 * @author Paulo Ferreira <pf at sourcenotes.org>
 */
class InlineNormalize implements IPhase {

  // Mixins
  use \Zephir\Common\Mixins\DI;

  protected $sudo_methods = [
    'array' => [
      'join',
      'reversed',
      'rev',
      'diff',
      'flip',
      'fill',
      'walk',
      'haskey',
      'keys',
      'values',
      'split',
      'combine',
      'intersect',
      'merge',
      'mergerecursive',
      'pad',
      'pop',
      'push',
      'rand',
      'replace',
      'map',
      'replacerecursive',
      'shift',
      'slice',
      'splice',
      'sum',
      'unique',
      'prepend',
      'count',
      'current',
      'each',
      'end',
      'key',
      'next',
      'prev',
      'reset',
      'sort',
      'sortbykey',
      'reversesort',
      'reversesortbykey',
      'shuffle',
      'tojson',
      'reduce'
    ],
    'string' => [
    ]
  ];

  /**
   * Process the AST
   * 
   * @param array $ast AST to be processed
   * @return array Old or Transformed AST
   */
  public function top($ast) {
    return $ast;
  }

  /**
   * Process Class or Interface Constant
   * 
   * @param array $class Class Definition
   * @param array $constant Class Constant Definition
   * @return array New Constant Definition, 'NULL' if to be removed
   * @throws \Exception On error Parsing Constant
   */
  public function constant(&$class, $constant) {
    return $constant;
  }

  /**
   * Process Class or Interface Property
   * 
   * @param array $class Class Definition
   * @param array $property Class Property Definition
   * @return array New Property Definition, 'NULL' if to be removed
   * @throws \Exception On error Parsing Property
   */
  public function property(&$class, $property) {
    return $property;
  }

  /**
   * Process Class or Interface Method
   * 
   * @param array $class Class Definition
   * @param array $method Class Method Definition
   * @return array New Property Definition, 'NULL' if to be removed
   */
  public function method(&$class, $method) {
    $method['statements'] = $this->_processStatementBlock($class, $method, $method['statements']);
    return $method;
  }

  protected function _processStatementBlock(&$class, &$method, $block) {
    // Process Statement Block
    $statements = [];
    foreach ($block as $statement) {

      // Process Current Statement
      list($prepend, $current, $append) = $this->_processStatement($class, $method, $statement);

      // Do we need to insert statements before the current one?
      if (isset($prepend) && count($prepend)) { // YES
        $statements = array_merge($statements, $prepend);
      }

      // Did we still have a current statement?
      if (isset($current)) { // YES: Add It
        $statements[] = $statement;
      }

      // Do we need to insert statements after the current one?
      if (isset($append) && count($append)) {
        $statements = array_merge($statements, $append);
      }
    }

    return $statements;
  }

  protected function _processStatement(&$class, &$method, $statement) {
    $type = $statement['type'];

    $handler = $this->_handlerName("_statement", ucfirst($type));
    if (method_exists($this, $handler)) {
      return $this->$handler($class, $method, $statement);
    } else if (method_exists($this, "_statementDEFAULT")) {
      return $this->$handler($class, $method, $statement);
    } else {
      throw new \Exception("Unhandled statement type [{$type}] in line [{$statement['line']}]");
    }
  }

  protected function _statementLoop(&$class, &$method, $statement) {
    
  }

  protected function _statementDoWhile(&$class, &$method, $statement) {
    
  }

  protected function _statementWhile(&$class, &$method, $statement) {
    
  }

  protected function _statementFor(&$class, &$method, $statement) {
    
  }

  protected function _statementIf(&$class, &$method, $statement) {
    
  }

  protected function _statementSwitch(&$class, &$method, $statement) {
    
  }

  protected function _statementLet(&$class, &$method, $let) {
    $before = [];
    $after = [];
    $assignments = [];

    foreach ($let['assignments'] as $assigment) {
      $assigment['type'] = $assigment['operator'];
      list($prepend, $assigment, $append) = $this->_processStatement($class, $method, $assigment);
      if (isset($prepend) && count($prepend)) {
        $before = array_merge($before, $prepend);
      }
      $assignments[] = $assigment;
      if (isset($append) && count($append)) {
        $after = array_merge($after, $append);
      }
    }
    if (isset($before) && count($before)) {
      $before = array_merge($before, $assignments);
    } else {
      $before = $assignments;
    }
    return [$before, null, $after];
  }

  protected function _statementAssign(&$class, &$method, $assign) {
    list($before, $expression, $after) = $this->_processExpression($class, $method, $assign['expr']);
    $assign['expr'] = $expression;
    return [$before, $assign, $after];
  }

  protected function _statementReturn(&$class, &$method, $return) {
    list($before, $expression, $after) = $this->_processExpression($class, $method, $return['expr']);
    $return['expr'] = $expression;
    return [$before, $return, $after];
  }

  protected function _statementMcall(&$class, &$method, $statement) {
    list($before, $expression, $after) = $this->_processExpression($class, $method, $statement['expr']);
    $statement['expr'] = $expression;
    return [$before, $statement, $after];
  }

  protected function _statementFcall(&$class, &$method, $statement) {
    list($before, $expression, $after) = $this->_processExpression($class, $method, $statement['expr']);
    $statement['expr'] = $expression;
    return [$before, $statement, $after];
  }

  protected function _processExpression(&$class, &$method, $expression) {
    $before = [];
    $after = [];

    $type = $expression['type'];
    $handler = $this->_handlerName("_expression", ucfirst($type));
    if (method_exists($this, $handler)) {
      return $this->$handler($class, $method, $expression);
    } else if (method_exists($this, "_expressionDEFAULT")) {
      return $this->$handler($class, $method, $expression);
    } else {
      throw new \Exception("Unhandled expression type [{$type}] in line [{$expression['line']}]");
    }
  }

  protected function _expressionFCall(&$class, &$method, $expression) {
    $before = [];
    $parameters = [];
    $after = [];

    // For a function call, we have to check if the parameters use sudo objects
    foreach ($expression['parameters'] as $parameter) {
      list($prepend, $parameter, $append) = $this->_processExpression($class, $method, $parameter['parameter']);
      if (isset($prepend) && count($prepend)) {
        $before = array_merge($before, $prepend);
      }
      $parameters[] = ['parameter' => $parameter];
      if (isset($append) && count($append)) {
        $after = array_merge($after, $append);
      }
    }
    $expression['parameters'] = $parameters;

    return [$before, $expression, $after];
  }

  protected function _expressionMCall(&$class, &$method, $expression) {
    $before = [];
    $parameters = [];
    $after = [];

    // STEP 1: Process Method Call Parameters
    if (isset($expression['parameters'])) {
      foreach ($expression['parameters'] as $parameter) {
        list($prepend, $parameter, $append) = $this->_processExpression($class, $method, $parameter['parameter']);
        if (isset($prepend) && count($prepend)) {
          $before = array_merge($before, $prepend);
        }
        $parameters[] = ['parameter' => $parameter];
        if (isset($append) && count($append)) {
          $after = array_merge($after, $append);
        }
      }
    }
    // NOTE: GARAUNTEES THAT if MCALL has no PARAMETERS, an EMPTY ARRAY IS USED to SHOW THAT
    $expression['parameters'] = $parameters;

    // STEP 2: Determine if we are dealing with a sudo object call
    $sudoobject = null;
    $variable = $expression['variable'];
    switch ($variable['type']) {
      case 'array':
        $sudoobject = 'array';

        // Need to make sure that we expand any array values, before using them
        list($prepend, $parameter, $append) = $this->_processExpression($class, $method, $variable);
        if (isset($prepend) && count($prepend)) {
          $before = array_merge($before, $prepend);
        }
        $expression['variable'] = $variable;
        if (isset($append) && count($append)) {
          $after = array_merge($after, $append);
        }
        break;
      case 'string':
        $sudoobject = 'string';
        break;
      case 'variable':
        $definition = $this->_lookup($class, $method, $variable);
        if (isset($definition)) {
          if (isset($definition['data-type'])) {
            switch ($definition['data-type']) {
              case 'array':
                $sudoobject = 'array';
              case 'string':
                $sudoobject = 'string';
                break;
            }
          }
        }
    }

    // STEP 3: Handle Sudo Object Calls
    // Are we dealing with a sudo object?
    if (isset($sudoobject)) { // YES
      if ($this->_isValidSudoObjectFunction($sudoobject, $expression['name'])) {
        $handler = $this->_handlerName('_expand' . ucfirst($sudoobject), ucfirst($expression['name']));
        if (method_exists($this, $handler)) {
          list($prepend, $expression, $append) = $this->$handler($class, $method, $expression);
          if (isset($prepend) && count($prepend)) {
            $before = array_merge($before, $prepend);
          }
          if (isset($append) && count($append)) {
            $after = array_merge($after, $append);
          }
        } else {
          throw new \Exception("Missing Handler function [{$expression['name']}] for [{$sudoobject}] object.");
        }
      } else {
        throw new \Exception("Function [{$expression['name']}] is not valid for an [{$sudoobject}] object.");
      }
    }

    return [$before, $expression, $after];
  }

  protected function _expressionClosureArrow(&$class, &$method, $expression) {
    $closure = [
      'type' => 'closure',
      'call-type' => 1,
      'parameters' => ['parameter' => $expression['left']],
      'locals' => [],
      'file' => $expression['file'],
      'line' => $expression['line'],
      'char' => $expression['char']
    ];

    /* Currently
     * Closure Arrow - allows only one expression.
     * This expression has to be converted to a single return statement.
     */
    list($prepend, $ret_expression, $append) = $this->_processExpression($class, $closure, $expression['right']);
    if (isset($append) && count($append)) {
      throw new \Exception("A Closure can't have side-effects");
    }

    // Start Creating Statement List
    $statements = isset($prepend) && count($prepend) ? $prepend : [];

    // Create Final Return Statement
    $return = [
      'type' => 'return',
      'expr' => $ret_expression,
      'file' => $expression['file'],
      'line' => $expression['line'],
      'char' => $expression['char']
    ];
    $statements[] = $return;

    // Finish Closure
    $closure['statements'] = $statements;
    return [null, $closure, null];
  }

  protected function _expressionArray(&$class, &$method, $expression) {
    $before = [];
    $after = [];
    $values = [];

    foreach ($expression['left'] as $value) {
      list($prepend, $value, $append) = $this->_processExpression($class, $method, $value['value']);
      if (isset($prepend) && count($prepend)) {
        $before = array_merge($before, $prepend);
      }
      $values[] = ['value' => $value];
      if (isset($append) && count($append)) {
        $after = array_merge($after, $append);
      }
    }
    $expression['left'] = $values;

    return [$before, $expression, $after];
  }

  protected function _expressionConcat(&$class, &$method, $expression) {
    $before = [];
    $after = [];

    // Process Left Expression
    list($prepend, $left, $append) = $this->_processExpression($class, $method, $expression['left']);
    if (isset($prepend) && count($prepend)) {
      $before = array_merge($before, $prepend);
    }
    $expression['left'] = $left;
    if (isset($append) && count($append)) {
      $after = array_merge($after, $append);
    }

    // Process Right Expression
    list($prepend, $right, $append) = $this->_processExpression($class, $method, $expression['right']);
    if (isset($prepend) && count($prepend)) {
      $before = array_merge($before, $prepend);
    }
    $expression['right'] = $right;
    if (isset($append) && count($append)) {
      $after = array_merge($after, $append);
    }

    return [$before, $expression, $after];
  }

  protected function _expressionList(&$class, &$method, $list) {
    $before = [];
    $after = [];

    // Process Left Expression
    list($prepend, $left, $append) = $this->_processExpression($class, $method, $list['left']);
    if (isset($prepend) && count($prepend)) {
      $before = array_merge($before, $prepend);
    }
    $list['left'] = $left;
    if (isset($append) && count($append)) {
      $after = array_merge($after, $append);
    }

    return [$before, $list, $after];
  }

  protected function _expressionMul(&$class, &$method, $expression) {
    return [null, $expression, null];
  }

  protected function _expressionVariable(&$class, &$method, $expression) {
    return [null, $expression, null];
  }

  protected function _expressionString(&$class, &$method, $expression) {
    return [null, $expression, null];
  }

  protected function _expressionChar(&$class, &$method, $expression) {
    return [null, $expression, null];
  }

  protected function _expressionInt(&$class, &$method, $expression) {
    return [null, $expression, null];
  }

  protected function _expandArrayJoin(&$class, &$method, $expression) {
    $variable = $expression['variable'];
    $join_parameters = $expression['parameters'];

    switch (count($join_parameters)) {
      case 0: // $glue not set (using default)
        $parameters = ['parameter' => $variable];
        break;
      case 1:
        $parameters = $join_parameters;
        $parameters[] = ['parameter' => $variable];
        break;
      default:
        throw new \Exception("Array join() requires 0 or 1 parameter");
    }

    $function = [
      'type' => 'fcall',
      'name' => 'join',
      'call-type' => 1,
      'parameters' => $parameters,
      'file' => $expression['file'],
      'line' => $expression['line'],
      'char' => $expression['char']
    ];

    return [null, $function, null];
  }

  protected function _expandArrayReversed(&$class, &$method, $expression) {
    $variable = $expression['variable'];
    $join_parameters = $expression['parameters'];
    switch (count($join_parameters)) {
      case 0: // $glue not set (using default)
        $parameters = ['parameter' => $variable];
        break;
      case 1:
        $parameters = array_merge(['parameter' => $variable], $join_parameters);
        break;
      default:
        throw new \Exception("Array join() requires 0 or 1 parameter");
    }

    $function = [
      'type' => 'fcall',
      'name' => 'array_reverse',
      'call-type' => 1,
      'parameters' => $parameters,
      'file' => $expression['file'],
      'line' => $expression['line'],
      'char' => $expression['char']
    ];

    return [null, $function, null];
  }

  protected function _expandArrayMap(&$class, &$method, $expression) {
    $variable = $expression['variable'];
    $join_parameters = $expression['parameters'];

    // Do we have atleast one parameter
    if (!count($join_parameters)) {
      throw new \Exception("Array join() requires atleast one parameter");
    }

    /* TODO: Syntax Check
     * Verify that the 1st parameter is valid (i.e. is a closure or string)
     */
    $parameters = $join_parameters[0];
    $parameters[] = ['parameter' => $variable];

    // Do we have more than 1 parameter to the join?
    if (count($join_parameters) > 1) { // YES: Append them after the $variable
      // TODO : Syntax Check - Verify that extra parameters are valid (i.e. arrays)
      array_merge($parameters, array_slice($join_parameters, 1));
    }

    $function = [
      'type' => 'fcall',
      'name' => 'array_map',
      'call-type' => 1,
      'parameters' => $parameters,
      'file' => $expression['file'],
      'line' => $expression['line'],
      'char' => $expression['char']
    ];

    return [null, $function, null];
  }

  protected function _isValidSudoObjectFunction($otype, $fname) {
    if (isset($this->sudo_methods[$otype])) {
      return in_array($fname, $this->sudo_methods[$otype]);
    }

    return false;
  }

  protected function _handlerName($prefix, $name) {
    $name = implode(
      array_map(function($e) {
        return ucfirst(trim($e));
      }, explode('-', $name))
    );
    $name = implode(
      array_map(function($e) {
        return ucfirst(trim($e));
      }, explode('_', $name))
    );
    return $prefix . $name;
  }

  protected function _lookup($class, $method, $variable) {
    // TODO : Implement Variable Lookup
    /* Handle this;
      if ($variable['value'] !== 'this') {
      }
     */

    return null;
  }

}
