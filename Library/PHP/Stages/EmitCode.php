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

namespace Zephir\PHP\Stages;

use Zephir\Common\Stage as IStage;

/**
 * Normalizes the IR Ast to make for easier parsing
 * 
 * @author Paulo Ferreira <pf at sourcenotes.org>
 */
class EmitCode implements IStage {

  // Mixins
  use \Zephir\Common\Mixins\DI;

  // TODO: Maybe Create a Compile Context Object so as to simplify handlers
  // Processing a Right Expression (i.e. for variables this means dropping the '$');
  protected $_property = false;
  // Set Interface Mode (i.e. when emiting methods, emit only a method declaration
  protected $_interface = false;
  // Current Emitter
  protected $_emitter = null;

  /**
   * Initialize the Stage Instance
   * 
   * @return self Return instance of stage for Function Linking.
   */
  public function initialize() {
    $this->_emitter = $this->getDI()->getShared('emitter');
    return $this;
  }

  /**
   * Reset the Stage Instance (set the default state, if a stage is to
   * be re-used)
   * 
   * @return self Return instance of stage for Function Linking.
   */
  public function reset() {
    $this->_property = false;
    $this->_interface = false;
  }

  /**
   * Compile or Transform the AST.
   * 
   * @param array $ast AST to be compiled/transformed
   * @return array Old or Transformed AST
   */
  public function compile($ast) {
    $this->_emitter->emit("<?php", true);
    $this->_processStatementBlock($ast);
    /*
      foreach ($ast as $index => $entry) {
      $this->_redirectAST($entry['type'], $entry);
      }
     */
    $this->_emitter->emit("?>", true);
    return $ast;
  }

  protected function _processStatementBlock($block, $class = null, $method = null) {
    // Process Statement Block
    foreach ($block as $statement) {
      // Process Current Statement
      $this->_processStatement($statement, $class, $method);
    }
  }

  protected function _processStatement($statement, $class = null, $method = null) {
    $type = $statement['type'];

    // Do we have Specific Handler?
    $handler = $this->_handlerName("_statement", ucfirst($type));
    if (method_exists($this, $handler)) { // YES: Use it!
      return $this->$handler($statement, $class, $method);
    } else { // NO: Try Default
      $handler = '_statementDEFAULT';
    }

    // Do we have a Default Handler?
    if (method_exists($this, $handler)) { // YES: Use it!
      return $this->$handler($statement, $class, $method);
    } else { // NO: Aborts
      throw new \Exception("Unhandled statement type [{$type}] in line [{$statement['line']}]");
    }
  }

  protected function _processExpression($expression, $class = null, $method = null) {
    $type = $expression['type'];

    // Do we have Specific Handler?
    $handler = $this->_handlerName("_expression", ucfirst($type));
    if (method_exists($this, $handler)) { // YES: Use it!
      return $this->$handler($expression, $class, $method);
    } else { // NO: Try Default
      $handler = '_expressionDEFAULT';
    }

    // Do we have a Default Handler?
    if (method_exists($this, $handler)) { // YES: Use it!
      return $this->$handler($expression, $class, $method);
    } else { // NO: Aborts
      throw new \Exception("Unhandled expression type [{$type}] in line [{$expression['line']}]");
    }
  }

  protected function _statementNamespace($namespace, $class, $method) {
    $this->_emitter->emit(['namespace', $namespace['name'], ';'], true);
  }

  protected function _statementUse($use, $class = null, $method = null) {
    // TODO: Move to the Flag to Configuration File
    $config_useLFEntries = true; // Seperate single use entries (seperated by comma, by linefeed)

    $this->_emitter->emit('use');
    $first = true;
    $indent = true; // Should we indent (more)?
    foreach ($use['aliases'] as $alias) {
      if (!$first) {
        $this->_emitter->emit(',');
        if ($config_useLFEntries) {
          $this->_emitter->emitNL();
          $this->_emitter->indent($indent);
          $indent = false;
        }
      }
      $this->_emitter->emit($alias['name']);
      if (isset($alias['alias'])) {
        $this->_emitter->emit(['as', $alias['alias']]);
      }
      $first = false;
    }
    $this->_emitter->emitEOS();
    $this->_emitter->unindent(!$indent);
  }

  protected function _statementRequire($require, $class = null, $method = null) {
    /* TODO
     * Normalize the Require Statement and Expression so that we don't have to
     * 2 handlers for the same thing.
     */
    $this->_emitter->emit('require');
    $this->_processExpression($require['expr'], $class, $method);
    $this->_emitter->emitEOS();
  }

  protected function _statementClass($class) {
    /*
      class_declaration_statement:
      class_modifiers T_CLASS { $<num>$ = CG(zend_lineno); }
      T_STRING extends_from implements_list backup_doc_comment '{' class_statement_list '}'
      { $$ = zend_ast_create_decl(ZEND_AST_CLASS, $1, $<num>3, $7, zend_ast_get_str($4), $5, $6, $9, NULL); }
      |	T_CLASS { $<num>$ = CG(zend_lineno); }
      T_STRING extends_from implements_list backup_doc_comment '{' class_statement_list '}'
      { $$ = zend_ast_create_decl(ZEND_AST_CLASS, 0, $<num>2, $6, zend_ast_get_str($3), $4, $5, $8, NULL); }
      ;
     * 
      class_modifiers:
      class_modifier 					{ $$ = $1; }
      |	class_modifiers class_modifier 	{ $$ = zend_add_class_modifier($1, $2); }
      ;
     * 
      class_modifier:
      T_ABSTRACT 		{ $$ = ZEND_ACC_EXPLICIT_ABSTRACT_CLASS; }
      |	T_FINAL 		{ $$ = ZEND_ACC_FINAL; }
      ;
     */

    /*
     * CLASS HEADER
     */
    $this->_emitter->emitNL();
    if ($class['final']) {
      $this->_emitter->emit('final');
    } else if ($class['abstract']) {
      $this->_emitter->emit('abstract');
    }

    // TODO: Move to the Flag to Configuration File
    $config_classLFImplements = true; // Seperate class / implements with line-feed
    $config_classNLImplements = true; // Multiple Implements on Seperate Lines
    $this->_emitter->emit(["class", $class['name']]);
    $implements = isset($ast['implements']) ? $ast['implements'] : null;
    if (isset($implements)) {
      $this->_emitter->emitNL($config_classLFImplements);
      $this->_emitter->indent($config_classLFImplements);
      $this->_emitter->emit('implements');
      $first = true;
      foreach ($implements as $interace) {
        if (!$first) {
          $this->_emitter->emit(',', $config_classNLImplements);
        }
        $this->_emitVariable($interace, true);
        $first = false;
      }
      /* TODO Handle Case in Which we have implements (but not extends with respect to
       * line feeds see oo/oonativeinterfaces
       */
      $this->_emitter->unindent($config_classLFImplements);
    }
    $extends = isset($class['extends']) ? $class['extends'] : null;

    // TODO: Move to the Flag to Configuration File
    $config_classLFExtends = true;            // Seperate class / extends with line-feed
    $config_classLFImplementExtends = true;   // Seperate implment/extends with line-feed

    if (isset($extends)) {
      // Add Line Feed before Extends?
      $lf = (!isset($implements) && $config_classLFExtends) ||
        ($config_classLFImplementExtends && isset($implements));

      $this->_emitter->emitNL($lf);
      $this->_emitter->indent($lf);
      $this->_emitter->emit(['extends', $extends]);
      $this->_emitter->unindent($lf);
    }

    // TODO: Move to the Flag to Configuration File
    $config_classLFStartBlock = true; // class '{' on new line?
    $this->_emitter->emitNL($config_classLFStartBlock);
    $this->_emitter->emit('{', true);
    $this->_emitter->indent();

    /*
     * CLASS BODY
     */

    // Emit the Various Sections
    $section_order = ['constants', 'properties', 'methods'];
    foreach ($section_order as $order) {
      $section = isset($class[$order]) ? $class[$order] : null;
      if (isset($section)) {
        $handler = $this->_handlerName('_emitClass', $order);
        if (method_exists($this, $handler)) {
          $this->$handler($class, $section);
        } else {
          throw new \Exception("Unhandled section type [{$order}]");
        }
      }
    }

    /*
     * CLASS FOOTER
     */

    // Garauntee that we flush any pending lines
    $this->_emitter->emitNL();
    $this->_emitter->unindent();
    $this->_emitter->emit('}', true);
  }

