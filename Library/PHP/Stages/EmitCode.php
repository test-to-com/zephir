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
  protected $_right = false;

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
      throw new Exception("Indentation Level CANNOT BE less than ZERO");
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
    $this->_append(['namespace', "{$namespace['name']};"], true);
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
    $this->flush();
    $this->_append(["interface", $interface['name']]);

    // TODO: Move to the Flag to Configuration File
    $config_interfaceLFExtends = true; // Seperate interface / extends with line-feed

    $extends = isset($interface['extends']) ? $interface['extends'] : null;
    if (isset($extends)) {
      $this->_flush($config_interfaceLFExtends);
      $this->_indent($config_interfaceLFExtends);
      $this->_append(['extends', $extends]);
      $this->_unindent($config_interfaceLFExtends);
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
        $handler = $this->_handlerName('_emitClass', $section);
        if (method_exists($this, $handler)) {
          $this->$handler($interface, $section);
        } else {
          throw new \Exception("Unhandled section type [{$section}]");
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
      echo "/{$method['docblock']}/\n";
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
    /* STATEMENT Method Calls Have a Nested AST Structure, which diferentiate
     * it from EXPRESSION Method Calls (i.e. all statements, have expressions
     * and an expression can be used as part of statements)
      [ 'type' => 'mcall',
      'expr' => [
      'type' => 'mcall'
     */

    $this->_expressionFcall($call['expr'], $class, $method);
    $this->_appendEOS();
  }

  protected function _statementAssign($assign, $class, $method) {
    /* STATEMENT Method Calls Have a Nested AST Structure, which diferentiate
     * it from EXPRESSION Method Calls (i.e. all statements, have expressions
     * and an expression can be used as part of statements)
      [ 'type' => 'mcall',
      'expr' => [
      'type' => 'mcall'
     */

    $this->_append("\${$assign['variable']}");
    $this->_append('=');
    $this->_processExpression($assign['expr'], $class);
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

  protected function _emitStatementLoop($ast) {
    echo "while(true) {\n";
    $statements = isset($ast['statements']) ? $ast['statements'] : null;
    if (isset($statements)) {
      $this->_emitStatements($statements);
    }
    echo "}\n";
  }

  protected function _emitStatementWhile($ast) {
    echo "while(";
    $expr = $ast['expr'];
    $this->_redirectAST($expr['type'], $expr);
    echo ") {\n";
    $statements = isset($ast['statements']) ? $ast['statements'] : null;
    if (isset($statements)) {
      $this->_emitStatements($statements);
    }
    echo "}\n";
  }

  protected function _emitStatementDoWhile($ast) {
    echo "do {\n";
    $statements = isset($ast['statements']) ? $ast['statements'] : null;
    if (isset($statements)) {
      $this->_emitStatements($statements);
    }
    echo "} while (";
    // TODO If expr === 'list' don't place a leading '(' and trailing '(' as the list will add those
    $expr = $ast['expr'];
    $this->_redirectAST($expr['type'], $expr);
    echo ");\n";
  }

  protected function _emitStatementFor($ast) {
    // TODO Handle 'reverse'
    // TODO Handle 'anonymous variable' i.e. key, _
    // TODO Handle range() i.e.: range(1, 10)
    // TODO over Strings
    // TODO from flow.zep : for _ in range(1, 10) (No Key, No Value)
    $key = isset($ast['key']) ? ($ast['key'] !== '_' ? $ast['key'] : null) : null;
    $value = isset($ast['value']) ? $ast['value'] : null;
    $reverse = isset($ast['reverse']) ? $ast['reverse'] : false;
    $source = $ast['expr'];
    echo "foreach(";
    if ($reverse) {
      echo 'array_reverse(';
    }
    $this->_emitExpression($source);
    if ($reverse) {
      echo ')';
    }
    echo ' as ';
    if (isset($key)) {
      echo "\${$key} => \${$value}) {\n";
    } else {
      echo "\${$value}) {\n";
    }
    /* TODO 
     * 1. Optimization, if an 'for' has no statements we should just use a ';' rather than a '{ }' pair
     * 2. Optimization, if an 'for' has no statements, than maybe it is 'dead code' and should be removed
     * NOTE: this requires that the test expression has no side-effects (i.e. assigning within an if, function call, etc.)
     */
    $statements = isset($ast['statements']) ? $ast['statements'] : null;
    if (isset($statements)) {
      $this->_emitStatements($statements);
    }
    echo "}\n";
  }

  protected function _emitStatementIf($ast) {
    echo "if(";
    $expr = $ast['expr'];
    $fetch = $expr['type'] === 'fetch';
    if ($fetch) {
      $this->_emitFetchCheck($expr);
    } else {
      $this->_redirectAST($expr['type'], $expr);
    }
    echo ") {\n";
    if ($fetch) {
      $this->_emitFetchAssign($expr);
    }

    /* TODO 
     * 1. Optimization, if an 'for' has no statements we should just use a ';' rather than a '{ }' pair
     * 2. Optimization, if an 'for' has no statements, than maybe it is 'dead code' and should be removed
     * NOTE: this requires that the test expression has no side-effects (i.e. assigning within an if, function call, etc.)
     */
    $statements = isset($ast['statements']) ? $ast['statements'] : null;
    if (isset($statements)) {
      $this->_emitStatements($statements);
    }

    // Do we have an else clause?
    $else = isset($ast['else_statements']) ? $ast['else_statements'] : null;
    if (isset($else)) { // YES
      echo "} else {\n";
      $this->_emitStatements($else);
    }

    echo "}\n";
  }

  protected function _emitStatementSwitch($ast) {
    echo "switch(";
    $expr = $ast['expr'];
    $this->_redirectAST($expr['type'], $expr);
    echo ") {\n";

    /* TODO 
     * 1. Optimization, if an 'switch' has no clauses we should just use a ';' rather than a '{ }' pair
     * 2. Optimization, if an 'switch' has no clauses, than maybe it is 'dead code' and should be removed
     * NOTE: this requires that the test expression has no side-effects (i.e. assigning within an if, function call, etc.)
     */
    $clauses = isset($ast['clauses']) ? $ast['clauses'] : null;
    if (isset($clauses)) {
      $this->_emitClauses($clauses);
    }

    echo "}\n";
  }

  protected function _emitClauses($astclauses) {
    // TODO : Handle Scenario when 'default' is not the last clause (should be error)
    foreach ($astclauses as $astclause) {
      $this->_redirectAST($astclause['type'], $astclause, 'clause');
    }
  }

  protected function _emitClauseCase($ast) {
    echo "case ";
    $expr = $ast['expr'];
    $this->_redirectAST($expr['type'], $expr);
    echo ":\n";
    $statements = isset($ast['statements']) ? $ast['statements'] : null;
    if (isset($statements)) {
      $this->_emitStatements($statements);
    }
  }

  protected function _emitClauseDefault($ast) {
    echo "default:\n";
    $statements = isset($ast['statements']) ? $ast['statements'] : null;
    if (isset($statements)) {
      $this->_emitStatements($statements);
    }
  }

  protected function _statementClosure($closure, $class = null, $method = null) {
    $this->_append('continue');
    $this->_appendEOS();
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

  protected function _emitStatementEcho($ast) {
    $expressions = $ast['expressions'];
    echo 'echo ';
    $first = true;
    foreach ($expressions as $expr) {
      if (!$first) {
        echo '.';
      }
      $this->_emitExpression($expr);
    }
    echo ";\n";
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

  protected function _emitFetchCheck($ast) {
    $right = $ast['right'];
    echo 'isset(';
    $this->_redirectAST($right['type'], $right);
    echo ')';
  }

  protected function _emitFetchAssign($ast) {
    $left = $ast['left'];
    $right = $ast['right'];
    $this->_emitVariable($left);
    echo '=';
    $this->_redirectAST($right['type'], $right);
    echo ";\n";
  }

  protected function _emitReference($ast) {
    throw new \Exception('TODO');
  }

  protected function _emitNot($ast) {
    $left = $ast['left'];
    echo '!';
    $this->_redirectAST($left['type'], $left);
  }

  protected function _emitBitwiseNot($ast) {
    $left = $ast['left'];
    echo '~';
    $this->_redirectAST($left['type'], $left);
  }

  protected function _emitMinus($ast) {
    $left = $ast['left'];

    echo '-';
    $this->_redirectAST($left['type'], $left);
  }

  protected function _emitPlus($ast) {
    $left = $ast['left'];

    echo '+';
    $this->_redirectAST($left['type'], $left);
  }

  protected function _emitIsset($ast) {
    /* TODO
     * Zephir isset does more than the normal php isset
     */
    $left = $ast['left'];
    $type = $left['type'];
    switch ($type) {
      case 'array-access':
        echo 'zephir_isset_array(';
        $this->_emitVariable($left['left']);
        echo ', ';
        $this->_redirectAST($left['right']['type'], $left['right']);
        echo ')';
        break;
      case 'property-access':
      case 'property-string-access':
        echo 'zephir_isset_property(';
        $this->_emitVariable($left['left']);
        echo ', \'';
        switch ($left['right']['type']) {
          case 'variable':
            $this->_emitVariable($left['right'], true);
            break;
          case 'string':
            echo $left['right']['value'];
            break;
          default:
            throw new \Exception("TODO - 1 - isset([{$type}])");
        }
        echo '\')';
        break;
      case 'property-dynamic-access':
        echo 'zephir_isset_property(';
        $this->_emitVariable($left['left']);
        echo ', ';
        $this->_redirectAST($left['right']['type'], $left['right']);
        echo ')';
        break;
      default:
        throw new \Exception("TODO - 2 - isset([{$type}])");
    }
  }

  protected function _emitRequire($ast) {
    /* TODO
     * Zephir isset does more than the normal php isset
     */
    $left = $ast['left'];
    echo 'require ';
    $this->_redirectAST($left['type'], $left);
  }

  protected function _emitEmpty($ast) {
    /* TODO
     * Implement Zephir Empty
     */
    $left = $ast['left'];
    echo 'zephir_isempty(';
    $this->_redirectAST($left['type'], $left);
    echo ')';
  }

  protected function _emitLikely($ast) {
    // TODO Use this as an Optimization Hint
    /* THIS IS ONLY AN OPTIMIZATION HINT : Don't know if it's of any use in PHP */
  }

  protected function _emitUnlikely($ast) {
    // TODO Use this as an Optimization Hint
    /* THIS IS ONLY AN OPTIMIZATION HINT : Don't know if it's of any use in PHP */
  }

  protected function _emitEquals($ast) {
    $left = $ast['left'];
    $right = $ast['right'];

    $this->_redirectAST($left['type'], $left);
    echo ' == ';
    $this->_redirectAST($right['type'], $right);
  }

  protected function _emitNotEquals($ast) {
    $left = $ast['left'];
    $right = $ast['right'];

    $this->_redirectAST($left['type'], $left);
    echo ' != ';
    $this->_redirectAST($right['type'], $right);
  }

  protected function _emitIdentical($ast) {
    $left = $ast['left'];
    $right = $ast['right'];

    $this->_redirectAST($left['type'], $left);
    echo ' === ';
    $this->_redirectAST($right['type'], $right);
  }

  protected function _emitNotIdentical($ast) {
    $left = $ast['left'];
    $right = $ast['right'];

    $this->_redirectAST($left['type'], $left);
    echo ' !== ';
    $this->_redirectAST($right['type'], $right);
  }

  protected function _emitLess($ast) {
    $left = $ast['left'];
    $right = $ast['right'];

    $this->_redirectAST($left['type'], $left);
    echo ' < ';
    $this->_redirectAST($right['type'], $right);
  }

  protected function _emitGreater($ast) {
    $left = $ast['left'];
    $right = $ast['right'];

    $this->_redirectAST($left['type'], $left);
    echo ' > ';
    $this->_redirectAST($right['type'], $right);
  }

  protected function _emitLessEqual($ast) {
    $left = $ast['left'];
    $right = $ast['right'];

    $this->_redirectAST($left['type'], $left);
    echo ' <= ';
    $this->_redirectAST($right['type'], $right);
  }

  protected function _emitGreaterEqual($ast) {
    $left = $ast['left'];
    $right = $ast['right'];

    $this->_redirectAST($left['type'], $left);
    echo ' >= ';
    $this->_redirectAST($right['type'], $right);
  }

  protected function _emitList($ast) {
    $left = $ast['left'];

    echo '(';
    $this->_redirectAST($left['type'], $left);
    echo ')';
  }

  protected function _emitCast($ast) {
    /* $left = $ast['left']; : Represents Hint, which we don't choose */
    $right = $ast['right'];
    $this->_redirectAST($right['type'], $right);
  }

  protected function _emitTypeHint($ast) {
    /* $left = $ast['left']; : Represents Hint, which we don't choose */
    $right = $ast['right'];
    $this->_redirectAST($right['type'], $right);
  }

  protected function _emitPropertyAccess($ast) {
    $left = $ast['left'];
    $right = $ast['right'];

    $this->_emitVariable($left);
    echo '->';
    $this->_emitVariable($right, true);
  }

  protected function _emitPropertyDynamicAccess($ast) {
    $left = $ast['left'];
    $right = $ast['right'];

    $this->_emitVariable($left, true);
    echo '->';
    $this->_emitVariable($right);
  }

  protected function _emitPropertyStringAccess($ast) {
    $left = $ast['left'];
    $right = $ast['right'];

    echo 'zephir_read_property(';
    $this->_emitVariable($left);
    echo ', \'';
    switch ($right['type']) {
      case 'string':
        echo $right['value'];
        break;
      default:
        throw new \Exception("TODO - 1 - _emitPropertyStringAccess");
    }
    echo '\')';
  }

  protected function _emitStaticPropertyAccess($ast) {
    $left = $ast['left'];
    $right = $ast['right'];

    $this->_emitVariable($left, true);
    echo '::';
    $this->_emitVariable($right);
  }

  protected function _emitStaticConstantAccess($ast) {
    $left = $ast['left'];
    $right = $ast['right'];

    $this->_emitVariable($left, true);
    echo '::';
    $this->_emitVariable($right);
  }

  protected function _emitArrayAccess($ast) {
    $left = $ast['left'];
    $right = $ast['right'];

    $this->_redirectAST($left['type'], $left);
    echo '[';
    // TODO: Handle Situations in which the right side is a string and not a variable (or int, etc).
    $this->_emitVariable($right, true);
    echo ']';
  }

  protected function _emitIRange($ast) {
    $left = $ast['left'];
    $right = $ast['right'];

    echo 'range(';
    $this->_redirectAST($left['type'], $left);
    echo ',';
    $this->_redirectAST($right['type'], $right);
    echo ')';
  }

  protected function _emitERange($ast) {
    $left = $ast['left'];
    $right = $ast['right'];

    echo 'array_shift(array_pop(range(';
    $this->_redirectAST($left['type'], $left);
    echo ',';
    $this->_redirectAST($right['type'], $right);
    echo ')))';
  }

  protected function _emitTypeof($ast) {
    $left = $ast['left'];
    echo 'gettype(';
    $this->_redirectAST($left['type'], $left);
    echo ')';
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
   * Class Method Call
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

  protected function _expressionParameter($parameter, $class, $method) {
    $this->_append("\${$parameter['name']}");
    if (isset($parameter['default'])) {
      $this->_append('=');
      $this->_processExpression($parameter['default'], $class);
    }
  }

  protected function _expressionPropertyAccess($expression, $class, $method) {
    $left = $expression['left'];
    $right = $expression['right'];
    $this->_processExpression($left, $class, $method);
    $this->_append('->');
    // Flag the Next Expression as Right Expression
    $this->_right = true;
    $this->_processExpression($right, $class, $method);
    $this->_right = false;
  }

  protected function _expressionVariable($variable, $class, $method) {
    if ($this->_right) {
      $this->_append($variable['value']);
    } else {
      $this->_append("\${$variable['value']}");
    }
  }

  protected function _emitMcallString($ast) {
    $map = [
      'index' => 'strpos',
      'trim' => 'trim',
      'trimleft' => 'ltrim',
      'trimright' => 'rtrim',
      'length' => 'strlen',
      'lower' => 'strtolower',
      'upper' => 'strtoupper',
      'lowerfirst' => 'lcfirst',
      'upperfirst' => 'ucfirst',
      'format' => 'sprintf',
      'md5' => 'md5',
      'sha1' => 'sha1',
      'nl2br' => 'nl2br',
      'parsecsv' => 'str_getcsv',
      'parsejson' => 'json_decode',
      'tojson' => 'json_encode',
      'toutf8' => 'utf8_encode',
      'repeat' => 'str_repeat',
      'shuffle' => 'str_shuffle',
      'split' => 'str_split',
      'compare' => 'strcmp',
      'comparelocale' => 'strcoll',
      'rev' => 'strrev',
      'htmlspecialchars' => 'htmlspecialchars',
      'camelize' => 'camelize',
      'uncamelize' => 'uncamelize',
    ];
    $method = $ast['name'];
    if (!array_key_exists($method, $map)) {
      throw new \Exception("Function [_emitMcallString] - Method [{$method}] doesn't exist for String Object");
    }

    throw new \Exception("Function [_emitMcallString] - TODO Implement");
  }

  protected function _emitMcallArray($ast) {
    $map = [
      'join' => [
        'function' => 'join',
        'parameters' => '1*,O',
        'inplace' => false
      ],
      'reversed' => [
        'function' => 'array_reverse',
        'parameters' => 'O,*'
      ],
      'rev' => [
        'function' => 'array_reverse',
        'parameters' => 'O,*'
      ],
      'diff' => [
        'function' => 'array_diff',
        'parameters' => 'O,*'
      ],
      'flip' => [
        'function' => 'array_flip',
        'parameters' => 'O'
      ],
      'fill' => [
        'function' => 'array_fill',
        'parameters' => 'O,*'
      ],
      'walk' => [
        'function' => 'array_walk',
        'parameters' => 'O,*'
      ],
      'haskey' => [
        'function' => 'array_key_exists',
        'parameters' => 'O,*'
      ],
      'keys' => [
        'function' => 'array_keys',
        'parameters' => 'O,*'
      ],
      'values' => [
        'function' => 'array_values',
        'parameters' => 'O'
      ],
      'split' => [
        'function' => 'array_chunk',
        'parameters' => 'O,*'
      ],
      'combine' => [
        'function' => 'array_combine',
        'parameters' => '*'
      ],
      'intersect' => [
        'function' => 'array_intersect',
        'parameters' => 'O,*'
      ],
      'merge' => [
        'function' => 'array_merge',
        'parameters' => 'O,*'
      ],
      'mergerecursive' => [
        'function' => 'array_merge_recursive',
        'parameters' => 'O,*'
      ],
      'pad' => [
        'function' => 'array_pad',
        'parameters' => 'O,*'
      ],
      'pop' => [
        'function' => 'array_pop',
        'parameters' => 'O'
      ],
      'push' => [
        'function' => 'array_push',
        'parameters' => 'O,*'
      ],
      'rand' => [
        'function' => 'array_rand',
        'parameters' => 'O,*'
      ],
      'replace' => [
        'function' => 'array_replace',
        'parameters' => 'O,*'
      ],
      'map' => [
        'function' => 'array_map',
        'parameters' => '1,O',
      ],
      'replacerecursive' => [
        'function' => 'array_replace_recursive',
        'parameters' => 'O,*'
      ],
      'shift' => [
        'function' => 'array_shift',
        'parameters' => 'O'
      ],
      'slice' => [
        'function' => 'array_slice',
        'parameters' => 'O,*'
      ],
      'splice' => [
        'function' => 'array_splice',
        'parameters' => 'O,*'
      ],
      'sum' => [
        'function' => 'array_sum',
        'parameters' => 'O'
      ],
      'unique' => [
        'function' => 'array_unique',
        'parameters' => 'O,*'
      ],
      'prepend' => [
        'function' => 'array_unshift',
        'parameters' => 'O,*'
      ],
      'count' => [
        'function' => 'count',
        'parameters' => 'O,*'
      ],
      'current' => [
        'function' => 'current',
        'parameters' => 'O'
      ],
      'each' => [
        'function' => 'each',
        'parameters' => 'O'
      ],
      'end' => [
        'function' => 'end',
        'parameters' => 'O'
      ],
      'key' => [
        'function' => 'key',
        'parameters' => 'O'
      ],
      'next' => [
        'function' => 'next',
        'parameters' => 'O'
      ],
      'prev' => [
        'function' => 'prev',
        'parameters' => 'O'
      ],
      'reset' => [
        'function' => 'reset',
        'parameters' => 'O'
      ],
      'sort' => [
        'function' => 'sort',
        'parameters' => 'O,*'
      ],
      'sortbykey' => [
        'function' => 'ksort',
        'parameters' => 'O,*'
      ],
      'reversesort' => [
        'function' => 'rsort',
        'parameters' => 'O,*'
      ],
      'reversesortbykey' => [
        'function' => 'krsort',
        'parameters' => 'O,*'
      ],
      'shuffle' => [
        'function' => 'shuffle',
        'parameters' => 'O'
      ],
      'tojson' => [
        'function' => 'json_encode',
        'parameters' => 'O,*'
      ],
      'reduce' => [
        'function' => 'array_reduce',
        'parameters' => 'O,*',
      ],
    ];
    $method = $ast['name'];
    if (!array_key_exists($method, $map)) {
      throw new \Exception("Function [_emitMcallArray] - Method [{$method}] doesn't exist for Array Object");
    }

    $map_entry = $map[$method];
    $method = $map_entry['function'];
    $param_map = explode(',', $map_entry['parameters']);
    $parameters = isset($ast['parameters']) ? $ast['parameters'] : null;

    throw new \Exception("Function [_emitMcallArray] - TODO Implement");
  }

  protected function _emitFcall($ast) {
    echo "{$ast['name']}(";
    if (isset($ast['parameters'])) {
      $first = true;
      foreach ($ast['parameters'] as $parameter) {
        if (!$first) {
          echo ', ';
        }
        $parameter = $parameter['parameter'];
        $this->_redirectAST($parameter['type'], $parameter);
        $first = false;
      }
    }
    echo ")";
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
        $parameter = $parameter['parameter'];
        $this->_processExpression($parameter, $class, $method);
        $first = false;
      }
      $this->_flush($config_callLFParameters);
      $this->_unindent($config_callLFParameters);
      $this->_append(')');
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

  protected function _expressionConcat($operation, $class, $method) {
    $this->_emitOperator($operation['left'], '.', $operation['right'], $class, $method);
  }

  protected function _expressionAnd($operation, $class, $method) {
    $this->_emitOperator($operation['left'], '&&', $operation['right'], $class, $method);
  }

  protected function _expressionOr($operation, $class, $method) {
    $this->_emitOperator($operation['left'], '||', $operation['right'], $class, $method);
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

  protected function _expressionInstanceof($operation, $class, $method) {
    $this->_emitOperator($operation['left'], 'instanceof', $operation['right'], $class, $method);
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
      $this->_processExpression($entry, $class, $method);
      $first = false;
    }

    // CLOSE ARRAY
    $this->_append(']');
  }

  protected function _expressionEmptyArray($ast) {
    $this->_append('[]');
  }

  protected function _emitConstant($ast) {
    echo $ast['value'];
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
