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

  // Spaces for Indent
  const spaces = '         ';
  const tabs = "\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t";

  protected $_current_line = '';
  protected $_current_length = 0;
  protected $_indent_level = 0;
  // TODO: Maybe Create a Compile Context Object so as to simplify handlers
  // Processing a Right Expression (i.e. for variables this means dropping the '$');
  protected $_property = false;

  /**
   * Initialize the Stage Instance
   * 
   * @return self Return instance of stage for Function Linking.
   */
  public function initialize() {
    return $this;
  }

  /**
   * Compile or Transform the AST.
   * 
   * @param array $ast AST to be compiled/transformed
   * @return array Old or Transformed AST
   */
  public function compile($ast) {
    echo "<?php\n";
    $this->_processStatementBlock($ast);
    /*
      foreach ($ast as $index => $entry) {
      $this->_redirectAST($entry['type'], $entry);
      }
     */
    echo "?>\n";

    return $ast;
  }

  protected function _append($content, $flush = false) {
    // Is the Content an Array?
    if (is_array($content)) { // YES: Build a Space Seperated String of the array
      $content = implode(' ', array_map(function ($e) {
          return trim($e);
        }, $content));
    }

    // Is the Current Line Empty?
    if ($this->_current_length === 0) { // YES
      $this->_current_line = trim($content);
    } else { // NO: Append
      $this->_current_line.=' ' . trim($content);
    }
    $this->_current_length = strlen($this->_current_line);

    if ($flush) {
      $this->_flush();
    }
  }

  protected function _appendEOS($flush = true) {
    $this->_append(';', $flush);
  }

  protected function _flush($force = false) {
    if ($this->_current_length) {
      echo $this->_indentation() . trim($this->_current_line) . "\n";
      $this->_current_line = '';
      $this->_current_length = 0;
    } else if ($force) {
      echo "\n";
    }
  }

  protected function _indent($indent = true) {
    if ($indent) {
      $this->_indent_level++;
    }
  }

  protected function _unindent($unindent = true) {
    if ($unindent) {
      $this->_indent_level--;
    }

    if ($this->_indent_level < 0) {
      throw new \Exception("Indentation Level CANNOT BE less than ZERO");
    }
  }

  protected function _indentation() {
    // TODO: Move to the Flags to Configuration File
    $config_indentSpaces = true; // Seperate interface / extends with line-feed
    $config_indentSize = 2; // Seperate interface / extends with line-feed
    $config_indentMax = 10; // Maximum of 10 Indent Levels
    // Create Indent Filler Unit
    if ($config_indentSpaces) {
      $filler = substr(self::spaces, 0, $config_indentSize <= 10 ? $config_indentSize : 10);
    } else {
      $filler = "\t";
    }

    // Calculate Filler
    $indent = $this->_indent_level > $config_indentMax ? $config_indentMax : $this->_indent_level;
    $filler = str_repeat($filler, $indent);
    return $filler;
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
    $this->_append(['namespace', $namespace['name'], ';'], true);
  }

  protected function _statementUse($use, $class = null, $method = null) {
    // TODO: Move to the Flag to Configuration File
    $config_useLFEntries = true; // Seperate single use entries (seperated by comma, by linefeed)

    $this->_append('use');
    $first = true;
    $indent = true; // Should we indent (more)?
    foreach ($use['aliases'] as $alias) {
      if (!$first) {
        $this->_append(',');
        if ($config_useLFEntries) {
          $this->_flush();
          $this->_indent($indent);
          $indent = false;
        }
      }
      $this->_append($alias['name']);
      if (isset($alias['alias'])) {
        $this->_append(['as', $alias['name']]);
      }
      $first = false;
    }
    $this->_appendEOS();
    $this->_unindent(!$indent);
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
    $this->_flush();
    if ($class['final']) {
      $this->_append('final');
    } else if ($class['abstract']) {
      $this->_append('abstract');
    }

    // TODO: Move to the Flag to Configuration File
    $config_classLFImplements = true; // Seperate class / implements with line-feed
    $config_classNLImplements = true; // Multiple Implements on Seperate Lines
    $this->_append(["class", $class['name']]);
    $implements = isset($ast['implements']) ? $ast['implements'] : null;
    if (isset($implements)) {
      $this->_flush($config_classLFImplements);
      $this->_indent($config_classLFImplements);
      $this->_append('implements');
      $first = true;
      foreach ($implements as $interace) {
        if (!$first) {
          $this->_append(',', $config_classNLImplements);
        }
        $this->_emitVariable($interace, true);
        $first = false;
      }
      /* TODO Handle Case in Which we have implements (but not extends with respect to
       * line feeds see oo/oonativeinterfaces
       */
      $this->_unindent($config_classLFImplements);
    }
    $extends = isset($class['extends']) ? $class['extends'] : null;

    // TODO: Move to the Flag to Configuration File
    $config_classLFExtends = true;            // Seperate class / extends with line-feed
    $config_classLFImplementExtends = true;   // Seperate implment/extends with line-feed

    if (isset($extends)) {
      // Add Line Feed before Extends?
      $lf = (!isset($implements) && $config_classLFExtends) ||
        ($config_classLFImplementExtends && isset($implements));

      $this->_flush($lf);
      $this->_indent($lf);
      $this->_append(['extends', $extends]);
      $this->_unindent($lf);
    }

    // TODO: Move to the Flag to Configuration File
    $config_classLFStartBlock = true; // class '{' on new line?
    $this->_flush($config_classLFStartBlock);
    $this->_append('{', true);
    $this->_indent();

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
    $this->_flush();
    $this->_unindent();
    $this->_append('}', true);
  }

  protected function _statementInterface($interface) {

    /*
     * INTERFACE HEADER
     */
    $this->_flush();
    $this->_append(["interface", $interface['name']]);

    // TODO: Move to the Flag to Configuration File
    $config_interfaceLFExtends = true; // Seperate interface / extends with line-feed

    $extends = isset($interface['extends']) ? $interface['extends'] : null;
    if (isset($extends)) {
      $this->_flush($config_interfaceLFExtends);
      $this->_indent($config_interfaceLFExtends);

      $this->_append('extends');
      $this->_property = true;

      $first = true;
      $indent = true;
      foreach ($extends as $extend) {
        if (!$first) {
          $this->_append(',');
          $this->_flush($config_interfaceLFExtends);
          $this->_indent($indent);
          $indent = false;
        }
        $this->_processExpression($extend);
        $first = false;
      }
      $this->_flush();
      $this->_unindent(!$indent);
      $this->_unindent($config_interfaceLFExtends);

      $this->_property = false;
    }

    // TODO: Move to the Flag to Configuration File
    $config_interfaceLFStartBlock = true; // interface '{' on new line?
    $this->_flush($config_interfaceLFStartBlock);
    $this->_append('{', true);
    $this->_indent();

    /*
     * INTERFACE BODY
     */

    // Emit the Various Sections
    $section_order = ['constants', 'properties', 'methods'];
    foreach ($section_order as $order) {
      $section = isset($interface[$order]) ? $interface[$order] : null;
      if (isset($section)) {
        $handler = $this->_handlerName('_emitClass', $order);
        if (method_exists($this, $handler)) {
          $this->$handler($interface, $section);
        } else {
          throw new \Exception("Unhandled section type [{$order}]");
        }
      }
    }

    /*
     * CLASS FOOTER
     */

    // Garauntee that we flush any pending lines
    $this->_flush();
    $this->_unindent();
    $this->_append('}', true);
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
        $this->_append(['const', $name, '=']);
        $this->_processExpression($constant['default'], $class);
        $this->_appendEOS();
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
          $this->_append($property['visibility']);
        }
        $this->_append("\${$name}");
        if (isset($property['default'])) {
          $this->_append('=');
          $this->_processExpression($property['default'], $class);
        }
        $this->_appendEOS();
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
      $this->_flush();
      $this->_append('/*', true);
      $this->_append($method['docblock'], true);
      ;
      $this->_append('*/', true);
    }
    /*
     * METHOD HEADER
     */
    if (isset($method['visibility'])) {
      $this->_append($method['visibility']);
    }
    $this->_append(['function', $name, '(']);
    if (count($method['parameters'])) {
      // TODO: Move to the Flag to Configuration File
      $config_methodLFParameters = true; // Function Parameters on new line?
      $this->_flush($config_methodLFParameters);
      $this->_indent($config_methodLFParameters);

      $first = true;
      foreach ($method['parameters'] as $parameter) {
        if (!$first) {
          $this->_append(',');
          $this->_flush($config_methodLFParameters);
        }
        $this->_processExpression($parameter, $class, $method);
        $first = false;
      }
      $this->_flush($config_methodLFParameters);
      $this->_unindent($config_methodLFParameters);
    }
    $this->_append(')');

    // TODO: Move to the Flag to Configuration File
    $config_methodLFStartBlock = true; // method '{' on new line?
    $this->_flush($config_methodLFStartBlock);
    $this->_append('{', true);


    /*
     * METHOD BODY
     */
    $this->_indent();

    if (isset($method['statements'])) {
      $this->_processStatementBlock($method['statements'], $class, $method);
    }

    // Garauntee that we flush any pending lines
    $this->_flush();
    $this->_unindent();

    /*
     * METHOD FOOTER
     */
    $this->_append('}', true);
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
    $this->_appendEOS();
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
    $this->_appendEOS();
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
    $this->_appendEOS();
  }

  protected function _statementIncr($assign, $class, $method) {
    switch ($assign['assign-to-type']) {
      case 'variable':
        $this->_append("\${$assign['variable']}");
        break;
      case 'object-property':
        $this->_append(["\${$assign['variable']}", '->', $assign['property']]);
        break;
      default:
        throw new \Exception("Unhandled Increment Type [{$assign['assign-to-type']}] in line [{$assign['line']}]");
    }

    $this->_append('++');
  }

  protected function _statementDecr($assign, $class, $method) {
    switch ($assign['assign-to-type']) {
      case 'variable':
        $this->_append("\${$assign['variable']}");
        break;
      case 'object-property':
        $this->_append(["\${$assign['variable']}", '->', $assign['property']]);
        break;
      default:
        throw new \Exception("Unhandled Increment Type [{$assign['assign-to-type']}] in line [{$assign['line']}]");
    }

    $this->_append('--');
  }

  protected function _statementAssign($assign, $class, $method) {
    // PROCESS TO Expression
    switch ($assign['assign-to-type']) {
      case 'variable':
        $this->_append("\${$assign['variable']}");
        break;
      case 'variable-append':
        $this->_append(["\${$assign['variable']}", '[', ']']);
        break;
      case 'array-index':
        $this->_append("\${$assign['variable']}");
        $this->_statementAssignArrayIndex($assign['index-expr'], $class, $method);
        break;
      case 'array-index-append':
        $this->_append("\${$assign['variable']}");
        $this->_statementAssignArrayIndex($assign['index-expr'], $class, $method);
        $this->_append(['[', ']']);
        break;
      case 'object-property':
        $this->_append(["\${$assign['variable']}", '->', $assign['property']]);
        break;
      case 'object-property-append':
        $this->_append(["\${$assign['variable']}", '->', $assign['property'], '[', ']']);
        break;
      case 'object-property-array-index':
        $this->_append(["\${$assign['variable']}", '->', $assign['property']]);
        $this->_statementAssignArrayIndex($assign['index-expr'], $class, $method);
        break;
      case 'object-property-array-index-append':
        $this->_append(["\${$assign['variable']}", '->', $assign['property']]);
        $this->_statementAssignArrayIndex($assign['index-expr'], $class, $method);
        $this->_append(['[', ']']);
        break;
      case 'static-property':
        $this->_append([$assign['variable'], '::', "\${$assign['property']}"]);
        break;
      case 'static-property-append':
        $this->_append([$assign['variable'], '::', "\${$assign['property']}", '[', ']']);
        break;
      case 'static-property-array-index':
        $this->_append([$assign['variable'], '::', "\${$assign['property']}"]);
        $this->_statementAssignArrayIndex($assign['index-expr'], $class, $method);
        break;
      case 'static-property-array-index-append':
        $this->_append([$assign['variable'], '::', "\${$assign['property']}"]);
        $this->_statementAssignArrayIndex($assign['index-expr'], $class, $method);
        $this->_append(['[', ']']);
        break;
      default:
        throw new \Exception("Unhandled Assignment Type [{$assign['assign-type']}] in line [{$assign['line']}]");
    }

    // PROCESS ASSIGNMENT OPERATOR
    switch ($assign['operator']) {
      case 'assign':
        $this->_append('=');
        break;
      case "concat-assign":
        $this->_append('.=');
        break;
      case 'mul-assign':
        $this->_append('*=');
        break;
      case 'add-assign':
        $this->_append('+=');
        break;
      case 'sub-assign':
        $this->_append('.=');
        break;
      default:
        throw new \Exception("Unhandled assignment operator  [{$assign['operator']}] in line [{$assign['line']}]");
    }

    // PROCESS R.H.S Expression
    $this->_processExpression($assign['expr'], $class, $method);
    $this->_appendEOS();
  }

  protected function _statementAssignArrayIndex($index, $class, $method) {
    $this->_append('[');
    $first = true;
    foreach ($index as $index) {
      if (!$first) {
        $this->_append([']', '[']);
      }
      $this->_processExpression($index, $class, $method);
      $first = false;
    }
    $this->_append(']');
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
    $this->_append(['foreach', '(']);
    $this->_processExpression($for['expr'], $class, $method);
    $this->_append('as');
    if (isset($key)) {
      $this->_append("\${$key}", '=>', "\${$value}");
    } else {
      $this->_append("\${$value}");
    }
    $this->_append(')');

    // TODO: Move to the Flag to Configuration File
    $config_forLFStartBlock = true; // method '{' on new line?
    $this->_flush($config_forLFStartBlock);
    $this->_append('{', true);

    /* TODO 
     * 1. Optimization, if an 'for' has no statements we should just use a ';' rather than a '{ }' pair
     * 2. Optimization, if an 'for' has no statements, than maybe it is 'dead code' and should be removed
     * NOTE: this requires that the test expression has no side-effects (i.e. assigning within an if, function call, etc.)
     */
    /*
     * BODY
     */
    $this->_indent();

    if (isset($for['statements'])) {
      $this->_processStatementBlock($for['statements'], $class, $method);
    }

    // Garauntee that we flush any pending lines
    $this->_flush();
    $this->_unindent();

    /*
     * FOOTER
     */
    $this->_append('}', true);
  }

  protected function _statementWhile($while, $class, $method) {
    /*
     * HEADER
     */
    $this->_append(['while', '(']);
    $this->_processExpression($while['expr'], $class, $method);
    $this->_append(')');

    // TODO: Move to the Flag to Configuration File
    $config_forLFStartBlock = true; // method '{' on new line?
    $this->_flush($config_forLFStartBlock);
    $this->_append('{', true);

    /*
     * BODY
     */
    $this->_indent();

    if (isset($while['statements'])) {
      $this->_processStatementBlock($while['statements'], $class, $method);
    }

    // Garauntee that we flush any pending lines
    $this->_flush();
    $this->_unindent();
    /*
     * FOOTER
     */
    $this->_append('}', true);
  }

  protected function _statementLoop($loop, $class = null, $method = null) {
    /*
     * HEADER
     */
    $this->_append('do');

    // TODO: Move to the Flag to Configuration File
    $config_dowhileLFStartBlock = false; // method '{' on new line?
    if ($config_dowhileLFStartBlock) {
      $this->_flush();
    }
    $this->_append('{', true);

    /*
     * BODY
     */
    $this->_indent();

    if (isset($loop['statements'])) {
      $this->_processStatementBlock($loop['statements'], $class, $method);
    }

    // Garauntee that we flush any pending lines
    $this->_flush();
    $this->_unindent();

    /*
     * FOOTER
     */
    $this->_append(['}', 'while', '(', 'true', ')']);
    $this->_appendEOS();
  }

  protected function _statementDoWhile($dowhile, $class = null, $method = null) {
    /*
     * HEADER
     */
    $this->_append('do');

    // TODO: Move to the Flag to Configuration File
    $config_dowhileLFStartBlock = false; // method '{' on new line?
    if ($config_dowhileLFStartBlock) {
      $this->_flush();
    }
    $this->_append('{', true);

    /*
     * BODY
     */
    $this->_indent();

    if (isset($dowhile['statements'])) {
      $this->_processStatementBlock($dowhile['statements'], $class, $method);
    }

    // Garauntee that we flush any pending lines
    $this->_flush();
    $this->_unindent();

    /*
     * FOOTER
     */
    $this->_append(['}', 'while', '(']);
    $this->_processExpression($dowhile['expr'], $class, $method);
    $this->_append(')');
    $this->_appendEOS();
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
        $this->_append('else');
        $this->_statementIfExpression($else_if, $class, $method);
      }
    }

    /* ELSE { statements } */
    if (isset($if['else_statements'])) {
      $this->_append(['else', '{'], true);
      $this->_indent();

      /* ELSE { statements } */
      $this->_processStatementBlock($if['else_statements'], $class, $method);

      // Garauntee that we flush any pending lines
      $this->_flush();
      $this->_unindent();
      $this->_append('}', true);
    }
  }

  protected function _statementIfExpression($if_expr, $class = null, $method = null) {
    $this->_append(['if', '(']);
    // TODO: Move to the Flag to Configuration File
    $config_ifLFExpressions = true; // Function Parameters on new line?
    $this->_flush($config_ifLFExpressions);
    $this->_indent($config_ifLFExpressions);

    $this->_processExpression($if_expr['expr'], $class, $method);

    $this->_flush($config_ifLFExpressions);
    $this->_unindent($config_ifLFExpressions);
    $this->_append(')');

    $config_ifLFStartBlock = true; // '{' on new line?
    $this->_flush($config_ifLFStartBlock);
    $this->_append('{', true);

    /* IF { statements } */
    $this->_indent();

    if (isset($if_expr['statements'])) {
      $this->_processStatementBlock($if_expr['statements'], $class, $method);
    }

    // Garauntee that we flush any pending lines
    $this->_flush();
    $this->_unindent();
    $this->_append('}', true);
  }

  protected function _statementSwitch($switch, $class = null, $method = null) {
    // HEADER
    $this->_append(['switch', '(']);
    // TODO: Move to the Flag to Configuration File
    $config_switchLFExpressions = true; // Function Parameters on new line?
    $this->_flush($config_switchLFExpressions);
    $this->_indent($config_switchLFExpressions);

    $this->_processExpression($switch['expr'], $class, $method);

    $this->_flush($config_switchLFExpressions);
    $this->_unindent($config_switchLFExpressions);
    $this->_append(')');

    $config_switchLFStartBlock = true; // '{' on new line?
    $this->_flush($config_switchLFStartBlock);
    $this->_append('{', true);
    $this->_indent();

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
            $this->_append('case');
            $this->_processExpression($clause['expr'], $class, $method);
            $this->_append(':', true);
            break;
          case 'default':
            $this->_append(['default', ':'], true);
            break;
          default:
            throw new \Exception("Unexpected SWITCH Clause Type [{$clause['type']}] in line [{$assign['line']}]");
        }

        // Do we have statements for the clause?
        if (isset($clause['statements']) && count($clause['statements'])) { // YES : Process
          $this->_indent();
          $this->_processStatementBlock($clause['statements'], $class, $method);
          $this->_unindent();
        }
      }
    }

    // FOOTER
    $this->_flush();
    $this->_unindent();
    $this->_append('}', true);
  }

  protected function _statementTryCatch($trycatch, $class = null, $method = null) {
    // BODY : CATCH CLAUSES
    /* TODO 
     * 1. Optimization, if an 'try' has no clauses we should just use a ';' rather than a '{ }' pair
     * 2. Optimization, if an 'try' has no clauses, than maybe it is 'dead code' and should be removed
     * NOTE: this requires that the test expression has no side-effects (i.e. assigning within an if, function call, etc.)
     */
    /* TRY HEADER */
    $this->_append('try');

    // TODO: Move to the Flag to Configuration File
    $config_tryLFStartBlock = false; // method '{' on new line?
    if ($config_tryLFStartBlock) {
      $this->_flush();
    }
    $this->_append('{', true);

    /* TRY BODY */
    $this->_indent();
    $this->_processStatementBlock($trycatch['statements'], $class, $method);

    // Garauntee that we flush any pending lines
    $this->_flush();
    $this->_unindent();

    /*
     * TRY FOOTER
     */
    $this->_append('}');

    // BODY : CATCH CLAUSES
    /* TODO 
     * 1. Optimization, if an 'catch' has no clauses we should just use a ';' rather than a '{ }' pair
     * 2. Optimization, if an 'catch' has no clauses, than maybe it is 'dead code' and should be removed
     * NOTE: this requires that the test expression has no side-effects (i.e. assigning within an if, function call, etc.)
     */
    foreach ($trycatch['catches'] as $catch) {
      /* CATCH HEADER */
      $this->_append(['catch', '(']);
      $this->_property = true;
      $this->_processExpression($catch['class'], $class, $method);
      $this->_property = false;
      $this->_processExpression($catch['variable'], $class, $method);
      $this->_append(')', true);

      // TODO: Move to the Flag to Configuration File
      $config_tryLFStartBlock = false; // method '{' on new line?
      if ($config_tryLFStartBlock) {
        $this->_flush();
      }
      $this->_append('{', true);

      /* CATCH BODY */
      $this->_indent();
      $this->_processStatementBlock($catch['statements'], $class, $method);

      // Garauntee that we flush any pending lines
      $this->_flush();
      $this->_unindent();

      /* TRY FOOTER */
      $this->_append('}', true);
    }
  }

  protected function _statementContinue($continue, $class = null, $method = null) {
    $this->_append('continue');
    $this->_appendEOS();
  }

  protected function _statementBreak($break, $class = null, $method = null) {
    $this->_append('break');
    $this->_appendEOS();
  }

  protected function _statementReturn($return, $class = null, $method = null) {
    $this->_append('return');
    $this->_processExpression($return['expr'], $class, $method);
    $this->_appendEOS();
  }

  protected function _statementThrow($throw, $class = null, $method = null) {
    $this->_append('throw');
    $this->_processExpression($throw['expr'], $class, $method);
    $this->_appendEOS();
  }

  protected function _statementUnset($unset, $class = null, $method = null) {
    $this->_append(['unset', '(']);
    $this->_processExpression($unset['expr'], $class, $method);
    $this->_append(')');
    $this->_appendEOS();
  }

  protected function _statementEcho($echo, $class = null, $method = null) {
    $this->_append('echo');
    $first = true;
    foreach ($echo['expressions'] as $expression) {
      if (!$first) {
        $this->_append('.');
      }
      $this->_processExpression($expression, $class, $method);
      $first = false;
    }
    $this->_appendEOS();
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

  protected function _emitRequire($ast) {
    /* TODO
     * Zephir isset does more than the normal php isset
     */
    $left = $ast['left'];
    echo 'require ';
    $this->_redirectAST($left['type'], $left);
  }

  /**
   * Class Static Method Call
   * 
   * @param type $ast
   */
  protected function _expressionScall($call, $class = null, $method = null) {
    $this->_append([$call['class'], '::', $call['name'], '(']);
    if (count($call['parameters'])) {
      $first = true;
      foreach ($call['parameters'] as $parameter) {
        if (!$first) {
          $this->_append(',');
        }
        $this->_processExpression($parameter, $class, $method);
        $first = false;
      }
    }
    $this->_append(')');
  }

  /**
   * Class Method Call
   * 
   * @param type $ast
   */
  protected function _expressionMcall($call, $class = null, $method = null) {
    $this->_processExpression($call['variable']);
    $this->_append(['->', $call['name'], '(']);
    if (count($call['parameters'])) {
      $first = true;
      foreach ($call['parameters'] as $parameter) {
        if (!$first) {
          $this->_append(',');
        }
        $this->_processExpression($parameter, $class, $method);
        $first = false;
      }
    }
    $this->_append(')');
  }

  /**
   * Function Call
   * 
   * @param type $ast
   */
  protected function _expressionFcall($call, $class = null, $method = null) {
    $this->_append([$call['name'], '(']);
    if (count($call['parameters'])) {
      $first = true;
      foreach ($call['parameters'] as $parameter) {
        if (!$first) {
          $this->_append(',');
        }
        $this->_processExpression($parameter, $class, $method);
        $first = false;
      }
    }
    $this->_append(')');
  }

  protected function _expressionNew($new, $class, $method) {
    $this->_append(['new', $new['class']]);
    if (isset($new['parameters'])) {
      $this->_append('(');

      // TODO: Move to the Flag to Configuration File
      $config_callLFParameters = true; // Function Parameters on new line?
      $this->_flush($config_callLFParameters);
      $this->_indent($config_callLFParameters);

      $first = true;
      foreach ($new['parameters'] as $parameter) {
        if (!$first) {
          $this->_append(',');
          $this->_flush($config_callLFParameters);
        }
        $this->_processExpression($parameter, $class, $method);
        $first = false;
      }
      $this->_flush($config_callLFParameters);
      $this->_unindent($config_callLFParameters);
      $this->_append(')');
    }
  }

  protected function _expressionIsset($isset, $class, $method) {
    $left = $isset['left'];
    switch ($left['type']) {
      case 'array-access':
        $this->_append(['zephir_isset_array', '(']);
        $this->_processExpression($left['left'], $class, $method);
        $this->_append(',');
        $this->_processExpression($left['right'], $class, $method);
        $this->_append(')');
        break;
      case 'property-access':
      case 'property-string-access':
        $this->_append(['zephir_isset_property', '(']);
        $this->_processExpression($left['left'], $class, $method);
        $this->_append(',');
        $right = $left['right'];
        switch ($right['type']) {
          case 'variable':
          case 'string':
            $this->_append("'{$right['value']}'");
            break;
          default:
            throw new \Exception("TODO - 1 - isset([{$right['type']}])");
        }
        $this->_append(')');
        break;
      case 'property-dynamic-access':
        $this->_append(['zephir_isset_property', '(']);
        $this->_processExpression($left['left'], $class, $method);
        $this->_append(',');
        $this->_processExpression($left['right'], $class, $method);
        $this->_append(')');
        break;
      case 'static-property-access':
        // TODO Verify if this is what zephir does for static access
        $this->_append(['isset', '(']);
        $this->_property = true;
        $this->_processExpression($left['left'], $class, $method);
        $this->_property = false;
        $this->_append('::');
        $this->_processExpression($left['right'], $class, $method);
        $this->_append(')');
        break;
      default:
        throw new \Exception("TODO - 2 - isset([{$type}]) in line [{$isset['line']}]");
    }
  }

  protected function _expressionTypeof($typeof, $class, $method) {
    $this->_append(['gettype', '(']);
    $this->_processExpression($typeof['left'], $class, $method);
    $this->_append(')');
  }

  protected function _expressionParameter($parameter, $class, $method) {
    $this->_append("\${$parameter['name']}");
    if (isset($parameter['default'])) {
      $this->_append('=');
      $this->_processExpression($parameter['default'], $class);
    }
  }

  protected function _expressionArrayAccess($expression, $class = null, $method = null) {
    $left = $expression['left'];
    $right = $expression['right'];
    $this->_processExpression($left, $class, $method);
    $this->_append('[');
    $this->_processExpression($right, $class, $method);
    $this->_append(']');
  }

  protected function _expressionPropertyAccess($expression, $class = null, $method = null) {
    $left = $expression['left'];
    $right = $expression['right'];
    $this->_processExpression($left, $class, $method);
    $this->_append('->');
    // Flag the Next Expression as Property Expression
    $this->_property = true;
    $this->_processExpression($right, $class, $method);
    $this->_property = false;
  }

  protected function _expressionPropertyDynamicAccess($expression, $class = null, $method = null) {
    $left = $expression['left'];
    $right = $expression['right'];
    $this->_processExpression($left, $class, $method);
    $this->_append('->');
    // Flag the Next Expression as Property Expression
    $this->_processExpression($right, $class, $method);
  }

  protected function _expressionStaticPropertyAccess($expression, $class = null, $method = null) {
    $left = $expression['left'];
    $right = $expression['right'];
    $this->_property = true;
    $this->_processExpression($left, $class, $method);
    $this->_property = false;
    $this->_append('::');
    $this->_processExpression($right, $class, $method);
  }

  protected function _expressionStaticConstantAccess($expression, $class = null, $method = null) {
    $left = $expression['left'];
    $right = $expression['right'];
    $this->_property = true;
    $this->_processExpression($left, $class, $method);
    $this->_append('::');
    $this->_processExpression($right, $class, $method);
    $this->_property = false;
  }

  protected function _expressionCast($cast, $class, $method) {
    $this->_append(['(', $cast['left'], ')']);
    $this->_processExpression($cast['right'], $class, $method);
  }

  protected function _expressionVariable($variable, $class, $method) {
    if ($this->_property) {
      $this->_append($variable['value']);
    } else {
      $this->_append("\${$variable['value']}");
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
    $this->_append(['function', '(']);
    if (count($closure['parameters'])) {
      // TODO: Move to the Flag to Configuration File
      $config_methodLFParameters = true; // Function Parameters on new line?
      $this->_flush($config_methodLFParameters);
      $this->_indent($config_methodLFParameters);

      $first = true;
      foreach ($closure['parameters'] as $parameter) {
        if (!$first) {
          $this->_append(',');
          $this->_flush($config_methodLFParameters);
        }
        $this->_processExpression($parameter, $class, $method);
        $first = false;
      }
      $this->_flush($config_methodLFParameters);
      $this->_unindent($config_methodLFParameters);
    }
    $this->_append(')');

    // TODO: Move to the Flag to Configuration File
    $config_methodLFStartBlock = true; // method '{' on new line?
    $this->_flush($config_methodLFStartBlock);
    $this->_append('{', true);

    /*
     * METHOD BODY
     */
    $this->_indent();

    if (isset($closure['statements'])) {
      $this->_processStatementBlock($closure['statements'], $class, $method);
    }

    // Garauntee that we flush any pending lines
    $this->_flush();
    $this->_unindent();

    /*
     * METHOD FOOTER
     */
    $this->_append('}', true);
  }

  /*
   * EXPRESSION OPERATORS
   */

  protected function _emitOperator($left, $operator, $right, $class, $method) {
    $this->_processExpression($left, $class, $method);
    $this->_append($operator);
    $this->_processExpression($right, $class, $method);
  }

  protected function _expressionList($list, $class, $method) {
    $this->_append('(');
    $this->_processExpression($list['left'], $class, $method);
    $this->_append(')');
  }

  protected function _expressionBitwiseNot($bitwise_not, $class, $method) {
    $this->_append('~');
    $this->_processExpression($bitwise_not['left'], $class, $method);
  }

  protected function _expressionMinus($minus, $class, $method) {
    $this->_append('-');
    $this->_processExpression($minus['left'], $class, $method);
  }

  protected function _expressionPlus($plus, $class, $method) {
    $this->_append('+');
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
    $this->_append('!');
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
    $this->_emitOperator($operation['left'], 'instanceof', $operation['right'], $class, $method);
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
    $this->_append($ast['value']);
  }

  protected function _expressionInt($ast, $class = null, $method = null) {
    $this->_append($ast['value']);
  }

  protected function _expressionBool($ast, $class = null, $method = null) {
    $this->_append(strtoupper($ast['value']));
  }

  protected function _expressionNull($ast, $class = null, $method = null) {
    $this->_append('NULL');
  }

  protected function _expressionString($ast, $class = null, $method = null) {
    $this->_append("\"{$ast['value']}\"");
  }

  protected function _expressionChar($ast, $class = null, $method = null) {
    $this->_append("'{$ast['value']}'");
  }

  protected function _expressionArray($array, $class = null, $method = null) {
    // OPEN ARRAY
    $this->_append('[');

    // PROCESS ARRAY ELEMENTS
    $first = true;
    foreach ($array['left'] as $entry) {
      if (!$first) {
        $this->_append(',');
      }
      $key = isset($entry['key']) ? $entry['key'] : null;
      if (isset($key)) {
        $this->_processExpression($key, $class, $method);
        $this->_append('=>');
      }
      $this->_processExpression($entry['value'], $class, $method);
      $first = false;
    }

    // CLOSE ARRAY
    $this->_append(']');
  }

  protected function _expressionEmptyArray($array, $class = null, $method = null) {
    $this->_append('[]');
  }

  protected function _expressionConstant($constant, $class = null, $method = null) {
    $this->_append($constant['value']);
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