  protected function _statementInterface($interface) {

    /*
     * INTERFACE HEADER
     */
    $this->_emitter->emitNL();
    $this->_emitter->emit(["interface", $interface['name']]);

    // TODO: Move to the Flag to Configuration File
    $config_interfaceLFExtends = true; // Seperate interface / extends with line-feed

    $extends = isset($interface['extends']) ? $interface['extends'] : null;
    if (isset($extends)) {
      $this->_emitter->emitNL($config_interfaceLFExtends);
      $this->_emitter->indent($config_interfaceLFExtends);

      $this->_emitter->emit('extends');
      $this->_property = true;

      $first = true;
      $indent = true;
      foreach ($extends as $extend) {
        if (!$first) {
          $this->_emitter->emit(',');
          $this->_emitter->emitNL($config_interfaceLFExtends);
          $this->_emitter->indent($indent);
          $indent = false;
        }
        $this->_processExpression($extend);
        $first = false;
      }
      $this->_emitter->emitNL();
      $this->_emitter->unindent(!$indent);
      $this->_emitter->unindent($config_interfaceLFExtends);

      $this->_property = false;
    }

    // TODO: Move to the Flag to Configuration File
    $config_interfaceLFStartBlock = true; // interface '{' on new line?
    $this->_emitter->emitNL($config_interfaceLFStartBlock);
    $this->_emitter->emit('{', true);
    $this->_emitter->indent();

    /*
     * INTERFACE BODY
     */

    // Emit the Various Sections
    $section_order = ['constants', 'properties', 'methods'];
    foreach ($section_order as $order) {
      $section = isset($interface[$order]) ? $interface[$order] : null;
      if (isset($section)) {
        $this->_interface = true;
        $handler = $this->_handlerName('_emitClass', $order);
        if (method_exists($this, $handler)) {
          $this->$handler($interface, $section);
        } else {
          throw new \Exception("Unhandled section type [{$order}]");
        }
        $this->_interface = false;
      }
    }

    /*
     * CLASS FOOTER
     */

    // Garauntee that we flush any pending lines
    $this->_emitter->emitNL();
    $this->_emitter->unindent();
    $this->_emitter->emit('}', true);
  }

  protected function _emitComment($ast) {
    /* TODO:
     * Comment Modifiers Types
     * Simple Comment start with '* '
     * PHPDoc Comment starts with '**'
     * Extra Carriage Returns
     * - Before namespace       - simple YES phpdoc YES
     * - Before class           - simple  NO phpdoc NO
     * - Before Another Comment - simple  NO phpdoc NO
     */
    echo "/{$ast['value']}/\n";
  }

  protected function _emitClassConstants($class, $constants) {
    // Do we have constants to output?
    if (isset($constants) && is_array($constants)) { // YES
      // TODO: Move to the Flag to Configuration File
      $config_sortConstants = true; // Sort Class or Interface Constants?
      if ($config_sortConstants) {
        ksort($constants);
      }

      /* const CONSTANT = 'constant value'; */
      foreach ($constants as $name => $constant) {
        $this->_emitter->emit(['const', $name, '=']);
        $this->_processExpression($constant['default'], $class);
        $this->_emitter->emitEOS();
      }
    }
  }

  protected function _emitClassProperties($class, $properties) {
    // Do we have properties to output?
    if (isset($properties) && is_array($properties)) { // YES
      // TODO: Move to the Flag to Configuration File
      $config_sortProperties = true; // Sort Class or Interface Properties?
      if ($config_sortProperties) {
        ksort($properties);
      }

      foreach ($properties as $name => $property) {
        if (isset($property['visibility'])) {
          $this->_emitter->emit($property['visibility']);
        }
        $this->_emitter->emit("\${$name}");
        if (isset($property['default'])) {
          $this->_emitter->emit('=');
          $this->_processExpression($property['default'], $class);
        }
        $this->_emitter->emitEOS();
      }
    }
  }

  protected function _emitClassMethods($class, $methods) {
    // Do we have properties to output?
    if (isset($methods) && is_array($methods)) { // YES
      // TODO: Move to the Flag to Configuration File
      $config_sortMethods = true; // Sort Class or Interface Methods?
      if ($config_sortMethods) {
        ksort($methods);
      }

      foreach ($methods as $name => $method) {
        // Process Class Metho
        $this->_emitClassMethod($class, $name, $method);
      }
    }
  }

  protected function _emitClassMethod($class, $name, $method) {
    if (isset($method['docblock'])) {
      $this->_emitter->emitNL();
      $this->_emitter->emit('/*', true);
      $this->_emitter->emit($method['docblock'], true);
      ;
      $this->_emitter->emit('*/', true);
    }
    /*
     * METHOD HEADER
     */
    if (isset($method['visibility'])) {
      $this->_emitter->emit($method['visibility']);
    }
    $this->_emitter->emit(['function', $name, '(']);
    if (count($method['parameters'])) {
      // TODO: Move to the Flag to Configuration File
      $config_methodLFParameters = true; // Function Parameters on new line?
      $this->_emitter->emitNL($config_methodLFParameters);
      $this->_emitter->indent($config_methodLFParameters);

      $first = true;
      foreach ($method['parameters'] as $parameter) {
        if (!$first) {
          $this->_emitter->emit(',');
          $this->_emitter->emitNL($config_methodLFParameters);
        }
        $this->_processExpression($parameter, $class, $method);
        $first = false;
      }
      $this->_emitter->emitNL($config_methodLFParameters);
      $this->_emitter->unindent($config_methodLFParameters);
    }
    $this->_emitter->emit(')');



    /*
     * METHOD BODY
     */
    //Are we in interface mode?
    if (!$this->_interface) { // NO: Class Mode

      /* METHOD START */
      // TODO: Move to the Flag to Configuration File
      $config_methodLFStartBlock = true; // method '{' on new line?
      $this->_emitter->emitNL($config_methodLFStartBlock);
      $this->_emitter->emit('{', true);

      $this->_emitter->indent();

      /* METHOD STATEMENTS */
      if (isset($method['statements'])) {
        $this->_processStatementBlock($method['statements'], $class, $method);
      }

      // Garauntee that we flush any pending lines
      $this->_emitter->emitNL();
      $this->_emitter->unindent();

      /* METHOD END */
      $this->_emitter->emit('}', true);
    } else { // YES
      $this->_emitter->emitEOS();
    }
  }

