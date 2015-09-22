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
use Zephir\PHP\Phases\InlineComments;
use Zephir\PHP\Phases\InlineShortcuts;

/**
 * Normalizes the IR Ast to make for easier parsing
 * 
 * @author Paulo Ferreira <pf at sourcenotes.org>
 */
class Process implements IStage {

  // Mixins
  use \Zephir\Common\Mixins\DI;

  protected $_comments = [];
  protected $_phases = [];

  /**
   * 
   */
  public function __destruct() {
    // TODO HOW 
    $this->_comments = null;
    $this->_phases = null;
  }

  /**
   * Initialize the Stage Instance
   * 
   * @return self Return instance of stage for Function Linking.
   */
  public function initialize() {
    // Create Phases
    $this->_phases[] = new InlineComments();
    $this->_phases[] = new InlineShortcuts();

    // Initialize Phases
    $di = $this->getDI();
    foreach ($this->_phases as $phase) {
      $phase->setDI($di);
    }

    return $this;
  }

  /**
   * Compile or Transform the AST.
   * 
   * @param array $ast AST to be compiled/transformed
   * @return array Old or Transformed AST
   */
  public function compile($ast) {
    $newAST = [];

    foreach ($ast as $index => $entry) {

      // Process Top Level Statements
      foreach ($this->_phases as $phase) {
        $entry = $phase->top($entry);

        // Is the entry to be processed further?
        if (!isset($entry)) { // NO:
          continue;
        }
      }

      if (isset($entry)) {
        switch ($entry['type']) {
          case 'class':
            $entry = $this->_compileClass($entry);
        }

        // Add Entry to List of Statements
        $newAST[] = $entry;
      }
    }

    return $newAST;
  }

  /**
   * Converts a Comment AST Entry into a Comment Block Entry, to be merged into
   * the AST of the Next Statement Block
   * 
   * @param array $ast Comment AST
   * @return array Comment Block Entry or Null, if an empty comment
   */
  protected function _compileClass($class) {
    // NOTE: Requires Normalized Class Definition as Created by the Compact Stage

    $sections = ['constants', 'properties', 'methods'];
    foreach ($sections as $section) {

      // Process Each Individual Entry in the Section
      $entries = $class[$section];
      $newEntries = [];

      // Pass All Entries in the Section - Through All the Phases (In Sequence)
      foreach ($entries as $key => $entry) {
        foreach ($this->_phases as $phase) {
          switch ($section) {
            case 'constants':
              $entry = $phase->constant($class, $entry);
              break;
            case 'properties':
              $entry = $phase->property($class, $entry);
              break;
            case 'methods':
              $entry = $phase->method($class, $entry);
              break;
          }

          // Is the entry to be processed further?
          if (!isset($entry)) { // NO: Break Loop
            break;
          }
        }

        // Was the Entry Removed?
        if (isset($entry)) { // NO
          $newEntries[$key] = $entry;
        }
      }


      /* NOTE: We directly set only the properties definition, and not use the
       * shortcut $definition, because the actuall class definition, might be
       * changed, due to the processing of the properties, specifically,
       * ZEP property shortcuts, add methods, to the class definiion.
       */
      $class[$section] = $newEntries;
    }

    return $class;
  }

  protected function _processMethod($class, $method) {
    $statements = [];
    if (count($method['statements'])) {
      $statements = $this->_statementBlock($class, $method, $method['statements']);
    }
    $method['statements'] = $statements;
    return $method;
  }

  /**
   * Process a Block of Statements
   * 
   * @param type $class
   * @param type $method
   * @param type $block
   */
  protected function _statementBlock($class, $method, $block) {
    $statements = [];
    foreach ($block as $statement) {
      $type = $statement['type'];
      $local_handler = "_statement".ucfirst($type);
    }
  }

}
