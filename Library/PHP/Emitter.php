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

namespace Zephir\PHP;

use Zephir\API\FileSystem as IFileSystem;
use Zephir\API\Emitter as IEmitter;
use Zephir\API\DI;

/**
 * PHP Code Emitter (i.e. translate ZEP to PHP in ZEPHIR Context)
 * 
 * @author Paulo Ferreira <pf at sourcenotes.org>
 */
class Emitter implements IEmitter {

  protected $_di;

  public function setDI(DI $di) {
    $this->_di = $di;
  }

  public function getDI() {
    return $this->_di;
  }

  /**
   * Emit Code for all Files in a Project
   * 
   * @param type $path
   * @throws \Exception
   */
  public function project($path) {
    
  }

  /**
   * Emit Code for a Single File
   * 
   * @param type $path
   * @throws \Exception
   */
  public function file($path) {
    $fs = $this->_di['fileSystem'];

    $phpFile = $this->_genIR($path);

    $ir = $fs->requireFile($phpFile);
    $this->_compile($ir);
    return $ir;
  }

  /**
   * Emit Code for a Single File
   * 
   * @param mixed $path
   * @throws \Exception
   */
  public function files($files) {
    if (isset($files)) {
      if (is_string($files)) {
        $path = trim($files);
        if (strlen($path) === 0) {
          throw new \Exception("Source Path is empty");
        }

        // Get File System from Dependency Injector
        $fs = $this->_di['fileSystem'];

        // Emite PHP Code for all the ZEP files in the Directory
        $emitter = $this;
        $fs->enumerateFiles(function($path) use($emitter) {
          $count_path = strlen($path);
          $extension = $count_path > 4 ? strtolower(substr($path, $count_path - 4)) : null;
          if (isset($extension) && ($extension === '.zep')) {
            $emitter->file($path);
          }
          return true;
        });
      }
    }
  }

  /**
   * Compiles the file generating a JSON intermediate representation
   *
   * @param Compiler $compiler
   * @return array
   */
  protected function _genIR($zepFile) {
    // Get File System from Dependency Injector
    $fs = $this->_di['fileSystem'];

    // Does the ZEP File Exist?
    $zepRealPath = $fs->realpath($zepFile, IFileSystem::INPUT);
    if (!$fs->exists($zepRealPath)) { // NO
      throw new \Exception("Source File [{$zepRealPath}] doesn't exist");
    }

    // Create Normalized File Paths for the Parse Results
    $compilePath = $this->_compilePath($zepRealPath);
    $compilePathJS = $compilePath . ".js";
    $compilePathPHP = $compilePath . ".php";

    // Create Path to Zephir Binary
    if (PHP_OS == "WINNT") {
      $zephirParserBinary = $fs->realpath('bin\zephir-parser.exe', IFileSystem::SYSTEM);
    } else {
      $zephirParserBinary = $fs->realpath('bin/zephir-parser', IFileSystem::SYSTEM);
    }

    // Does it Exist?
    if (!$fs->exists($zephirParserBinary)) { // NO
      throw new \Exception($zephirParserBinary . ' was not found');
    }

    $changed = false;

    // Has the ZEP File already been Parsed (intermediate file JS exists)?
    if ($fs->exists($compilePathJS)) { // YES
      // Is it Older than the Source ZEP File, OR, are we using a New ZEP Parser?
      $modificationTime = $fs->modificationTime($compilePathJS);
      if ($modificationTime < $fs->modificationTime($zepRealPath) || $modificationTime < $fs->modificationTime($zephirParserBinary)) { // YES
        // Reparse the File
        $fs->system($zephirParserBinary . ' ' . $zepRealPath, 'stdout', $compilePathJS);
        $changed = true;
      }
    } else { // NO : Parse the ZEP File
      $fs->system($zephirParserBinary . ' ' . $zepRealPath, 'stdout', $compilePathJS);
      $changed = true;
    }

    // Do we have a new Parsed Intermediate File (JS)?
    if ($changed || !$fs->exists($compilePathPHP)) { // YES: Try to build the Final PHP Result
      // Is the Intermediate JS Valid?
      $json = json_decode($fs->read($compilePathJS), true);
      if (!isset($json)) { // NO
        // TODO : $fs->delete($zepRealPath);
        throw new \Exception("Failed to Parse the ZEP File [{$zepRealPath}]");
      }
      $data = '<?php return ' . var_export($json, true) . ';';
      $fs->write($compilePathPHP, $data);
    }

    return $compilePathPHP;
  }