  protected function _statementFunction($function, $class = null, $method = null) {
    $this->_emitter->emit(['function', $function['name'], '(']);
    if (count($function['parameters'])) {
      // TODO: Move to the Flag to Configuration File
      $config_functionLFParameters = true; // Function Parameters on new line?
      $this->_emitter->emitNL($config_functionLFParameters);
      $this->_emitter->indent($config_functionLFParameters);

      $first = true;
      foreach ($function['parameters'] as $parameter) {
        if (!$first) {
          $this->_emitter->emit(',');
          $this->_emitter->emitNL($config_functionLFParameters);
        }
        $this->_processExpression($parameter, $class, $method);
        $first = false;
      }
      $this->_emitter->emitNL($config_functionLFParameters);
      $this->_emitter->unindent($config_functionLFParameters);
    }
    $this->_emitter->emit(')');

    // TODO: Move to the Flag to Configuration File
    $config_functionLFStartBlock = true; // method '{' on new line?
    $this->_emitter->emitNL($config_functionLFStartBlock);
    $this->_emitter->emit('{', true);


    /*
     * METHOD BODY
     */
    $this->_emitter->indent();

    if (isset($function['statements'])) {
      $this->_processStatementBlock($function['statements']);
    }

    // Garauntee that we flush any pending lines
    $this->_emitter->emitNL();
    $this->_emitter->unindent();

    /*
     * METHOD FOOTER
     */
    $this->_emitter->emit('}', true);
  }

  protected function _statementMcall($call, $class, $method) {
    /* STATEMENT Method Calls Have a Nested AST Structure, which diferentiate
     * it from EXPRESSION Method Calls (i.e. all statements, have expressions
     * and an expression can be used as part of statements)
      [ 'type' => 'mcall',
      'expr' => [
      'type' => 'mcall'
     */

    $this->_expressionMcall($call['expr'], $class, $method);
    $this->_emitter->emitEOS();
  }

  protected function _statementFcall($call, $class, $method) {
    /* STATEMENT Function Calls Have a Nested AST Structure, which diferentiate
     * it from EXPRESSION Method Calls (i.e. all statements, have expressions
     * and an expression can be used as part of statements)
      [ 'type' => 'fcall',
      'expr' => [
      'type' => 'fcall'
     */

    $this->_expressionFcall($call['expr'], $class, $method);
    $this->_emitter->emitEOS();
  }

  protected function _statementScall($call, $class, $method) {
    /* STATEMENT Function Calls Have a Nested AST Structure, which diferentiate
     * it from EXPRESSION Method Calls (i.e. all statements, have expressions
     * and an expression can be used as part of statements)
      [ 'type' => 'fcall',
      'expr' => [
      'type' => 'fcall'
     */

    $this->_expressionScall($call['expr'], $class, $method);
    $this->_emitter->emitEOS();
  }

  protected function _statementIncr($assign, $class, $method) {
    switch ($assign['assign-to-type']) {
      case 'variable':
        $this->_emitter->emit("\${$assign['variable']}");
        break;
      case 'object-property':
        $this->_emitter->emit(["\${$assign['variable']}", '->', $assign['property']]);
        break;
      default:
        throw new \Exception("Unhandled Increment Type [{$assign['assign-to-type']}] in line [{$assign['line']}]");
    }

    $this->_emitter->emit('++');
    $this->_emitter->emitEOS();
  }

  protected function _statementDecr($assign, $class, $method) {
    switch ($assign['assign-to-type']) {
      case 'variable':
        $this->_emitter->emit("\${$assign['variable']}");
        break;
      case 'object-property':
        $this->_emitter->emit(["\${$assign['variable']}", '->', $assign['property']]);
        break;
      default:
        throw new \Exception("Unhandled Increment Type [{$assign['assign-to-type']}] in line [{$assign['line']}]");
    }

    $this->_emitter->emit('--');
    $this->_emitter->emitEOS();
  }

  protected function _statementAssign($assign, $class, $method) {
    // PROCESS TO Expression
    switch ($assign['assign-to-type']) {
      case 'variable':
        $this->_emitter->emit("\${$assign['variable']}");
        break;
      case 'variable-append':
        $this->_emitter->emit(["\${$assign['variable']}", '[', ']']);
        break;
      case 'array-index':
        $this->_emitter->emit("\${$assign['variable']}");
        $this->_statementAssignArrayIndex($assign['index-expr'], $class, $method);
        break;
      case 'array-index-append':
        $this->_emitter->emit("\${$assign['variable']}");
        $this->_statementAssignArrayIndex($assign['index-expr'], $class, $method);
        $this->_emitter->emit(['[', ']']);
        break;
      case 'object-property':
        $this->_emitter->emit(["\${$assign['variable']}", '->', $assign['property']]);
        break;
      case 'object-property-append':
        $this->_emitter->emit(["\${$assign['variable']}", '->', $assign['property'], '[', ']']);
        break;
      case 'object-property-array-index':
        $this->_emitter->emit(["\${$assign['variable']}", '->', $assign['property']]);
        $this->_statementAssignArrayIndex($assign['index-expr'], $class, $method);
        break;
      case 'object-property-array-index-append':
        $this->_emitter->emit(["\${$assign['variable']}", '->', $assign['property']]);
        $this->_statementAssignArrayIndex($assign['index-expr'], $class, $method);
        $this->_emitter->emit(['[', ']']);
        break;
      case 'variable-dynamic-object-property':
        $this->_emitter->emit(["\${$assign['variable']}", '->', "\${$assign['property']}"]);
        break;
      case 'static-property':
        $this->_emitter->emit([$assign['variable'], '::', "\${$assign['property']}"]);
        break;
      case 'static-property-append':
        $this->_emitter->emit([$assign['variable'], '::', "\${$assign['property']}", '[', ']']);
        break;
      case 'static-property-array-index':
        $this->_emitter->emit([$assign['variable'], '::', "\${$assign['property']}"]);
        $this->_statementAssignArrayIndex($assign['index-expr'], $class, $method);
        break;
      case 'static-property-array-index-append':
        $this->_emitter->emit([$assign['variable'], '::', "\${$assign['property']}"]);
        $this->_statementAssignArrayIndex($assign['index-expr'], $class, $method);
        $this->_emitter->emit(['[', ']']);
        break;
      default:
        throw new \Exception("Unhandled Assignment Type [{$assign['assign-type']}] in line [{$assign['line']}]");
    }

    // PROCESS ASSIGNMENT OPERATOR
    switch ($assign['operator']) {
      case 'assign':
        $this->_emitter->emit('=');
        break;
      case "concat-assign":
        $this->_emitter->emit('.=');
        break;
      case 'mul-assign':
        $this->_emitter->emit('*=');
        break;
      case 'add-assign':
        $this->_emitter->emit('+=');
        break;
      case 'sub-assign':
        $this->_emitter->emit('.=');
        break;
      default:
        throw new \Exception("Unhandled assignment operator  [{$assign['operator']}] in line [{$assign['line']}]");
    }

    // PROCESS R.H.S Expression
    $this->_processExpression($assign['expr'], $class, $method);
    $this->_emitter->emitEOS();
  }

