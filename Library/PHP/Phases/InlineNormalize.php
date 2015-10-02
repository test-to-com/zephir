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
      'combine' => 'array_combine',
      'count' => 'count',
      'current' => 'current',
      'diff' => 'array_diff',
      'each' => 'each',
      'end' => 'end',
      'fill' => 'array_fill',
      'flip' => 'array_flip',
      'haskey' => 'array_key_exists',
      'intersect' => 'array_intersect',
      'join' => 'implode',
      'key' => 'key',
      'keys' => 'array_keys',
      'map' => 'array_map',
      'merge' => 'array_merge',
      'mergerecursive' => 'array_merge_recursive',
      'next' => 'next',
      'pad' => 'array_pad',
      'pop' => 'array_pop',
      'prepend' => 'array_unshift',
      'prev' => 'prev',
      'push' => 'array_push',
      'rand' => 'array_rand',
      'reduce' => 'array_reduce',
      'replace' => 'array_replace',
      'replacerecursive' => 'array_replace_recursive',
      'reset' => 'reset',
      'rev' => 'array_reverse',
      'reversed' => 'array_reverse',
      'reversesort' => 'rsort',
      'reversesortbykey' => 'krsort',
      'shift' => 'array_shift',
      'shuffle' => 'shuffle',
      'slice' => 'array_slice',
      'sort' => 'sort',
      'sortbykey' => 'ksort',
      'splice' => 'array_splice',
      'split' => 'array_chunk',
      'sum' => 'array_sum',
      'tojson' => 'json_encode',
      'unique' => 'array_unique',
      'values' => 'array_values',
      'walk' => 'array_walk'
    ],
    'char' => [
      'toHex' => null
    ],
    'int' => [
      'abs' => 'abs',
      'acos' => 'acos',
      'asin' => 'asin',
      'atan' => 'atan',
      'cos' => 'cos',
      'exp' => 'exp',
      'log' => 'log',
      'pow' => 'pow',
      'sin' => 'sin',
      'sqrt' => 'sqrt',
      'tan' => 'tan',
      'toBinary' => 'decbin',
      'toHex' => 'dechex',
      'toOctal' => 'decoct'
    ],
    'double' => [
      'abs' => 'abs',
      'acos' => 'acos',
      'asin' => 'asin',
      'atan' => 'atan',
      'cos' => 'cos',
      'exp' => 'exp',
      'log' => 'log',
      'pow' => 'pow',
      'sin' => 'sin',
      'sqrt' => 'sqrt',
      'tan' => 'tan',
      'toBinary' => 'decbin',
      'toHex' => 'dechex',
      'toOctal' => 'decoct'
    ],
    'string' => [
      'camelize' => 'camelize',
      'compare' => 'strcmp',
      'compareLocale' => 'strcoll',
      'format' => 'sprintf',
      'htmlSpecialChars' => 'htmlspecialchars',
      'index' => 'strpos',
      'length' => 'strlen',
      'lower' => 'strtolower',
      'lowerFirst' => 'lcfirst',
      'md5' => 'md5',
      'nl2br' => 'nl2br',
      'parseCsv' => 'str_getcsv',
      'parseJson' => 'json_decode',
      'repeat' => 'str_repeat',
      'rev' => 'strrev',
      'sha1' => 'sha1',
      'shuffle' => 'str_shuffle',
      'split' => 'str_split',
      'toJson' => 'json_encode',
      'toutf8' => 'utf8_encode',
      'trim' => 'trim',
      'trimLeft' => 'ltrim',
      'trimRight' => 'rtrim',
      'uncamelize' => 'uncamelize',
      'upper' => 'strtoupper',
      'upperFirst' => 'ucfirst'
    ]
  ];

  /**
   * Process the AST
   * 
   * @param array $ast AST to be processed
   * @return array Old or Transformed AST
   */
  public function top($ast) {
    switch ($ast['type']) {
      case 'function':
        $class = null;
        $function = null;
        list($prepend, $ast, $append) = $this->_processStatement($class, $function, $ast);
        break;
    }
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
    // Process Visibility
    $visibility = [];
    foreach ($method['visibility'] as $entry) {
      // TODO: Verify if Parser Garauntee trim (so that we can ignore this here)
      $entry = trim($entry);
      switch ($entry) {
        case 'public':
        case 'protected':
        case 'private':
          break;
        case 'internal':
          $entry = 'private';
          break;
        default:
          $entry = null;
      }

      if (isset($entry)) {
        $visibility[] = $entry;
      }
    }

    // Is the visibility set?
    if (count($visibility) === 0) { // NO: Use a default of public
      $visibility[] = 'public';
    } else if (count($visibility) > 1) {
      $visibility[] = array_unique($entry);
      if (count($visibility) > 1) {
        throw new Exception("Method visibility is unclear");
      }
    }

    $method['visibility'] = $visibility;
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
        $statements[] = $current;
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

    // Do we have Specific Handler?
    $handler = $this->_handlerName("_statement", ucfirst($type));
    if (method_exists($this, $handler)) { // YES: Use it!
      return $this->$handler($class, $method, $statement);
    } else { // NO: Try Default
      $handler = '_statementDEFAULT';
    }

    // Do we have a Default Handler?
    if (method_exists($this, $handler)) { // YES: Use it!
      return $this->$handler($class, $method, $statement);
    } else { // NO: Aborts
      throw new \Exception("Unhandled statement type [{$type}] in line [{$statement['line']}]");
    }
  }

  protected function _statementFunction(&$class, &$method, $function) {
    /* FUNCTION (STATEMENTS) */
    $function['statements'] = $this->_processStatementBlock($class, $method, $function['statements']);

    return [null, $function, null];
  }

  protected function _statementLoop(&$class, &$method, $loop) {
    $before = [];
    $after = [];

    /* LOOP (STATEMENTS) */
    $loop['statements'] = $this->_processStatementBlock($class, $method, $loop['statements']);

    return [$before, $loop, $after];
  }

  protected function _statementDoWhile(&$class, &$method, $dowhile) {
    $before = [];
    $after = [];

    /* DO-WHILE (STATEMENTS) */
    $dowhile['statements'] = $this->_processStatementBlock($class, $method, $dowhile['statements']);

    /* DO-WHILE (EXPR) */
    list($prepend, $expression, $append) = $this->_processExpression($class, $method, $dowhile['expr']);
    if (isset($prepend) && count($prepend)) {
      $before = array_merge($before, $prepend);
    }
    $dowhile['expr'] = $expression;
    if (isset($append) && count($append)) {
      $after = array_merge($after, $append);
    }

    return [$before, $dowhile, $after];
  }

  protected function _statementWhile(&$class, &$method, $while) {
    $before = [];
    $after = [];

    /* WHILE (EXPR) */
    list($prepend, $expression, $append) = $this->_processExpression($class, $method, $while['expr']);
    if (isset($prepend) && count($prepend)) {
      $before = array_merge($before, $prepend);
    }
    $while['expr'] = $expression;
    if (isset($append) && count($append)) {
      $after = array_merge($after, $append);
    }

    /* WHILE (STATEMENTS) */
    $while['statements'] = $this->_processStatementBlock($class, $method, $while['statements']);

    return [$before, $while, $after];
  }

  protected function _statementFor(&$class, &$method, $statement) {
    $before = [];
    $after = [];

    /* FOR (EXPR) */
    list($prepend, $expression, $append) = $this->_processExpression($class, $method, $statement['expr']);
    if (isset($prepend) && count($prepend)) {
      $before = array_merge($before, $prepend);
    }
    if (isset($append) && count($append)) {
      $after = array_merge($after, $append);
    }

    if (isset($statement['reverse']) && $statement['reverse']) {
      /* TODO: Is it possible in zephir to do a foreach over a string?
       * If so, then we have to handle the case in which the expression is a
       * string and use strrev() instead of array_reverse
       */
      $expression = [
        'type' => 'fcall',
        'name' => 'array_reverse',
        'call-type' => 1,
        'parameters' => [$expression],
        'file' => $expression['file'],
        'line' => $expression['line'],
        'char' => $expression['char']
      ];
      unset($statement['reverse']);
    }
    $statement['expr'] = $expression;

    /* FOR (STATEMENTS) */
    $statement['statements'] = $this->_processStatementBlock($class, $method, $statement['statements']);

    return [$before, $statement, $after];
  }

  protected function _statementFetch(&$class, &$method, $fetch) {
    // Convert fetch statement to if statement and process that
    $if = [
      'type' => 'if',
      'expr' => $fetch['expr'],
      'file' => $fetch['file'],
      'line' => $fetch['line'],
      'char' => $fetch['char'],
    ];

    /* TODO
     * This solution works, BUT, it also should be possible to solve this
     * as terniary statement instead...
     * Verify which solution is best:
     * example code: phalcon->http/request.zep
     * fetch address, _SERVER["HTTP_X_FORWARDED_FOR"];
		 * if address === null {
		 *	  	fetch address, _SERVER["HTTP_CLIENT_IP"];
		 * }
     * 
     * doing: address = isset _SERVER["HTTP_X_FORWARDED_FOR"] ? _SERVER["HTTP_X_FORWARDED_FOR"] : null;
     * should also work!?
     */
    return $this->_statementIf($class, $method, $if);
  }

  protected function _statementIf(&$class, &$method, $statement) {
    $before = [];
    $after = [];

    if (!isset($statement['statements'])) {
      $statement['statements'] = [];
    }

    /* IF (EXPR) */
    $expression = $statement['expr'];
    // Are we Dealing with Fetch Expression
    if ($expression['type'] === 'fetch') { // YES
      /* FETCH STATEMENTS ARE PROCESSED in 2 STAGES
       * 1. An Assignment Statement is added to statement block
       * 2. Fetch is replaced by an isset() test...
       */
      // Create Assignment Statement
      $let = [
        'type' => 'let',
        'assignments' =>
        [
          [
            'assign-type' => 'variable',
            'operator' => 'assign',
            // EXPECTED = $expression['left']['type'] === 'variable'!??
            'variable' => $expression['left']['value'],
            'expr' => $expression['right'],
            'file' => $expression['file'],
            'line' => $expression['line'],
            'char' => $expression['char'],
          ],
        ],
        'file' => $expression['file'],
        'line' => $expression['line'],
        'char' => $expression['char'],
      ];

      if (count($statement['statements']) >= 1) {
        array_unshift($statement['statements'], $let);
      } else {
        $statement['statements'][] = $let;
      }
    }

    list($prepend, $expression, $append) = $this->_processExpression($class, $method, $statement['expr']);
    if (isset($prepend) && count($prepend)) {
      $before = array_merge($before, $prepend);
    }
    $statement['expr'] = $expression;
    if (isset($append) && count($append)) {
      $after = array_merge($after, $append);
    }

    /* IF (STATEMENTS) */
    $statement['statements'] = $this->_processStatementBlock($class, $method, $statement['statements']);

    /* ELSE IF */
    if (isset($statement['elseif_statements'])) {
      $statement['elseif_statements'] = $this->_processStatementBlock($class, $method, $statement['elseif_statements']);
    }

    /* ELSE */
    if (isset($statement['else_statements'])) {
      $statement['else_statements'] = $this->_processStatementBlock($class, $method, $statement['else_statements']);
    }

    return [$before, $statement, $after];
  }

  protected function _statementSwitch(&$class, &$method, $switch) {
    $before = [];
    $after = [];
    $assignments = [];

    /* SWITCH (EXPR) */
    list($prepend, $expression, $append) = $this->_processExpression($class, $method, $switch['expr']);
    if (isset($prepend) && count($prepend)) {
      $before = array_merge($before, $prepend);
    }
    $switch['expr'] = $expression;
    if (isset($append) && count($append)) {
      $after = array_merge($after, $append);
    }

    /* SWITCH (CLAUSES) */
    if (isset($switch['clauses'])) {
      foreach ($switch['clauses'] as &$clause) {
        $clause['statements'] = $this->_processStatementBlock($class, $method, $clause['statements']);
      }
    } else {
      $switch['clauses'] = [];
    }

    return [$before, $switch, $after];
  }

  protected function _statementTryCatch(&$class, &$method, $trycatch) {
    $before = [];
    $after = [];
    $assignments = [];

    /* TRY { } */
    if (isset($trycatch['statements'])) {
      $trycatch['statements'] = $this->_processStatementBlock($class, $method, $trycatch['statements']);
    } else {
      $trycatch['statements'] = [];
    }

    /* CATCH (CLAUSES) */
    if (isset($trycatch['catches'])) {
      $catches = [];
      foreach ($trycatch['catches'] as $catch) {
        // Cleanup Catch Statements
        $statements = isset($catch['statements']) ? $this->_processStatementBlock($class, $method, $catch['statements']) : [];

        //Do we already have a catch variable?
        if (isset($catch['variable'])) { // YES: Save it as Method Local
          $variable = $catch['variable'];
          $this->_addLocalVariable($class, $method, $variable);
          $tv_name = $variable['value'];
        } else { // NO: Create a New Method Local Variable
          $tv_name = $this->_newLocalVariable($class, $method, 'variable', $catch['file'], $catch['line'], $catch['char']);
        }

        // Decouple Multiple Classes into Single Class Catch Clause
        foreach ($catch['classes'] as $catch_class) {
          $new_catch = [
            'class' => $catch_class,
            'variable' =>
            [
              'type' => 'variable',
              'value' => $tv_name,
              'file' => $catch['file'],
              'line' => $catch['line'],
              'char' => $catch['char'],
            ],
            'statements' => $statements
          ];

          $catches[] = $new_catch;
        }
      }

      $trycatch['catches'] = $catches;
    } else { // NO: Create an Empty Catch Statement
      $tv_name = $this->_newLocalVariable($class, $method, 'variable', $trycatch['file'], $trycatch['line'], $trycatch['char']);
      $trycatch['catches'] = [
        [
          'class' => [
            'type' => 'variable',
            'value' => '\Exception',
            'file' => $trycatch['file'],
            'line' => $trycatch['line'],
            'char' => $trycatch['char'],
          ],
          'variable' =>
          [
            'type' => 'variable',
            'value' => $tv_name,
            'file' => $trycatch['file'],
            'line' => $trycatch['line'],
            'char' => $trycatch['char'],
          ],
          'statements' => []
        ]
      ];
    }

    return [$before, $trycatch, $after];
  }

  protected function _statementLet(&$class, &$method, $let) {
    $before = [];
    $after = [];
    $assignments = [];

    foreach ($let['assignments'] as $assignment) {
      switch ($assignment['assign-type']) {
        case "object-property-incr":
          $assignment['type'] = 'incr';
          $assignment['assign-to-type'] = 'object-property';
          unset($assignment['assign-type']);
          break;
        case "object-property-decr":
          $assignment['type'] = 'decr';
          $assignment['assign-to-type'] = 'object-property';
          unset($assignment['assign-type']);
          break;
        case 'incr':
        case 'decr':
          $assignment['assign-to-type'] = 'variable';
          /* Convert i++ and i-- to actual statements, rather than use them a sub-type of assignment
           * The idea being that, an assignment has a LHS and a RHS
           */
          $assignment['type'] = $assignment['assign-type'];
          unset($assignment['assign-type']);
          break;
        case 'string-dynamic-object-property': // ex: let this->{"test"} = "works";
          // Step 1: Create a Local Variable
          $file = $assignment['file'];
          $line = $assignment['line'];
          $char = $assignment['char'];
          $tv_name = $this->_newLocalVariable($class, $method, 'string', $file, $line, $char);

          // Step 2: Assign Value to New Local
          $before[] = [
            'type' => 'assign',
            'operator' => 'assign',
            'assign-type' => 'variable',
            'assign-to-type' => 'variable',
            'variable' => $tv_name,
            'expr' => [
              'type' => 'string',
              'value' => $assignment['property'],
              'file' => $file,
              'line' => $line,
              'char' => $char
            ],
            'file' => $file,
            'line' => $line,
            'char' => $char
          ];

          // Step 3: Modify Original Assignment (to use new local variable)
          $assignment['assign-type'] = 'variable-dynamic-object-property';
          $assignment['property'] = $tv_name;
        default:
          $assignment['type'] = 'assign';
          $assignment['assign-to-type'] = $assignment['assign-type'];
      }
      list($prepend, $assignment, $append) = $this->_processStatement($class, $method, $assignment);
      if (isset($prepend) && count($prepend)) {
        $before = array_merge($before, $prepend);
      }
      $assignments[] = $assignment;
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
    // Are we dealing with an empty return (i.e. return;)?
    if (isset($return['expr'])) { // NO
      list($before, $expression, $after) = $this->_processExpression($class, $method, $return['expr']);
      $return['expr'] = $expression;
      return [$before, $return, $after];
    }
    return [null, $return, null];
  }

  protected function _statementMcall(&$class, &$method, $statement) {
    list($before, $expression, $after) = $this->_processExpression($class, $method, $statement['expr']);
    if ($expression['type'] === 'fcall') {
      $statement['type'] = 'fcall';
    }
    $statement['expr'] = $expression;
    return [$before, $statement, $after];
  }

  protected function _statementFcall(&$class, &$method, $statement) {
    list($before, $expression, $after) = $this->_processExpression($class, $method, $statement['expr']);
    $statement['expr'] = $expression;
    return [$before, $statement, $after];
  }

  protected function _statementEcho(&$class, &$method, $echo) {
    $before = [];
    $expressions = [];
    $after = [];

    // For a function call, we have to check if the parameters use sudo objects
    foreach ($echo['expressions'] as $expression) {
      list($prepend, $expression, $append) = $this->_processExpression($class, $method, $expression);
      if (isset($prepend) && count($prepend)) {
        $before = array_merge($before, $prepend);
      }
      $expressions[] = $expression;
      if (isset($append) && count($append)) {
        $after = array_merge($after, $append);
      }
    }
    $echo['expressions'] = $expressions;

    return [$before, $echo, $after];
  }

  protected function _statementDEFAULT(&$class, &$method, $statement) {
    $before = [];
    $after = [];

    if (isset($statement['expr'])) {
      list($before, $expression, $after) = $this->_processExpression($class, $method, $statement['expr']);
      $statement['expr'] = $expression;
    }

    return [$before, $statement, $after];
  }

  protected function _processExpression(&$class, &$method, $expression) {
    if (!isset($expression['type'])) {
      throw new \Exception('Invalid Expression');
    }
    $type = $expression['type'];

    // Do we have Specific Handler?
    $handler = $this->_handlerName("_expression", ucfirst($type));
    if (method_exists($this, $handler)) { // YES: Use it!
      return $this->$handler($class, $method, $expression);
    } else { // NO: Try Default
      $handler = '_expressionDEFAULT';
    }

    // Do we have a Default Handler?
    if (method_exists($this, $handler)) { // YES: Use it!
      return $this->$handler($class, $method, $expression);
    } else { // NO: Aborts
      throw new \Exception("Unhandled expression type [{$type}] in line [{$expression['line']}]");
    }

    /* TODO Implement Post Processing of Expressions
     * Idea: where normally processing performs expansion (i.e. convert sudoobject
     * method calls, to actual function calls). Post processing would perform
     * compression (i.e. if a List only has a single parameter, than it might
     * be better, for future processing, if we replace the list with it's
     * parameter value:
     * 
     * Example scenario: (from range.zep)
     * 		return (0...10)->join('-'); 
     * 
     * this requires that the sudo object mcall, recognize the list, and
     * try to extract it's parameters, to see if it applies.
     */
  }

  protected function _expressionSCall(&$class, &$method, $scall) {
    $before = [];
    $parameters = [];
    $after = [];

    // Do we have Parameters for the Call?
    if (isset($scall['parameters'])) { // YES
      // For a function call, we have to check if the parameters use sudo objects
      foreach ($scall['parameters'] as $parameter) {
        list($prepend, $parameter, $append) = $this->_processExpression($class, $method, $parameter['parameter']);
        if (isset($prepend) && count($prepend)) {
          $before = array_merge($before, $prepend);
        }
        $parameters[] = $parameter;
        if (isset($append) && count($append)) {
          $after = array_merge($after, $append);
        }
      }
    }
    $scall['parameters'] = $parameters;

    return [$before, $scall, $after];
  }

  protected function _expressionFCall(&$class, &$method, $fcall) {
    $before = [];
    $parameters = [];
    $after = [];

    // Do we have Parameters for the Call?
    if (isset($fcall['parameters'])) { // YES
      // For a function call, we have to check if the parameters use sudo objects
      foreach ($fcall['parameters'] as $parameter) {
        list($prepend, $parameter, $append) = $this->_processExpression($class, $method, $parameter['parameter']);
        if (isset($prepend) && count($prepend)) {
          $before = array_merge($before, $prepend);
        }
        $parameters[] = $parameter;
        if (isset($append) && count($append)) {
          $after = array_merge($after, $append);
        }
      }
    }
    $fcall['parameters'] = $parameters;

    return [$before, $fcall, $after];
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
        $parameters[] = $parameter;
        if (isset($append) && count($append)) {
          $after = array_merge($after, $append);
        }
      }
    }
    // NOTE: GARAUNTEES THAT if MCALL has no PARAMETERS, an EMPTY ARRAY IS USED to SHOW THAT
    $expression['parameters'] = $parameters;

    // STEP 2: Determine if we are dealing with a sudo object call
    $sudoobject = null;

    // Need to make sure that we expand possible values, before using them
    $variable = $expression['variable'];
    list($prepend, $variable, $append) = $this->_processExpression($class, $method, $variable);
    if (isset($prepend) && count($prepend)) {
      $before = array_merge($before, $prepend);
    }
    $expression['variable'] = $variable;
    if (isset($append) && count($append)) {
      $after = array_merge($after, $append);
    }

    switch ($variable['type']) {
      case 'array':
      case 'char':
      case 'int':
      case 'double':
      case 'string':
        $sudoobject = $variable['type'];
        break;
      case 'variable':
        $definition = $this->_lookup($class, $method, $variable);
        if (isset($definition) && isset($definition['data-type'])) {
          switch ($definition['data-type']) {
            case 'array':
            case 'char':
            case 'int':
            case 'double':
            case 'string':
              $sudoobject = $definition['data-type'];
              break;
          }
        }
    }

    // STEP 3: Handle Sudo Object Calls
    // Are we dealing with a sudo object?
    if (isset($sudoobject)) { // YES
      if ($this->_isValidSudoObjectFunction($sudoobject, $expression['name'])) {
        $handler = $this->_handlerName('_expand', [ $sudoobject, $expression['name']]);
        if (method_exists($this, $handler)) {
          list($prepend, $expression, $append) = $this->$handler($class, $method, $expression);
        } else {
          $handler = $this->_handlerName('_expand', [$sudoobject, 'default']);
          if (method_exists($this, $handler)) {
            list($prepend, $expression, $append) = $this->$handler($class, $method, $expression['name'], $expression);
          } else {
            $handler = $this->_handlerName('_expand', 'default');
            if (!method_exists($this, $handler)) {
              throw new \Exception("Missing Handler function [{$expression['name']}] for [{$sudoobject}] object type.");
            }
            list($prepend, $expression, $append) = $this->$handler($class, $method, $sudoobject, $expression['name'], $expression);
          }
        }

        if (isset($prepend) && count($prepend)) {
          $before = array_merge($before, $prepend);
        }
        if (isset($append) && count($append)) {
          $after = array_merge($after, $append);
        }
      } else {
        throw new \Exception("Function [{$expression['name']}] is not valid for an [{$sudoobject}] object.");
      }
    }

    return [$before, $expression, $after];
  }

  protected function _expressionNew(&$class, &$method, $new) {
    $before = [];
    $parameters = [];
    $after = [];

    // Do we have Parameters for the new?
    if (isset($new['parameters'])) { // YES
      // For a function call, we have to check if the parameters use sudo objects
      foreach ($new['parameters'] as $parameter) {
        list($prepend, $parameter, $append) = $this->_processExpression($class, $method, $parameter['parameter']);
        if (isset($prepend) && count($prepend)) {
          $before = array_merge($before, $prepend);
        }
        $parameters[] = $parameter;
        if (isset($append) && count($append)) {
          $after = array_merge($after, $append);
        }
      }
    }
    $new['parameters'] = $parameters;

    return [$before, $new, $after];
  }

  protected function _expressionNewType(&$class, &$method, $newtype) {

    // Transform New Type Expression
    switch ($newtype['internal-type']) {
      case 'array':
        $expression = [
          'type' => 'empty-array',
          'file' => $newtype['file'],
          'line' => $newtype['line'],
          'char' => $newtype['char']
        ];
        break;
      case 'string':
        $expression = [
          'type' => 'string',
          'value' => '',
          'file' => $newtype['file'],
          'line' => $newtype['line'],
          'char' => $newtype['char']
        ];
        break;
      default:
        throw new \Exception("Unhandled new type [{$newtype['internal-type']}] in line [{$newtype['line']}]");
    }

    return [null, $expression, null];
  }

  protected function _expressionTypeHint(&$class, &$method, $typehint) {
    $before = [];
    $after = [];

    /* TODO Use Type Hints. How?
     * 1. As the expected return type of the expression (associate the type with the expression
     * so that it get perculated up the expression tree). This would allow the compiler
     * to do further type checking AND sudo-object method expansion.
     * 2. For debug purposes, we could add asserts to verify that the returned
     * value is actually of the type expected...
     */
    // Unlikely doesn't apply to PHP (so just remove it)
    list($prepend, $typehint, $append) = $this->_processExpression($class, $method, $typehint['right']);
    return [$before, $typehint, $after];
  }

  protected function _expressionUnlikely(&$class, &$method, $unlikely) {
    $before = [];
    $after = [];

    // Unlikely doesn't apply to PHP (so just remove it)
    list($prepend, $unlikely, $append) = $this->_processExpression($class, $method, $unlikely['left']);
    return [$before, $unlikely, $after];
  }

  protected function _expressionLikely(&$class, &$method, $likely) {
    $before = [];
    $after = [];

    // Likely doesn't apply to PHP (so just remove it)
    list($prepend, $likely, $append) = $this->_processExpression($class, $method, $likely['left']);
    return [$before, $likely, $after];
  }

  protected function _expressionClosure(&$class, &$method, $closure) {
    $before = [];
    $after = [];

    // Transform AST to a more standard function definition AST
    $closure['parameters'] = [];
    $closure['statements'] = [];

    // 'left' expression represents parameters
    if (isset($closure['left'])) {
      $closure['parameters'] = $closure['left'];
      unset($closure['left']);
    }

    // 'right' expression statements
    if (isset($closure['right'])) {
      $closure['statements'] = $this->_processStatementBlock($class, $closure, $closure['right']);
      unset($closure['right']);
    }

    return [null, $closure, null];
  }

  protected function _expressionClosureArrow(&$class, &$method, $expression) {
    $closure = [
      'type' => 'closure',
      'call-type' => 1,
      'parameters' => [$expression['left']],
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

  protected function _expressionFetch(&$class, &$method, $fetch) {
    // Replace Fetch with isset(....)
    $expression = [
      'type' => 'isset',
      'left' => $fetch['right'],
      'file' => $fetch['file'],
      'line' => $fetch['line'],
      'char' => $fetch['char'],
    ];

    return [null, $expression, null];
  }

  protected function _expressionEmpty(&$class, &$method, $empty) {
    $before = [];
    $after = [];

    /* EMPTY (EXPR) */
    list($prepend, $expression, $append) = $this->_processExpression($class, $method, $empty['left']);
    if (isset($prepend) && count($prepend)) {
      $before = array_merge($before, $prepend);
    }
    if (isset($append) && count($append)) {
      $after = array_merge($after, $append);
    }

    // Convert empty(...) to a built-in function call
    $function = [
      'type' => 'fcall',
      'name' => 'zephir_isempty',
      'call-type' => 1,
      'parameters' => [$expression],
      'file' => $expression['file'],
      'line' => $expression['line'],
      'char' => $expression['char']
    ];
    return [$before, $function, $after];
  }

  protected function _expressionList(&$class, &$method, $list) {
    $before = [];
    $after = [];

    // Compact List Expression
    $left = $list['left'];
    if (isset($left['type'])) {
      // Are we dealing with concat operation?
      if ($left['type'] !== 'concat') {
        list($prepend, $list, $append) = $this->_processExpression($class, $method, $left);
      }
      /* TODO: Improve Handling of Expression and Return types so as to be
       * able to apply sudo-object methods globally
       * ex: stringmethods.zep -> return ("hello" . "hello")->length();
       */
    } else {
      throw new \Exception("Unexpected List Expression in line [{$assign['line']}]");
    }

    return [$before, $list, $after];
  }

  protected function _expressionIrange(&$class, &$method, $irange) {
    $before = [];
    $after = [];

    // Process Left
    list($prepend, $left, $append) = $this->_processExpression($class, $closure, $irange['left']);
    if (isset($prepend) && count($prepend)) {
      $before = array_merge($before, $prepend);
    }
    if (isset($append) && count($append)) {
      $after = array_merge($after, $append);
    }

    // Process Right
    list($prepend, $right, $append) = $this->_processExpression($class, $closure, $irange['right']);
    if (isset($prepend) && count($prepend)) {
      $before = array_merge($before, $prepend);
    }
    if (isset($append) && count($append)) {
      $after = array_merge($after, $append);
    }

    /* TODO
     * Add Type Hint (stating that the return result is an array, so that
     * it can be combiner with an array sudo method call)
     */

    /* MAP AST to equivalent PHP function call
     * range($irange['left'], $irange['right']) 
     */
    $function = [
      'type' => 'fcall',
      'name' => 'range',
      'call-type' => 1,
      'parameters' => [$left, $right],
      'file' => $irange['file'],
      'line' => $irange['line'],
      'char' => $irange['char']
    ];

    return [$before, $function, $after];
  }

  protected function _expressionErange(&$class, &$method, $erange) {
    /* TODO
     * Add Type Hint (stating that the return result is an array, so that
     * it can be combiner with an array sudo method call)
     */

    /* TODO consider using a single custom function to handle erange
     * Benefits:
     * 1. More extensive type checking and handling of empty ranges.
     */

    /* MAP AST to equivalent PHP function calls
     * array_shift(array_pop(range($erange['left'], $erange['right'])))
     */
    list($before, $range, $after) = $this->_expressionIrange($class, $method, $erange);
    $array_pop = [
      'type' => 'fcall',
      'name' => 'array_pop',
      'call-type' => 1,
      'parameters' => [$range],
      'file' => $erange['file'],
      'line' => $erange['line'],
      'char' => $erange['char']
    ];
    $array_shift = [
      'type' => 'fcall',
      'name' => 'array_shift',
      'call-type' => 1,
      'parameters' => [$array_pop],
      'file' => $erange['file'],
      'line' => $erange['line'],
      'char' => $erange['char']
    ];

    return [$before, $array_shift, $after];
  }

  protected function _expressionArray(&$class, &$method, $expression) {
    $before = [];
    $after = [];
    $entries = [];

    foreach ($expression['left'] as $entry) {
      list($prepend, $value, $append) = $this->_processExpression($class, $method, $entry['value']);
      if (isset($prepend) && count($prepend)) {
        $before = array_merge($before, $prepend);
      }
      $entry['value'] = $value;
      $entries[] = $entry;
      if (isset($append) && count($append)) {
        $after = array_merge($after, $append);
      }
    }
    $expression['left'] = $entries;

    return [$before, $expression, $after];
  }

  protected function _expressionPropertyStringAccess(&$class, &$method, $expression) {
    $before = [];
    $after = [];

    // Step 1: Create Local Variable and Assignment Statement
    $right = $expression['right'];
    $tv_name = $this->_newLocalVariable($class, $method, $right['type'], $right['file'], $right['line'], $right['char']);
    $tv_assignment = $this->_newAssignment($class, $method, $tv_name, $right);
    $before[] = $tv_assignment;

    // Step 2: Convert Property String Access to Property Dynamic Access
    $expression['type'] = 'property-dynamic-access';
    $expression['right'] = [
      'type' => 'variable',
      'value' => $tv_name,
      'file' => $expression['right']['file'],
      'line' => $expression['right']['line'],
      'char' => $expression['right']['char']
    ];

    return [$before, $expression, $after];
  }

  protected function _newLocalVariable(&$class, &$method, $datatype, $file, $line, $char) {
    // Can we handle the Variable Type
    switch ($datatype) {
      case 'array':
        $v_prefix = '__t_a_';
        break;
      case 'bool':
        $v_prefix = '__t_b_';
        break;
      case 'double':
        $v_prefix = '__t_d_';
        break;
      case 'int':
        $v_prefix = '__t_i_';
        break;
      case 'char':
        $v_prefix = '__t_c_';
        break;
      case 'string':
        $v_prefix = '__t_s_';
        break;
      case 'variable':
        $v_prefix = '__t_v_';
        break;
      default:
        throw new \Exception("Can't Create Local Variable of type [{$datatype}] in line [{$line}].");
    }

    // Find a Valid Local Variable Name
    $i = 1;
    $locals = $method['locals'];
    do {
      $v_name = "{$v_prefix}{$i}";
      if (!array_key_exists($v_name, $locals)) {
        break;
      }
      $i++;
    } while (true);

    // Add Variable to Method Locals
    $this->_addLocalVariable($class, $method, [
      'value' => $v_name,
      'type' => $datatype,
      'file' => $file,
      'line' => $line,
      'char' => $char
    ]);

    return $v_name;
  }

  protected function _addLocalVariable(&$class, &$method, $variable) {
    $v_name = $variable['value'];

    // Add Variable to Method Locals
    $method['locals'][$v_name] = $variable;
  }

  protected function _newAssignment($class, $method, $v_name, $expression) {
    // Create Assignment Statement
    $assignment = [
      'type' => 'assign',
      'operator' => 'assign',
      'assign-type' => 'variable',
      'assign-to-type' => 'variable',
      'variable' => $v_name,
      'expr' => $expression,
      'file' => $expression['file'],
      'line' => $expression['line'],
      'char' => $expression['char']
    ];

    return $assignment;
  }

  protected function _expressionCast(&$class, &$method, $cast) {
    $before = [];
    $after = [];

    /* in the Cast Expression, the LHS (i.e. $cast['left'] is just a string */

    // Process Right Expression
    list($prepend, $right, $append) = $this->_processExpression($class, $method, $cast['right']);
    if (isset($prepend) && count($prepend)) {
      $before = array_merge($before, $prepend);
    }
    $cast['right'] = $right;
    if (isset($append) && count($append)) {
      $after = array_merge($after, $append);
    }

    return [$before, $cast, $after];
  }

  protected function _expressionTernary(&$class, &$method, $ternary) {
    $before = [];
    $after = [];

    /* in the Cast Expression, the LHS (i.e. $cast['left'] is just a string */

    // Process Left Expression
    list($prepend, $left, $append) = $this->_processExpression($class, $method, $ternary['left']);
    if (isset($prepend) && count($prepend)) {
      $before = array_merge($before, $prepend);
    }
    $ternary['left'] = $left;
    if (isset($append) && count($append)) {
      $after = array_merge($after, $append);
    }

    // Process Right Expression
    list($prepend, $right, $append) = $this->_processExpression($class, $method, $ternary['right']);
    if (isset($prepend) && count($prepend)) {
      $before = array_merge($before, $prepend);
    }
    $ternary['right'] = $right;
    if (isset($append) && count($append)) {
      $after = array_merge($after, $append);
    }

    // Process Extra Expression
    list($prepend, $extra, $append) = $this->_processExpression($class, $method, $ternary['extra']);
    if (isset($prepend) && count($prepend)) {
      $before = array_merge($before, $prepend);
    }
    $ternary['extra'] = $extra;
    if (isset($append) && count($append)) {
      $after = array_merge($after, $append);
    }

    return [$before, $ternary, $after];
  }

  protected function _expressionIstring(&$class, &$method, $istring) {
    /* TODO figure out what an 'istring' is
     * example: (strings.zep) return ~"hello";
     */

    // For now, simply treat as a string
    $istring['type'] = 'string';
    return [null, $istring, null];
  }

  protected function _expressionDEFAULT(&$class, &$method, $expression) {
    $before = [];
    $after = [];

    // Does the Expression have a 'left' expression?
    if (isset($expression['left'])) { // YES: Normalize Left Expression
      // Process Left Expression
      list($prepend, $left, $append) = $this->_processExpression($class, $method, $expression['left']);
      if (isset($prepend) && count($prepend)) {
        $before = array_merge($before, $prepend);
      }
      $expression['left'] = $left;
      if (isset($append) && count($append)) {
        $after = array_merge($after, $append);
      }
    }

    // Does the Expression have a 'right' expression?
    if (isset($expression['right'])) { // YES: Normalize Right Expression
      // Process Right Expression
      list($prepend, $right, $append) = $this->_processExpression($class, $method, $expression['right']);
      if (isset($prepend) && count($prepend)) {
        $before = array_merge($before, $prepend);
      }
      $expression['right'] = $right;
      if (isset($append) && count($append)) {
        $after = array_merge($after, $append);
      }
    }

    return [$before, $expression, $after];
  }

  protected function _expandDefault(&$class, &$method, $type, $function, $expression) {
    $php_function = $this->sudo_methods[$type][$function];

    $variable = $expression['variable'];
    $join_parameters = $expression['parameters'];
    if (count($join_parameters) === 0) {
      $parameters = ['parameter' => $variable];
    } else {
      $parameters = array_merge(['parameter' => $variable], $join_parameters);
    }

    // TODO Validate Minimum and Maximum Parameters

    $function = [
      'type' => 'fcall',
      'name' => $php_function,
      'call-type' => 1,
      'parameters' => $parameters,
      'file' => $expression['file'],
      'line' => $expression['line'],
      'char' => $expression['char']
    ];

    return [null, $function, null];
  }

  protected function _expandArrayJoin(&$class, &$method, $expression) {
    $variable = $expression['variable'];
    $join_parameters = $expression['parameters'];

    switch (count($join_parameters)) {
      case 1: // $glue set
        $parameters = $join_parameters;
      case 0: // $glue not set (using default)
        $parameters[] = $variable;
        break;
      case 1:
      default:
        throw new \Exception("Array join() requires 0 or 1 parameter");
    }

    $function = [
      'type' => 'fcall',
      'name' => 'implode',
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
    $parameters = [$join_parameters[0]];
    $parameters[] = $variable;

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

  protected function _expandCharToHex(&$class, &$method, $expression) {
    $variable = $expression['variable'];
    $join_parameters = $expression['parameters'];
    if (count($join_parameters) === 0) {
      $parameters = [
        [
          'type' => 'string',
          'value' => '%X',
          'file' => $expression['file'],
          'line' => $expression['line'],
          'char' => $expression['char']
        ],
        'parameter' => $variable
      ];
    } else {
      throw new \Exception("String length() requires no parameters");
    }

    $function = [
      'type' => 'fcall',
      'name' => 'sprintf',
      'call-type' => 1,
      'parameters' => $parameters,
      'file' => $expression['file'],
      'line' => $expression['line'],
      'char' => $expression['char']
    ];

    return [null, $function, null];
  }

  protected function _expandStringCompare(&$class, &$method, $expression) {
    $variable = $expression['variable'];
    $join_parameters = $expression['parameters'];
    if (count($join_parameters) === 1) {
      $parameters = ['parameter' => $variable, $join_parameters[0]];
    } else {
      throw new \Exception("String compare() requires 1 parameter");
    }

    $function = [
      'type' => 'fcall',
      'name' => 'strcmp',
      'call-type' => 1,
      'parameters' => $parameters,
      'file' => $expression['file'],
      'line' => $expression['line'],
      'char' => $expression['char']
    ];

    return [null, $function, null];
  }

  protected function _expandStringCompareLocale(&$class, &$method, $expression) {
    $variable = $expression['variable'];
    $join_parameters = $expression['parameters'];
    if (count($join_parameters) === 1) {
      $parameters = ['parameter' => $variable, $join_parameters[0]];
    } else {
      throw new \Exception("String compareLocale() requires 1 parameter");
    }

    $function = [
      'type' => 'fcall',
      'name' => 'strcoll',
      'call-type' => 1,
      'parameters' => $parameters,
      'file' => $expression['file'],
      'line' => $expression['line'],
      'char' => $expression['char']
    ];

    return [null, $function, null];
  }

  protected function _expandStringFormat(&$class, &$method, $expression) {
    $variable = $expression['variable'];
    $join_parameters = $expression['parameters'];
    if (count($join_parameters) === 0) {
      $parameters = ['parameter' => $variable];
    } else {
      $parameters = array_merge(['parameter' => $variable], $join_parameters);
    }

    $function = [
      'type' => 'fcall',
      'name' => 'sprintf',
      'call-type' => 1,
      'parameters' => $parameters,
      'file' => $expression['file'],
      'line' => $expression['line'],
      'char' => $expression['char']
    ];

    return [null, $function, null];
  }

  protected function _expandStringHtmlSpecialChars(&$class, &$method, $expression) {
    $variable = $expression['variable'];
    $join_parameters = $expression['parameters'];
    switch (count($join_parameters)) {
      case 0: // $glue not set (using default)
        $parameters = ['parameter' => $variable];
        break;
      case 1:
      case 2:
      case 3:
        $parameters = array_merge(['parameter' => $variable], $join_parameters);
        break;
      default:
        throw new \Exception("String htmlSpecialChars() can't have more than 3 parameters");
    }

    $function = [
      'type' => 'fcall',
      'name' => 'htmlspecialchars',
      'call-type' => 1,
      'parameters' => $parameters,
      'file' => $expression['file'],
      'line' => $expression['line'],
      'char' => $expression['char']
    ];

    return [null, $function, null];
  }

  protected function _expandStringIndex(&$class, &$method, $expression) {
    $variable = $expression['variable'];
    $join_parameters = $expression['parameters'];
    switch (count($join_parameters)) {
      case 1:
      case 2:
        $parameters = array_merge(['parameter' => $variable], $join_parameters);
        break;
      default:
        throw new \Exception("String index() requires between 1 and 2 parameters");
    }

    $function = [
      'type' => 'fcall',
      'name' => 'strpos',
      'call-type' => 1,
      'parameters' => $parameters,
      'file' => $expression['file'],
      'line' => $expression['line'],
      'char' => $expression['char']
    ];

    return [null, $function, null];
  }

  protected function _expandStringLength(&$class, &$method, $expression) {
    $variable = $expression['variable'];
    $join_parameters = $expression['parameters'];
    if (count($join_parameters) === 0) {
      $parameters = ['parameter' => $variable];
    } else {
      throw new \Exception("String length() requires no parameters");
    }

    $function = [
      'type' => 'fcall',
      'name' => 'strlen',
      'call-type' => 1,
      'parameters' => $parameters,
      'file' => $expression['file'],
      'line' => $expression['line'],
      'char' => $expression['char']
    ];

    return [null, $function, null];
  }

  protected function _expandStringLower(&$class, &$method, $expression) {
    $variable = $expression['variable'];
    $join_parameters = $expression['parameters'];
    if (count($join_parameters) === 0) {
      $parameters = ['parameter' => $variable];
    } else {
      throw new \Exception("String lower() requires no parameters");
    }

    $function = [
      'type' => 'fcall',
      'name' => 'strtolower',
      'call-type' => 1,
      'parameters' => $parameters,
      'file' => $expression['file'],
      'line' => $expression['line'],
      'char' => $expression['char']
    ];

    return [null, $function, null];
  }

  protected function _expandStringLowerFirst(&$class, &$method, $expression) {
    $variable = $expression['variable'];
    $join_parameters = $expression['parameters'];
    if (count($join_parameters) === 0) {
      $parameters = ['parameter' => $variable];
    } else {
      throw new \Exception("String lower() requires no parameters");
    }

    $function = [
      'type' => 'fcall',
      'name' => 'lcfirst',
      'call-type' => 1,
      'parameters' => $parameters,
      'file' => $expression['file'],
      'line' => $expression['line'],
      'char' => $expression['char']
    ];

    return [null, $function, null];
  }

  protected function _expandStringMd5(&$class, &$method, $expression) {
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
        throw new \Exception("String md5() requires 0 or 1 parameter");
    }

    $function = [
      'type' => 'fcall',
      'name' => 'md5',
      'call-type' => 1,
      'parameters' => $parameters,
      'file' => $expression['file'],
      'line' => $expression['line'],
      'char' => $expression['char']
    ];

    return [null, $function, null];
  }

  protected function _expandStringNl2br(&$class, &$method, $expression) {
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
        throw new \Exception("String nl2br() requires 0 or 1 parameter");
    }

    $function = [
      'type' => 'fcall',
      'name' => 'nl2br',
      'call-type' => 1,
      'parameters' => $parameters,
      'file' => $expression['file'],
      'line' => $expression['line'],
      'char' => $expression['char']
    ];

    return [null, $function, null];
  }

  protected function _expandStringParseCsv(&$class, &$method, $expression) {
    $variable = $expression['variable'];
    $join_parameters = $expression['parameters'];
    switch (count($join_parameters)) {
      case 0: // $glue not set (using default)
        $parameters = ['parameter' => $variable];
        break;
      case 1:
      case 2:
      case 3:
        $parameters = array_merge(['parameter' => $variable], $join_parameters);
        break;
      default:
        throw new \Exception("String parseCsv() can't have more than 3 parameters");
    }

    $function = [
      'type' => 'fcall',
      'name' => 'str_getcsv',
      'call-type' => 1,
      'parameters' => $parameters,
      'file' => $expression['file'],
      'line' => $expression['line'],
      'char' => $expression['char']
    ];

    return [null, $function, null];
  }

  protected function _expandStringParseJson(&$class, &$method, $expression) {
    $variable = $expression['variable'];
    $join_parameters = $expression['parameters'];
    switch (count($join_parameters)) {
      case 0: // $glue not set (using default)
        $parameters = ['parameter' => $variable];
        break;
      case 1:
      case 2:
      case 3:
        $parameters = array_merge(['parameter' => $variable], $join_parameters);
        break;
      default:
        throw new \Exception("String parseJson() can't have more than 3 parameters");
    }

    $function = [
      'type' => 'fcall',
      'name' => 'json_decode',
      'call-type' => 1,
      'parameters' => $parameters,
      'file' => $expression['file'],
      'line' => $expression['line'],
      'char' => $expression['char']
    ];

    return [null, $function, null];
  }

  protected function _expandStringRepeat(&$class, &$method, $expression) {
    $variable = $expression['variable'];
    $join_parameters = $expression['parameters'];
    if (count($join_parameters) === 1) {
      $parameters = ['parameter' => $variable, $join_parameters[0]];
    } else {
      throw new \Exception("String repeat() requires 1 parameter");
    }

    $function = [
      'type' => 'fcall',
      'name' => 'str_repeat',
      'call-type' => 1,
      'parameters' => $parameters,
      'file' => $expression['file'],
      'line' => $expression['line'],
      'char' => $expression['char']
    ];

    return [null, $function, null];
  }

  protected function _expandStringRev(&$class, &$method, $expression) {
    $variable = $expression['variable'];
    $join_parameters = $expression['parameters'];
    if (count($join_parameters) === 0) {
      $parameters = ['parameter' => $variable];
    } else {
      throw new \Exception("String rev() requires no parameters");
    }

    $function = [
      'type' => 'fcall',
      'name' => 'strrev',
      'call-type' => 1,
      'parameters' => $parameters,
      'file' => $expression['file'],
      'line' => $expression['line'],
      'char' => $expression['char']
    ];

    return [null, $function, null];
  }

  protected function _expandStringSha1(&$class, &$method, $expression) {
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
        throw new \Exception("String sha1() requires 0 or 1 parameter");
    }

    $function = [
      'type' => 'fcall',
      'name' => 'sha1',
      'call-type' => 1,
      'parameters' => $parameters,
      'file' => $expression['file'],
      'line' => $expression['line'],
      'char' => $expression['char']
    ];

    return [null, $function, null];
  }

  protected function _expandStringShuffle(&$class, &$method, $expression) {
    $variable = $expression['variable'];
    $join_parameters = $expression['parameters'];
    if (count($join_parameters) === 0) {
      $parameters = ['parameter' => $variable];
    } else {
      throw new \Exception("String shuffle() requires no parameters");
    }

    $function = [
      'type' => 'fcall',
      'name' => 'str_shuffle',
      'call-type' => 1,
      'parameters' => $parameters,
      'file' => $expression['file'],
      'line' => $expression['line'],
      'char' => $expression['char']
    ];

    return [null, $function, null];
  }

  protected function _expandStringSplit(&$class, &$method, $expression) {
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
        throw new \Exception("String md5() requires 0 or 1 parameter");
    }

    $function = [
      'type' => 'fcall',
      'name' => 'str_split',
      'call-type' => 1,
      'parameters' => $parameters,
      'file' => $expression['file'],
      'line' => $expression['line'],
      'char' => $expression['char']
    ];

    return [null, $function, null];
  }

  protected function _expandStringToJson(&$class, &$method, $expression) {
    $variable = $expression['variable'];
    $join_parameters = $expression['parameters'];
    switch (count($join_parameters)) {
      case 0: // $glue not set (using default)
        $parameters = ['parameter' => $variable];
        break;
      case 1:
      case 2:
        $parameters = array_merge(['parameter' => $variable], $join_parameters);
        break;
      default:
        throw new \Exception("String toJson() can't have more than 2 parameters");
    }

    $function = [
      'type' => 'fcall',
      'name' => 'json_encode',
      'call-type' => 1,
      'parameters' => $parameters,
      'file' => $expression['file'],
      'line' => $expression['line'],
      'char' => $expression['char']
    ];

    return [null, $function, null];
  }

  protected function _expandStringTrim(&$class, &$method, $expression) {
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
        throw new \Exception("String trim() requires 0 or 1 parameter");
    }

    $function = [
      'type' => 'fcall',
      'name' => 'trim',
      'call-type' => 1,
      'parameters' => $parameters,
      'file' => $expression['file'],
      'line' => $expression['line'],
      'char' => $expression['char']
    ];

    return [null, $function, null];
  }

  protected function _expandStringTrimLeft(&$class, &$method, $expression) {
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
        throw new \Exception("String trimLeft() requires 0 or 1 parameter");
    }

    $function = [
      'type' => 'fcall',
      'name' => 'ltrim',
      'call-type' => 1,
      'parameters' => $parameters,
      'file' => $expression['file'],
      'line' => $expression['line'],
      'char' => $expression['char']
    ];

    return [null, $function, null];
  }

  protected function _expandStringTrimRight(&$class, &$method, $expression) {
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
        throw new \Exception("String trimRight() requires 0 or 1 parameter");
    }

    $function = [
      'type' => 'fcall',
      'name' => 'rtrim',
      'call-type' => 1,
      'parameters' => $parameters,
      'file' => $expression['file'],
      'line' => $expression['line'],
      'char' => $expression['char']
    ];

    return [null, $function, null];
  }

  protected function _expandStringUpper(&$class, &$method, $expression) {
    $variable = $expression['variable'];
    $join_parameters = $expression['parameters'];
    if (count($join_parameters) === 0) {
      $parameters = ['parameter' => $variable];
    } else {
      throw new \Exception("String upper() requires no parameters");
    }

    $function = [
      'type' => 'fcall',
      'name' => 'strtoupper',
      'call-type' => 1,
      'parameters' => $parameters,
      'file' => $expression['file'],
      'line' => $expression['line'],
      'char' => $expression['char']
    ];

    return [null, $function, null];
  }

  protected function _expandStringUpperFirst(&$class, &$method, $expression) {
    $variable = $expression['variable'];
    $join_parameters = $expression['parameters'];
    if (count($join_parameters) === 0) {
      $parameters = ['parameter' => $variable];
    } else {
      throw new \Exception("String upper() requires no parameters");
    }

    $function = [
      'type' => 'fcall',
      'name' => 'ucfirst',
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
      return array_key_exists($fname, $this->sudo_methods[$otype]);
    }

    return false;
  }

  protected function _handlerName($prefix, $name) {
    if (is_string($name)) {
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
    } else if (is_array($name)) {
      $name = implode(
        array_map(function($e) {
          return ucfirst(trim($e));
        }, $name));
    }
    return $prefix . $name;
  }

  protected function _lookup($class, $method, $variable) {
    // TODO : Implement Variable Lookup
    /* Handle this;
      if ($variable['value'] !== 'this') {
      }
     */

    $value = null;
    switch ($variable['type']) {
      case 'variable':
        $name = $variable['value'];
        $value = $this->_lookupMethodLocals($method, $name);
        if (!isset($value)) {
          $value = $this->_lookupMethodParameters($method, $name);
        }
        break;
      default:
        throw new \Exception("Unhandled type [{$variable['type']}] in lookup");
    }

    return $value;
  }

  protected function _lookupMethodLocals($method, $name) {
    return isset($method['locals'][$name]) ? $method['locals'][$name] : null;
  }

  protected function _lookupMethodParameters($method, $name) {
    foreach ($method['parameters'] as $parameter) {
      if ($parameter['name'] === $name) {
        return $parameter;
      }
    }
    return null;
  }

}