  /**
   * 
   * @param type $realPath
   * @return string
   */
  protected function _compilePath($realPath) {
    // Get File System from Dependency Injector
    $fs = $this->_di['fileSystem'];

    // Produce a Base Output File Name for the Given Name
    $normalizedPath = str_replace(array(DIRECTORY_SEPARATOR, ":", '/'), '_', $realPath);
    $compilePath = $fs->realpath($normalizedPath, IFileSystem::OUTPUT);
    return $compilePath;
  }

  protected function _compile($ir) {
    echo "<?php\n";
    foreach ($ir as $index => $ast) {
      $this->_redirectAST($ast['type'], $ast);
      /*
        switch ($ast['type']) {
        case 'comment':
        echo "/{$ast['value']}/\n";
        break;
        case 'namespace':
        echo "namespace {$ast['name']};\n";
        break;
        case 'class':
        break;
        default:
        echo "AST TYPE [{$ast['type']}]\n";
        }
       */
    }
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

  protected function _emitNamespace($ast) {
    /* TODO:
     * Space between 'namespace' and {name}
     */
    echo "namespace {$ast['name']};\n";
  }

  protected function _emitClass($ast) {
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


    /* TODO:
     * Space between $modifiers and and {name}
     * Carriage Return after {name} and extends/implements
     * Carriage Return between extends/implements
     * Carriage Return before '{'
     */
    $modifiers = '';
    $space = '';
    if ($ast['final']) {
      $modifiers = 'final';
      $space = ' ';
    } else if ($ast['abstract']) {
      $modifiers = 'abstract';
      $space = ' ';
    }

    echo "{$modifiers}{$space}class {$ast['name']}";
    $implements = isset($ast['implements']) ? $ast['implements'] : null;
    if (isset($implements)) {
      echo "\n";
      $first = true;
      foreach ($implements as $interace) {
        if (!$first) {
          // TODO Add Option to place linefeed before each interface
          echo ', ';
        }
        $this->_emitVariable($interace, true);
        $first = false;
      }
      /* TODO HAndle Case in Which we have implements (but not extends with respect to
       * line feeds see oo/oonativeinterfaces
       */
    }
    $extends = isset($ast['extends']) ? $ast['extends'] : null;
    if (isset($extends)) {
      echo "\n";
      echo "{$extends} ";
    }
    echo "{\n";

    $sections = isset($ast['definition']) ? $ast['definition'] : null;
    if (isset($sections)) {
      $sectionsOrder = ['constants', 'properties', 'methods'];

      foreach ($sectionsOrder as $order) {
        if (isset($sections[$order])) {
          $this->_redirectAST($order, $sections[$order], 'class');
        }
      }
    }
    echo "}\n";
  }

  protected function _emitInterface($ast) {
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


    /* TODO:
     * Carriage Return after {name} and extends
     * Carriage Return before '{'
     */
    echo "interface {$ast['name']}";
    $extends = isset($ast['extends']) ? $ast['extends'] : null;
    if (isset($extends)) {
      echo "\n";
      echo "{$extends} ";
    }
    echo "{\n";

    $sections = isset($ast['definition']) ? $ast['definition'] : null;
    if (isset($sections)) {
      $sectionsOrder = ['constants', 'properties', 'methods'];

      foreach ($sectionsOrder as $order) {
        if (isset($sections[$order])) {
          $this->_redirectAST($order, $sections[$order], 'class');
        }
      }
    }
    echo "}\n";
  }

  protected function _processVisibility($visibility) {
    return implode(' ', $visibility);
  }

  protected function _emitClassConstants($astconsts) {
    /* const CONSTANT = 'constant value'; */
    foreach ($astconsts as $astconst) {
      echo "const {$astconst['name']}";
      $this->_emitClassPropertyDefault($astconst['default']);
      echo ";\n";
    }
  }

  protected function _emitClassProperties($astprops) {
    foreach ($astprops as $astprop) {
      $visibility = $this->_processVisibility($astprop['visibility']);
      echo "{$visibility} \${$astprop['name']}";
      if (isset($astprop['default'])) {
        $this->_emitClassPropertyDefault($astprop['default']);
      }
      echo ";\n";
    }
  }

  protected function _emitClassPropertyDefault($astdefault) {
    /* TODO
     * Space Before and After '='
     */
    echo ' = ';
    $type = $astdefault['type'];
    $this->_redirectAST($type, $astdefault);
  }

  protected function _emitClassMethods($astmethods) {
    foreach ($astmethods as $astmethod) {
      $visibility = $this->_processVisibility($astmethod['visibility']);
      if (isset($astmethod['docblock'])) {
        echo "/{$astmethod['docblock']}/\n";
      }
      echo "{$visibility} function {$astmethod['name']}(";
      if (isset($astmethod['parameters'])) {
        $this->_emitParameters($astmethod['parameters']);
      }
      echo ") {\n";
      if (isset($astmethod['statements'])) {
        $this->_emitStatements($astmethod['statements']);
      }
      echo "}\n";
    }
  }

  protected function _emitParameters($astparams) {
    $first = true;
    foreach ($astparams as $astparam) {
      if (!$first) {
        echo ', ';
      }
      $this->_redirectAST($astparam['type'], $astparam);
      $first = false;
    }
  }

  protected function _emitParameter($astparam) {
    echo "\${$astparam['name']}";
  }

  protected function _emitStatements($aststats) {
    foreach ($aststats as $aststat) {
      $this->_redirectAST($aststat['type'], $aststat, 'statement');
    }
  }

  protected function _emitStatementMcall($ast) {
    /* STATEMENT Method Calls Have a Nested AST Structure, which diferentiate
     * it from EXPRESSION Method Calls (i.e. all statements, have expressions
     * and an expression can be used as part of statements)
      [ 'type' => 'mcall',
      'expr' => [
      'type' => 'mcall'
     */
    $mcall = $ast['expr']; // Extract Internal AST Element
    $this->_emitMcall($mcall);
    echo ";\n";
  }

  protected function _emitStatementFcall($ast) {
    /* STATEMENT Method Calls Have a Nested AST Structure, which diferentiate
     * it from EXPRESSION Method Calls (i.e. all statements, have expressions
     * and an expression can be used as part of statements)
      [ 'type' => 'mcall',
      'expr' => [
      'type' => 'mcall'
     */
    $fcall = $ast['expr']; // Extract Internal AST Element
    $this->_emitFcall($fcall);
    echo ";\n";
  }

  protected function _emitStatementDeclare($ast) {
    /* PHP Doesn't Require Declaration so do nothing for pure Declares,
     * if the declaration assigns a default value treat it as if was a 'let' statement
     */
    $assignments = $ast['variables'];
    foreach ($assignments as $assignment) {
      if (isset($assignment['expr'])) {
        echo "\${$assignment['variable']} = ";
        $expr = $assignment['expr'];
        $this->_emitExpression($expr);
        echo ";\n";
      }
    }
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

  protected function _emitStatementContinue($ast) {
    echo "continue;\n";
  }

  protected function _emitStatementBreak($ast) {
    echo "break;\n";
  }

  protected function _emitStatementReturn($ast) {
    echo "return ";
    if (isset($ast['expr'])) {
      $this->_emitExpression($ast['expr']);
    }
    echo ";\n";
  }

  protected function _emitStatementThrow($ast) {
    echo "throw ";
    if (isset($ast['expr'])) {
      $this->_emitExpression($ast['expr']);
    }
    echo ";\n";
  }

  protected function _emitStatementUnset($ast) {
    $expr = $ast['expr'];
    echo 'unset(';
    $this->_emitExpression($expr);
    echo ");\n";
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

  protected function _emitAdd($ast) {
    $left = $ast['left'];
    $right = $ast['right'];

    $this->_redirectAST($left['type'], $left);
    echo ' + ';
    $this->_redirectAST($right['type'], $right);
  }

  protected function _emitSub($ast) {
    $left = $ast['left'];
    $right = $ast['right'];

    $this->_redirectAST($left['type'], $left);
    echo ' - ';
    $this->_redirectAST($right['type'], $right);
  }

  protected function _emitMul($ast) {
    $left = $ast['left'];
    $right = $ast['right'];

    $this->_redirectAST($left['type'], $left);
    echo ' * ';
    $this->_redirectAST($right['type'], $right);
  }

  protected function _emitDiv($ast) {
    $left = $ast['left'];
    $right = $ast['right'];

    $this->_redirectAST($left['type'], $left);
    echo ' / ';
    $this->_redirectAST($right['type'], $right);
  }

  protected function _emitMod($ast) {
    $left = $ast['left'];
    $right = $ast['right'];

    $this->_redirectAST($left['type'], $left);
    echo ' % ';
    $this->_redirectAST($right['type'], $right);
  }

  protected function _emitConcat($ast) {
    $left = $ast['left'];
    $right = $ast['right'];

    $this->_redirectAST($left['type'], $left);
    echo ' . ';
    $this->_redirectAST($right['type'], $right);
  }

  protected function _emitAnd($ast) {
    $left = $ast['left'];
    $right = $ast['right'];

    $this->_redirectAST($left['type'], $left);
    echo ' && ';
    $this->_redirectAST($right['type'], $right);
  }

  protected function _emitOr($ast) {
    $left = $ast['left'];
    $right = $ast['right'];

    $this->_redirectAST($left['type'], $left);
    echo ' || ';
    $this->_redirectAST($right['type'], $right);
  }

  protected function _emitBitwiseOr($ast) {
    $left = $ast['left'];
    $right = $ast['right'];

    $this->_redirectAST($left['type'], $left);
    echo ' | ';
    $this->_redirectAST($right['type'], $right);
  }

  protected function _emitBitwiseAnd($ast) {
    $left = $ast['left'];
    $right = $ast['right'];

    $this->_redirectAST($left['type'], $left);
    echo ' & ';
    $this->_redirectAST($right['type'], $right);
  }

  protected function _emitBitwiseXor($ast) {
    $left = $ast['left'];
    $right = $ast['right'];

    $this->_redirectAST($left['type'], $left);
    echo ' ^ ';
    $this->_redirectAST($right['type'], $right);
  }

  protected function _emitBitwiseShiftleft($ast) {
    $left = $ast['left'];
    $right = $ast['right'];

    $this->_redirectAST($left['type'], $left);
    echo ' << ';
    $this->_redirectAST($right['type'], $right);
  }

  protected function _emitBitwiseShiftright($ast) {
    $left = $ast['left'];
    $right = $ast['right'];

    $this->_redirectAST($left['type'], $left);
    echo ' >> ';
    $this->_redirectAST($right['type'], $right);
  }

  protected function _emitInstanceof($ast) {
    $left = $ast['left'];
    $right = $ast['right'];

    $this->_redirectAST($left['type'], $left);
    echo ' instanceof ';
    $this->_redirectAST($right['type'], $right);
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
  protected function _emitMcall($ast) {
    /* TODO If a variable is of type (array or string) we have to verify
     * if it's using a fake object calls, this requires that we capture and 
     * maintain parse data (i.e. if parameters are of type string/array).
     * If they are declared 
     */
    $object = $ast['variable'];
    $objtype = $object['type'];
    switch ($objtype) {
      case 'string':
        $this->_emitMcallString($ast);
        break;
      case 'array':
        $this->_emitMcallArray($ast);
        break;
      default:
        $this->_emitVariable($object);
        echo "->{$ast['name']}(";
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

  protected function _emitNew($ast) {
    echo "new {$ast['class']}(";
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

  protected function _emitDouble($ast) {
    echo $ast['value'];
  }

  protected function _emitInt($ast) {
    echo $ast['value'];
  }

  protected function _emitBool($ast) {
    echo $ast['value'];
  }

  protected function _emitNull($ast) {
    echo 'null';
  }

  protected function _emitString($ast) {
    echo "\"{$ast['value']}\"";
  }

  protected function _emitChar($ast) {
    echo "'{$ast['value']}'";
  }

  protected function _emitArray($ast) {
    $elements = $ast['left'];
    $first = true;
    echo '[';
    foreach ($elements as $element) {
      if (!$first) {
        echo ', ';
      }
      $key = isset($element['key']) ? $element['key'] : null;
      if (isset($key)) {
        $this->_redirectAST($key['type'], $key);
        echo '=>';
      }
      $value = $element['value'];
      $this->_redirectAST($value['type'], $value);
      $first = false;
    }
    echo ']';
  }

  protected function _emitEmptyArray($ast) {
    echo '[]';
  }

  protected function _emitVariable($ast, $property = false) {
    echo $property ? $ast['value'] : "\${$ast['value']}";
  }

  protected function _emitConstant($ast) {
    echo $ast['value'];
  }

  protected function _redirectAST($type, $ast, $prefix = null) {
    $type = ucfirst($type);
    if (strpos($type, '-') !== FALSE) {
      $type = implode('', array_map('ucfirst', explode('-', $type)));
    }
    if (strpos($type, '_') !== FALSE) {
      $type = implode('', array_map('ucfirst', explode('_', $type)));
    }
    if (isset($prefix)) {
      $emitter = '_emit' . ucfirst($prefix) . $type;
    } else {
      $emitter = '_emit' . $type;
    }
    if (method_exists($this, $emitter)) {
      $this->$emitter($ast);
    } else {
      if (isset($prefix)) {
        throw new \Exception("Function [_redirectAST] - Handler for [{$prefix}], type [{$type}] NOT FOUND");
      } else {
        throw new \Exception("Function [_redirectAST] - Handler for type [{$type}] NOT FOUND");
      }
    }
    return true;
  }

}