  protected function _statementAssignArrayIndex($index, $class, $method) {
    $this->_emitter->emit('[');
    $first = true;
    foreach ($index as $index) {
      if (!$first) {
        $this->_emitter->emit([']', '[']);
      }
      $this->_processExpression($index, $class, $method);
      $first = false;
    }
    $this->_emitter->emit(']');
  }

  protected function _statementFor($for, $class, $method) {
    // TODO Handle 'reverse'
    // TODO Handle 'anonymous variable' i.e. key, _
    // TODO Handle range() i.e.: range(1, 10)
    // TODO over Strings
    // TODO from flow.zep : for _ in range(1, 10) (No Key, No Value)
    $key = isset($for['key']) ? ($for['key'] !== '_' ? $for['key'] : null) : null;
    $value = isset($for['value']) ? $for['value'] : null;

    /*
     * HEADER
     */
    $this->_emitter->emit(['foreach', '(']);
    $this->_processExpression($for['expr'], $class, $method);
    $this->_emitter->emit('as');
    if (isset($key)) {
      $this->_emitter->emit("\${$key}", '=>', "\${$value}");
    } else {
      $this->_emitter->emit("\${$value}");
    }
    $this->_emitter->emit(')');

    // TODO: Move to the Flag to Configuration File
    $config_forLFStartBlock = true; // method '{' on new line?
    $this->_emitter->emitNL($config_forLFStartBlock);
    $this->_emitter->emit('{', true);

    /* TODO 
     * 1. Optimization, if an 'for' has no statements we should just use a ';' rather than a '{ }' pair
     * 2. Optimization, if an 'for' has no statements, than maybe it is 'dead code' and should be removed
     * NOTE: this requires that the test expression has no side-effects (i.e. assigning within an if, function call, etc.)
     */
    /*
     * BODY
     */
    $this->_emitter->indent();

    if (isset($for['statements'])) {
      $this->_processStatementBlock($for['statements'], $class, $method);
    }

    // Garauntee that we flush any pending lines
    $this->_emitter->emitNL();
    $this->_emitter->unindent();

    /*
     * FOOTER
     */
    $this->_emitter->emit('}', true);
  }

  protected function _statementWhile($while, $class, $method) {
    /*
     * HEADER
     */
    $this->_emitter->emit(['while', '(']);
    $this->_processExpression($while['expr'], $class, $method);
    $this->_emitter->emit(')');

    // TODO: Move to the Flag to Configuration File
    $config_forLFStartBlock = true; // method '{' on new line?
    $this->_emitter->emitNL($config_forLFStartBlock);
    $this->_emitter->emit('{', true);

    /*
     * BODY
     */
    $this->_emitter->indent();

    if (isset($while['statements'])) {
      $this->_processStatementBlock($while['statements'], $class, $method);
    }

    // Garauntee that we flush any pending lines
    $this->_emitter->emitNL();
    $this->_emitter->unindent();
    /*
     * FOOTER
     */
    $this->_emitter->emit('}', true);
  }

  protected function _statementLoop($loop, $class = null, $method = null) {
    /*
     * HEADER
     */
    $this->_emitter->emit('do');

    // TODO: Move to the Flag to Configuration File
    $config_dowhileLFStartBlock = false; // method '{' on new line?
    if ($config_dowhileLFStartBlock) {
      $this->_emitter->emitNL();
    }
    $this->_emitter->emit('{', true);

    /*
     * BODY
     */
    $this->_emitter->indent();

    if (isset($loop['statements'])) {
      $this->_processStatementBlock($loop['statements'], $class, $method);
    }

    // Garauntee that we flush any pending lines
    $this->_emitter->emitNL();
    $this->_emitter->unindent();

    /*
     * FOOTER
     */
    $this->_emitter->emit(['}', 'while', '(', 'true', ')']);
    $this->_emitter->emitEOS();
  }

  protected function _statementDoWhile($dowhile, $class = null, $method = null) {
    /*
     * HEADER
     */
    $this->_emitter->emit('do');

    // TODO: Move to the Flag to Configuration File
    $config_dowhileLFStartBlock = false; // method '{' on new line?
    if ($config_dowhileLFStartBlock) {
      $this->_emitter->emitNL();
    }
    $this->_emitter->emit('{', true);

    /*
     * BODY
     */
    $this->_emitter->indent();

    if (isset($dowhile['statements'])) {
      $this->_processStatementBlock($dowhile['statements'], $class, $method);
    }

    // Garauntee that we flush any pending lines
    $this->_emitter->emitNL();
    $this->_emitter->unindent();

    /*
     * FOOTER
     */
    $this->_emitter->emit(['}', 'while', '(']);
    $this->_processExpression($dowhile['expr'], $class, $method);
    $this->_emitter->emit(')');
    $this->_emitter->emitEOS();
  }

