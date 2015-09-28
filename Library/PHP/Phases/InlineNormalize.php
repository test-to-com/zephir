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
      'join', // 'implode'
      'reversed', // 'array_reverse'
      'rev', //  'array_reverse'
      'diff', // 'array_diff'
      'flip', // 'array_flip'
      'fill', // 'array_fill'
      'walk', // 'array_walk'
      'haskey', // 'array_key_exists'
      'keys', // 'array_keys'
      'values', // 'array_values'
      'split', // 'array_chunk'
      'combine', // 'array_combine'
      'intersect', // 'array_intersect'
      'merge', // 'array_merge'
      'mergerecursive', // 'array_merge_recursive'
      'pad', // 'array_pad'
      'pop', // 'array_pop'
      'push', // 'array_push'
      'rand', // 'array_rand'
      'replace', // 'array_replace'
      'map', // 'array_map'
      'replacerecursive', // 'array_replace_recursive'
      'shift', // 'array_shift'
      'slice', // 'array_slice'
      'splice', // 'array_splice'
      'sum', // 'array_sum'
      'unique', // 'array_unique'
      'prepend', // 'array_unshift'
      'count', // 'count'
      'current', // 'current'
      'each', // 'each'
      'end', // 'end'
      'key', // 'key'
      'next', // 'next'
      'prev', // 'prev'
      'reset', // 'reset'
      'sort', // 'sort'
      'sortbykey', // 'ksort'
      'reversesort', // 'rsort'
      'reversesortbykey', // 'krsort'
      'shuffle', // 'shuffle'
      'tojson', // 'json_encode'
      'reduce' // array_reduce
    ],
    'string' => [
      'index', // 'strpos'
      'trim', // 'trim'
      'trimleft', // 'ltrim'
      'trimright', // 'rtrim'
      'length', // 'strlen'
      'lower', // 'strtolower'
      'upper', // 'strtoupper'
      'lowerfirst', // 'lcfirst'
      'upperfirst', // 'ucfirst'
      'format', // 'sprintf'
      'md5', // 'md5'
      'sha1', // 'sha1'
      'nl2br', // 'nl2br'
      'parsecsv', // 'str_getcsv'
      'parsejson', // 'json_decode'
      'tojson', // 'json_encode'
      'toutf8', // 'utf8_encode'
      'repeat', // 'str_repeat'
      'shuffle', // 'str_shuffle'
      'split', // 'str_split'
      'compare', // 'strcmp'
      'comparelocale', // 'strcoll'
      'rev', // 'strrev'
      'htmlspecialchars', // 'htmlspecialchars'
      'camelize', // 'camelize'
      'uncamelize' // 'uncamelize'
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

  protected function _statementLoop(&$class, &$method, $statement) {
    throw new \Exception("TODO Implement");
  }

  protected function _statementDoWhile(&$class, &$method, $statement) {
    throw new \Exception("TODO Implement");
  }

  protected function _statementWhile(&$class, &$method, $statement) {
    throw new \Exception("TODO Implement");
  }

  protected function _statementFor(&$class, &$method, $statement) {
    throw new \Exception("TODO Implement");
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

  protected function _statementSwitch(&$class, &$method, $statement) {
    throw new \Exception("TODO Implement");
  }

  protected function _statementLet(&$class, &$method, $let) {
    $before = [];
    $after = [];
    $assignments = [];

    foreach ($let['assignments'] as $assignment) {
      $assignment['type'] = 'assign';
      $assignment['assign-to-type'] = $assignment['assign-type'];
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
    list($before, $expression, $after) = $this->_processExpression($class, $method, $return['expr']);
    $return['expr'] = $expression;
    return [$before, $return, $after];
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
    list($tv_name, $tv_statement) = $this->_newLocalVariable($class, $method, $expression['right']);
    $before[] = $tv_statement;

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

  protected function _newLocalVariable(&$class, &$method, $expression) {
    // Can we handle the Variable Type
    switch ($expression['type']) {
      case 'string':
        $v_prefix = '__t_s_';
        break;
      default:
        throw new \Exception("Can't Create Local Variable of type [{$expression['type']}] in line [{$expression['line']}].");
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
    
    // Add Variable to Method Locals
    $method['locals'][$v_name] = [
      'name' => $v_name,
      'data-type' => $expression['type'],
      'file' => $expression['file'],
      'line' => $expression['line'],
      'char' => $expression['char']
    ];
      
    return [$v_name, $assignment];
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