  protected function _emitStatementLet($ast) {
    $assignments = $ast['assignments'];
    foreach ($assignments as $assignment) {
      // Assignment (LHS)
      switch ($assignment['assign-type']) {
        case 'variable-append':
          $to = "\${$assignment['variable']}[]";
          break;
        case 'array-index':
          $to = "\${$assignment['variable']}";
          foreach ($assignment['index-expr'] as $element) {
            switch ($element['type']) {
              case 'string':
                $to.="[\"{$element['value']}\"]";
                break;
              case 'variable':
                $to.="[\${$element['value']}]";
                break;
              default:
                $to.="[{$element['value']}]";
            }
          }
          break;
        case 'object-property':
          $to = "\${$assignment['variable']}->{$assignment['property']}";
          break;
        case 'object-property-append':
          $to = "\${$assignment['variable']}->{$assignment['property']}[]";
          break;
        case 'object-property-array-index':
          $to = "\${$assignment['variable']}->{$assignment['property']}";
          $indices = $assignment['index-expr'];
          foreach ($indices as $index) {
            $to.='[';
            switch ($index['type']) {
              case 'string':
                $to.="\"{$index['value']}\"";
                break;
              case 'variable':
                $to.="\${$index['value']}";
                break;
              default:
                $to.="{$index['value']}";
            }
            $to.=']';
          }
          break;
        case 'object-property-array-index-append':
          $to = "\${$assignment['variable']}->{$assignment['property']}";
          $indices = $assignment['index-expr'];
          foreach ($indices as $index) {
            $to.='[';
            switch ($index['type']) {
              case 'string':
                $to.="\"{$index['value']}\"";
                break;
              case 'variable':
                $to.="\${$index['value']}";
                break;
              default:
                $to.="{$index['value']}";
            }
            $to.=']';
          }
          $to.='[]';
          break;
        case 'object-property-incr':
          $to = "\${$assignment['variable']}->{$assignment['property']}++";
          break;
        case 'object-property-decr':
          $to = "\${$assignment['variable']}->{$assignment['property']}--";
          break;
        case 'static-property':
          $to = "{$assignment['variable']}::\${$assignment['property']}";
          break;
        case 'static-property-append':
          $to = "{$assignment['variable']}::\${$assignment['property']}[]";
          break;
        case 'static-property-array-index':
          $to = "{$assignment['variable']}::\${$assignment['property']}";
          $indices = $assignment['index-expr'];
          foreach ($indices as $index) {
            $to.='[';
            switch ($index['type']) {
              case 'string':
                $to.="\"{$index['value']}\"";
                break;
              case 'variable':
                $to.="\${$index['value']}";
                break;
              default:
                $to.="{$index['value']}";
            }
            $to.=']';
          }
          break;
        case 'static-property-array-index-append':
          $to = "{$assignment['variable']}::\${$assignment['property']}";
          $indices = $assignment['index-expr'];
          foreach ($indices as $index) {
            $to.='[';
            switch ($index['type']) {
              case 'string':
                $to.="\"{$index['value']}\"";
                break;
              case 'variable':
                $to.="\${$index['value']}";
                break;
              default:
                $to.="{$index['value']}";
            }
            $to.=']';
          }
          $to.='[]';
          break;
        case 'incr':
          $to = "\${$assignment['variable']}++";
          break;
        case 'decr':
          $to = "\${$assignment['variable']}--";
          break;
        default:
          $to = "\${$assignment['variable']}";
          break;
      }
      echo "{$to}";
      // Operator
      if (isset($assignment['operator'])) {
        echo ' ';
        switch ($assignment['operator']) {
          case 'assign':
            echo '=';
            break;
          case 'mul-assign':
            echo '*=';
            break;
          case 'add-assign':
            echo '+=';
            break;
          case 'sub-assign':
            echo '-=';
            break;
          case 'concat-assign':
            echo '.=';
            break;
          default:
            echo "Operator Type [{$assignment['operator']}] is unknown";
        }
      }
      // Assignment (RHS)
      if (isset($assignment['expr'])) {
        $rhs = $assignment['expr'];
        $this->_emitExpression($rhs);
      }
      echo ";\n";
    }
  }

  protected function _statementIf($if, $class = null, $method = null) {
    /* IF (EXPR) */
    $this->_statementIfExpression($if, $class, $method);

    /* ELSE IF { statements } */
    if (isset($if['elseif_statements'])) {
      foreach ($if['elseif_statements'] as $else_if) {
        $this->_emitter->emit('else');
        $this->_statementIfExpression($else_if, $class, $method);
      }
    }

    /* ELSE { statements } */
    if (isset($if['else_statements'])) {
      $this->_emitter->emit(['else', '{'], true);
      $this->_emitter->indent();

      /* ELSE { statements } */
      $this->_processStatementBlock($if['else_statements'], $class, $method);

      // Garauntee that we flush any pending lines
      $this->_emitter->emitNL();
      $this->_emitter->unindent();
      $this->_emitter->emit('}', true);
    }
  }

  protected function _statementIfExpression($if_expr, $class = null, $method = null) {
    $this->_emitter->emit(['if', '(']);
    // TODO: Move to the Flag to Configuration File
    $config_ifLFExpressions = true; // Function Parameters on new line?
    $this->_emitter->emitNL($config_ifLFExpressions);
    $this->_emitter->indent($config_ifLFExpressions);

    $this->_processExpression($if_expr['expr'], $class, $method);

    $this->_emitter->emitNL($config_ifLFExpressions);
    $this->_emitter->unindent($config_ifLFExpressions);
    $this->_emitter->emit(')');

    $config_ifLFStartBlock = true; // '{' on new line?
    $this->_emitter->emitNL($config_ifLFStartBlock);
    $this->_emitter->emit('{', true);

    /* IF { statements } */
    $this->_emitter->indent();

    if (isset($if_expr['statements'])) {
      $this->_processStatementBlock($if_expr['statements'], $class, $method);
    }

    // Garauntee that we flush any pending lines
    $this->_emitter->emitNL();
    $this->_emitter->unindent();
    $this->_emitter->emit('}', true);
  }

  protected function _statementSwitch($switch, $class = null, $method = null) {
    // HEADER
    $this->_emitter->emit(['switch', '(']);
    // TODO: Move to the Flag to Configuration File
    $config_switchLFExpressions = true; // Function Parameters on new line?
    $this->_emitter->emitNL($config_switchLFExpressions);
    $this->_emitter->indent($config_switchLFExpressions);

    $this->_processExpression($switch['expr'], $class, $method);

    $this->_emitter->emitNL($config_switchLFExpressions);
    $this->_emitter->unindent($config_switchLFExpressions);
    $this->_emitter->emit(')');

    $config_switchLFStartBlock = true; // '{' on new line?
    $this->_emitter->emitNL($config_switchLFStartBlock);
    $this->_emitter->emit('{', true);
    $this->_emitter->indent();

    // BODY : SWITCH CLAUSES
    /* TODO 
     * 1. Optimization, if an 'switch' has no clauses we should just use a ';' rather than a '{ }' pair
     * 2. Optimization, if an 'switch' has no clauses, than maybe it is 'dead code' and should be removed
     * NOTE: this requires that the test expression has no side-effects (i.e. assigning within an if, function call, etc.)
     */
    if (isset($switch['clauses'])) {
      foreach ($switch['clauses'] as $clause) {
        switch ($clause['type']) {
          case 'case':
            $this->_emitter->emit('case');
            $this->_processExpression($clause['expr'], $class, $method);
            $this->_emitter->emit(':', true);
            break;
          case 'default':
            $this->_emitter->emit(['default', ':'], true);
            break;
          default:
            throw new \Exception("Unexpected SWITCH Clause Type [{$clause['type']}] in line [{$assign['line']}]");
        }

        // Do we have statements for the clause?
        if (isset($clause['statements']) && count($clause['statements'])) { // YES : Process
          $this->_emitter->indent();
          $this->_processStatementBlock($clause['statements'], $class, $method);
          $this->_emitter->unindent();
        }
      }
    }

    // FOOTER
    $this->_emitter->emitNL();
    $this->_emitter->unindent();
    $this->_emitter->emit('}', true);
  }

  protected function _statementTryCatch($trycatch, $class = null, $method = null) {
    // BODY : CATCH CLAUSES
    /* TODO 
     * 1. Optimization, if an 'try' has no clauses we should just use a ';' rather than a '{ }' pair
     * 2. Optimization, if an 'try' has no clauses, than maybe it is 'dead code' and should be removed
     * NOTE: this requires that the test expression has no side-effects (i.e. assigning within an if, function call, etc.)
     */
    /* TRY HEADER */
    $this->_emitter->emit('try');

    // TODO: Move to the Flag to Configuration File
    $config_tryLFStartBlock = false; // method '{' on new line?
    if ($config_tryLFStartBlock) {
      $this->_emitter->emitNL();
    }
    $this->_emitter->emit('{', true);

    /* TRY BODY */
    $this->_emitter->indent();
    $this->_processStatementBlock($trycatch['statements'], $class, $method);

    // Garauntee that we flush any pending lines
    $this->_emitter->emitNL();
    $this->_emitter->unindent();

    /*
     * TRY FOOTER
     */
    $this->_emitter->emit('}');

    // BODY : CATCH CLAUSES
    /* TODO 
     * 1. Optimization, if an 'catch' has no clauses we should just use a ';' rather than a '{ }' pair
     * 2. Optimization, if an 'catch' has no clauses, than maybe it is 'dead code' and should be removed
     * NOTE: this requires that the test expression has no side-effects (i.e. assigning within an if, function call, etc.)
     */
    foreach ($trycatch['catches'] as $catch) {
      /* CATCH HEADER */
      $this->_emitter->emit(['catch', '(']);
      $this->_property = true;
      $this->_processExpression($catch['class'], $class, $method);
      $this->_property = false;
      $this->_processExpression($catch['variable'], $class, $method);
      $this->_emitter->emit(')', true);

      // TODO: Move to the Flag to Configuration File
      $config_tryLFStartBlock = false; // method '{' on new line?
      if ($config_tryLFStartBlock) {
        $this->_emitter->emitNL();
      }
      $this->_emitter->emit('{', true);

      /* CATCH BODY */
      $this->_emitter->indent();
      $this->_processStatementBlock($catch['statements'], $class, $method);

      // Garauntee that we flush any pending lines
      $this->_emitter->emitNL();
      $this->_emitter->unindent();

      /* TRY FOOTER */
      $this->_emitter->emit('}', true);
    }
  }

  protected function _statementContinue($continue, $class = null, $method = null) {
    $this->_emitter->emit('continue');
    $this->_emitter->emitEOS();
  }

  protected function _statementBreak($break, $class = null, $method = null) {
    $this->_emitter->emit('break');
    $this->_emitter->emitEOS();
  }

  protected function _statementReturn($return, $class = null, $method = null) {
    $this->_emitter->emit('return');
    $this->_processExpression($return['expr'], $class, $method);
    $this->_emitter->emitEOS();
  }

  protected function _statementThrow($throw, $class = null, $method = null) {
    $this->_emitter->emit('throw');
    $this->_processExpression($throw['expr'], $class, $method);
    $this->_emitter->emitEOS();
  }

  protected function _statementUnset($unset, $class = null, $method = null) {
    $this->_emitter->emit(['unset', '(']);
    $this->_processExpression($unset['expr'], $class, $method);
    $this->_emitter->emit(')');
    $this->_emitter->emitEOS();
  }

  protected function _statementEcho($echo, $class = null, $method = null) {
    $this->_emitter->emit('echo');
    $first = true;
    foreach ($echo['expressions'] as $expression) {
      if (!$first) {
        $this->_emitter->emit('.');
      }
      $this->_processExpression($expression, $class, $method);
      $first = false;
    }
    $this->_emitter->emitEOS();
  }

  protected function _emitStatementClone($ast) {
    throw new \Exception('TODO');
  }

  protected function _emitStatementRequire($ast) {
    // TODO Merge Require Statement and Require Expression in single function
    // NOTE: AST Structure is different than in the case of Fcall in which we have an expression wrapped as a statement
    $expr = $ast['expr'];
    echo 'require ';
    $this->_emitExpression($expr);
    echo ";\n";
  }

  protected function _emitExpression($ast) {
    $this->_redirectAST($ast['type'], $ast);
  }

  protected function _emitReference($ast) {
    throw new \Exception('TODO');
  }

  /**
   * Class Static Method Call
   * 
   * @param type $ast
   */
  protected function _expressionScall($call, $class = null, $method = null) {
    $this->_emitter->emit([$call['class'], '::', $call['name'], '(']);
    if (count($call['parameters'])) {
      $first = true;
      foreach ($call['parameters'] as $parameter) {
        if (!$first) {
          $this->_emitter->emit(',');
        }
        $this->_processExpression($parameter, $class, $method);
        $first = false;
      }
    }
    $this->_emitter->emit(')');
  }

  /**
   * Class Method Call
   * 
   * @param type $ast
   */
  protected function _expressionMcall($call, $class = null, $method = null) {
    $this->_processExpression($call['variable']);
    $this->_emitter->emit(['->', $call['name'], '(']);
    if (count($call['parameters'])) {
      $first = true;
      foreach ($call['parameters'] as $parameter) {
        if (!$first) {
          $this->_emitter->emit(',');
        }
        $this->_processExpression($parameter, $class, $method);
        $first = false;
      }
    }
    $this->_emitter->emit(')');
  }

  /**
   * Function Call
   * 
   * @param type $ast
   */
  protected function _expressionFcall($call, $class = null, $method = null) {
    $this->_emitter->emit([$call['name'], '(']);
    if (count($call['parameters'])) {
      $first = true;
      foreach ($call['parameters'] as $parameter) {
        if (!$first) {
          $this->_emitter->emit(',');
        }
        $this->_processExpression($parameter, $class, $method);
        $first = false;
      }
    }
    $this->_emitter->emit(')');
  }

  protected function _expressionNew($new, $class, $method) {
    $this->_emitter->emit(['new', $new['class']]);
    if (isset($new['parameters'])) {
      $this->_emitter->emit('(');

      // TODO: Move to the Flag to Configuration File
      $config_callLFParameters = true; // Function Parameters on new line?
      $this->_emitter->emitNL($config_callLFParameters);
      $this->_emitter->indent($config_callLFParameters);

      $first = true;
      foreach ($new['parameters'] as $parameter) {
        if (!$first) {
          $this->_emitter->emit(',');
          $this->_emitter->emitNL($config_callLFParameters);
        }
        $this->_processExpression($parameter, $class, $method);
        $first = false;
      }
      $this->_emitter->emitNL($config_callLFParameters);
      $this->_emitter->unindent($config_callLFParameters);
      $this->_emitter->emit(')');
    }
  }

  protected function _expressionIsset($isset, $class, $method) {
    $left = $isset['left'];
    switch ($left['type']) {
      case 'array-access':
        $this->_emitter->emit(['zephir_isset_array', '(']);
        $this->_processExpression($left['left'], $class, $method);
        $this->_emitter->emit(',');
        $this->_processExpression($left['right'], $class, $method);
        $this->_emitter->emit(')');
        break;
      case 'property-access':
      case 'property-string-access':
        $this->_emitter->emit(['zephir_isset_property', '(']);
        $this->_processExpression($left['left'], $class, $method);
        $this->_emitter->emit(',');
        $right = $left['right'];
        switch ($right['type']) {
          case 'variable':
          case 'string':
            $this->_emitter->emit("'{$right['value']}'");
            break;
          default:
            throw new \Exception("TODO - 1 - isset([{$right['type']}])");
        }
        $this->_emitter->emit(')');
        break;
      case 'property-dynamic-access':
        $this->_emitter->emit(['zephir_isset_property', '(']);
        $this->_processExpression($left['left'], $class, $method);
        $this->_emitter->emit(',');
        $this->_processExpression($left['right'], $class, $method);
        $this->_emitter->emit(')');
        break;
      case 'static-property-access':
        // TODO Verify if this is what zephir does for static access
        $this->_emitter->emit(['isset', '(']);
        $this->_property = true;
        $this->_processExpression($left['left'], $class, $method);
        $this->_property = false;
        $this->_emitter->emit('::');
        $this->_processExpression($left['right'], $class, $method);
        $this->_emitter->emit(')');
        break;
      default:
        throw new \Exception("TODO - 2 - isset([{$type}]) in line [{$isset['line']}]");
    }
  }

  protected function _expressionTypeof($typeof, $class, $method) {
    $this->_emitter->emit(['gettype', '(']);
    $this->_processExpression($typeof['left'], $class, $method);
    $this->_emitter->emit(')');
  }

  protected function _expressionParameter($parameter, $class, $method) {
    $this->_emitter->emit("\${$parameter['name']}");
    if (isset($parameter['default'])) {
      $this->_emitter->emit('=');
      $this->_processExpression($parameter['default'], $class);
    }
  }

  protected function _expressionArrayAccess($expression, $class = null, $method = null) {
    $left = $expression['left'];
    $right = $expression['right'];
    $this->_processExpression($left, $class, $method);
    $this->_emitter->emit('[');
    $this->_processExpression($right, $class, $method);
    $this->_emitter->emit(']');
  }

  protected function _expressionPropertyAccess($expression, $class = null, $method = null) {
    $left = $expression['left'];
    $right = $expression['right'];
    $this->_processExpression($left, $class, $method);
    $this->_emitter->emit('->');
    // Flag the Next Expression as Property Expression
    $this->_property = true;
    $this->_processExpression($right, $class, $method);
    $this->_property = false;
  }

  protected function _expressionPropertyDynamicAccess($expression, $class = null, $method = null) {
    $left = $expression['left'];
    $right = $expression['right'];
    $this->_processExpression($left, $class, $method);
    $this->_emitter->emit('->');
    // Flag the Next Expression as Property Expression
    $this->_processExpression($right, $class, $method);
  }

  protected function _expressionStaticPropertyAccess($expression, $class = null, $method = null) {
    $left = $expression['left'];
    $right = $expression['right'];
    $this->_property = true;
    $this->_processExpression($left, $class, $method);
    $this->_property = false;
    $this->_emitter->emit('::');
    $this->_processExpression($right, $class, $method);
  }

  protected function _expressionStaticConstantAccess($expression, $class = null, $method = null) {
    $left = $expression['left'];
    $right = $expression['right'];
    $this->_property = true;
    $this->_processExpression($left, $class, $method);
    $this->_emitter->emit('::');
    $this->_processExpression($right, $class, $method);
    $this->_property = false;
  }

  protected function _expressionCast($cast, $class, $method) {
    // TODO Correct Type Casts in Normalization Phase rather than here...
    $type = $cast['left'];
    switch($type) {
      case 'char': // CHAR is a ZEP type only
        $type = 'string';
      break;
      case 'long': // LONG is a ZEP type only
        $type = 'int';
        break;
    }
    if (isset($type)) {
      $this->_emitter->emit(['(', $type, ')']);
    }
    $this->_processExpression($cast['right'], $class, $method);
  }

  protected function _expressionTernary($ternary, $class, $method) {
    /* TODO Add Configuration for Line Feed and Alignment
     * example: expr ? true
     *               : false;
     */
    $this->_processExpression($ternary['left'], $class, $method);
    $this->_emitter->emit('?');
    $this->_processExpression($ternary['right'], $class, $method);
    $this->_emitter->emit(':');
    $this->_processExpression($ternary['extra'], $class, $method);
  }

  protected function _expressionVariable($variable, $class, $method) {
    if ($this->_property) {
      $this->_emitter->emit($variable['value']);
    } else {
      $this->_emitter->emit("\${$variable['value']}");
    }
  }

  protected function _emitNewType($ast) {
    $type = $ast['internal-type'];
    $parameters = isset($ast['parameters']) ? $ast['parameters'] : null;
    switch ($type) {
      case 'array':
        // TODO : Verify if this is correct handling for zephir
        echo '[]';
        break;
      case 'string':
        // TODO: See the Actual Implementation to Verify if this is worth it
        echo "''";
        break;
      default:
        throw new \Exception("Function [_emitNewType] - Cannot build instance of type [{$type}]");
    }
  }

  protected function _expressionClosure($closure, $class, $method) {
    /*
     * METHOD HEADER
     */
    $this->_emitter->emit(['function', '(']);
    if (count($closure['parameters'])) {
      // TODO: Move to the Flag to Configuration File
      $config_methodLFParameters = true; // Function Parameters on new line?
      $this->_emitter->emitNL($config_methodLFParameters);
      $this->_emitter->indent($config_methodLFParameters);

      $first = true;
      foreach ($closure['parameters'] as $parameter) {
        if (!$first) {
          $this->_emitter->emit(',');
          $this->_emitter->emitNL($config_methodLFParameters);
        }
        $this->_processExpression($parameter, $class, $method);
        $first = false;
      }
      $this->_emitter->emitNL($config_methodLFParameters);
      $this->_emitter->unindent($config_methodLFParameters);
    }
    $this->_emitter->emit(')');

    // TODO: Move to the Flag to Configuration File
    $config_methodLFStartBlock = true; // method '{' on new line?
    $this->_emitter->emitNL($config_methodLFStartBlock);
    $this->_emitter->emit('{', true);

    /*
     * METHOD BODY
     */
    $this->_emitter->indent();

    if (isset($closure['statements'])) {
      $this->_processStatementBlock($closure['statements'], $class, $method);
    }

    // Garauntee that we flush any pending lines
    $this->_emitter->emitNL();
    $this->_emitter->unindent();

    /*
     * METHOD FOOTER
     */
    $this->_emitter->emit('}', true);
  }

  protected function _expressionRequire($require, $class, $method) {
    $this->_emitter->emit('require ');
    $this->_processExpression($require['left'], $class, $method);
  }

  /*
   * EXPRESSION OPERATORS
   */

  protected function _emitOperator($left, $operator, $right, $class, $method) {
    $this->_processExpression($left, $class, $method);
    $this->_emitter->emit($operator);
    $this->_processExpression($right, $class, $method);
  }

  protected function _expressionList($list, $class, $method) {
    $this->_emitter->emit('(');
    $this->_processExpression($list['left'], $class, $method);
    $this->_emitter->emit(')');
  }

  protected function _expressionBitwiseNot($bitwise_not, $class, $method) {
    $this->_emitter->emit('~');
    $this->_processExpression($bitwise_not['left'], $class, $method);
  }

  protected function _expressionMinus($minus, $class, $method) {
    $this->_emitter->emit('-');
    $this->_processExpression($minus['left'], $class, $method);
  }

  protected function _expressionPlus($plus, $class, $method) {
    $this->_emitter->emit('+');
    $this->_processExpression($plus['left'], $class, $method);
  }

  protected function _expressionAdd($operation, $class, $method) {
    $this->_emitOperator($operation['left'], '+', $operation['right'], $class, $method);
  }

  protected function _expressionSub($operation, $class, $method) {
    $this->_emitOperator($operation['left'], '-', $operation['right'], $class, $method);
  }

  protected function _expressionMul($operation, $class, $method) {
    $this->_emitOperator($operation['left'], '*', $operation['right'], $class, $method);
  }

  protected function _expressionDiv($operation, $class, $method) {
    $this->_emitOperator($operation['left'], '/', $operation['right'], $class, $method);
  }

  protected function _expressionMod($operation, $class, $method) {
    $this->_emitOperator($operation['left'], '%', $operation['right'], $class, $method);
  }

  protected function _expressionBitwiseOr($operation, $class, $method) {
    $this->_emitOperator($operation['left'], '|', $operation['right'], $class, $method);
  }

  protected function _expressionBitwiseAnd($operation, $class, $method) {
    $this->_emitOperator($operation['left'], '&', $operation['right'], $class, $method);
  }

  protected function _expressionBitwiseXor($operation, $class, $method) {
    $this->_emitOperator($operation['left'], '^', $operation['right'], $class, $method);
  }

  protected function _expressionBitwiseShiftleft($operation, $class, $method) {
    $this->_emitOperator($operation['left'], '<<', $operation['right'], $class, $method);
  }

  protected function _expressionBitwiseShiftright($operation, $class, $method) {
    $this->_emitOperator($operation['left'], '>>', $operation['right'], $class, $method);
  }

  protected function _expressionConcat($operation, $class, $method) {
    $this->_emitOperator($operation['left'], '.', $operation['right'], $class, $method);
  }

  /*
   * EXPRESSIONS BOOLEAN OPERATORS
   */

  protected function _expressionNot($operation, $class, $method) {
    $this->_emitter->emit('!');
    $this->_processExpression($operation['left'], $class, $method);
  }

  protected function _expressionEquals($operation, $class, $method) {
    $this->_emitOperator($operation['left'], '==', $operation['right'], $class, $method);
  }

  protected function _expressionNotEquals($operation, $class, $method) {
    $this->_emitOperator($operation['left'], '!=', $operation['right'], $class, $method);
  }

  protected function _expressionIdentical($operation, $class, $method) {
    $this->_emitOperator($operation['left'], '===', $operation['right'], $class, $method);
  }

  protected function _expressionNotIdentical($operation, $class, $method) {
    $this->_emitOperator($operation['left'], '!==', $operation['right'], $class, $method);
  }

  protected function _expressionAnd($operation, $class, $method) {
    $this->_emitOperator($operation['left'], '&&', $operation['right'], $class, $method);
  }

  protected function _expressionOr($operation, $class, $method) {
    $this->_emitOperator($operation['left'], '||', $operation['right'], $class, $method);
  }

  protected function _expressionInstanceof($operation, $class, $method) {
    $this->_processExpression($operation['left'], $class, $method);
    $this->_emitter->emit('instanceof');
    $this->_property = true;
    $this->_processExpression($operation['right'], $class, $method);
    $this->_property = true;
  }

  protected function _expressionLess($operation, $class, $method) {
    $this->_emitOperator($operation['left'], '<', $operation['right'], $class, $method);
  }

  protected function _expressionLessEqual($operation, $class, $method) {
    $this->_emitOperator($operation['left'], '<=', $operation['right'], $class, $method);
  }

  protected function _expressionGreater($operation, $class, $method) {
    $this->_emitOperator($operation['left'], '>', $operation['right'], $class, $method);
  }

  protected function _expressionGreaterEqual($operation, $class, $method) {
    $this->_emitOperator($operation['left'], '>=', $operation['right'], $class, $method);
  }

  /*
   * EXPRESSIONS BASIC TYPES
   */

  protected function _expressionDouble($ast, $class = null, $method = null) {
    $this->_emitter->emit($ast['value']);
  }

  protected function _expressionInt($ast, $class = null, $method = null) {
    $this->_emitter->emit($ast['value']);
  }

  protected function _expressionBool($ast, $class = null, $method = null) {
    $this->_emitter->emit(strtoupper($ast['value']));
  }

  protected function _expressionNull($ast, $class = null, $method = null) {
    $this->_emitter->emit('NULL');
  }

  protected function _expressionString($ast, $class = null, $method = null) {
    // Make usre that string that include quotes and such, are properly escaped.
    $string = addslashes($ast['value']);
    $this->_emitter->emit("\"{$string}\"");
  }

  protected function _expressionChar($ast, $class = null, $method = null) {
    $this->_emitter->emit("'{$ast['value']}'");
  }

  protected function _expressionArray($array, $class = null, $method = null) {
    // OPEN ARRAY
    $this->_emitter->emit('[');

    // PROCESS ARRAY ELEMENTS
    $first = true;
    foreach ($array['left'] as $entry) {
      if (!$first) {
        $this->_emitter->emit(',');
      }
      $key = isset($entry['key']) ? $entry['key'] : null;
      if (isset($key)) {
        $this->_processExpression($key, $class, $method);
        $this->_emitter->emit('=>');
      }
      $this->_processExpression($entry['value'], $class, $method);
      $first = false;
    }

    // CLOSE ARRAY
    $this->_emitter->emit(']');
  }

  protected function _expressionEmptyArray($array, $class = null, $method = null) {
    $this->_emitter->emit('[]');
  }

  protected function _expressionConstant($constant, $class = null, $method = null) {
    $this->_emitter->emit($constant['value']);
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

}
