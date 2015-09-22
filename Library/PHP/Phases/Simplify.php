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
 * Normalizes the IR Ast to make for easier parsing
 * 
 * @author Paulo Ferreira <pf at sourcenotes.org>
 */
class InlineShortcuts implements IPhase {

  // Mixins
  use \Zephir\Common\Mixins\DI;

  protected $_comments = [];

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
   * Process Class or Interface Property
   * 
   * @param array $class Class Definition
   * @param array $property Class Property Definition
   * @return array New Property Definition, 'NULL' if to be removed
   * @throws \Exception On error Parsing Property
   */
  public function property(&$class, $property) {
    // Does the Property have Shortcuts Defined?
    $shortcuts = isset($property['shortcuts']) ? $property['shortcuts'] : null;
    if (isset($shortcuts)) { // YES
      $methods = [];

      // Process All of the Properties Shortcuts
      $processed = [];
      foreach ($shortcuts as $shortcut) {
        // Have we already processed the shortcut?
        if (in_array($shortcut['name'], $processed)) { // YES
          throw new \Exception("Shortcut [{$shortcut['name']}] is used multiple times in Property [{$property['name']}].");
        }
        
        // Create Method for Shortcut
        $method = $this->_expandShortcut($property, $shortcut);
        if (isset($method)) {
          $methods[] = $method;
        }
        
        // Add Shortcut to List of Processed Shortcuts
        $processed[] = $shortcut['name'];
      }

      // Do we have Shortcuts to Add?
      if (count($methods)) { // YES        
        $classMethods = $this->_getMethodsDefinition($class);

        $methodsList = $this->_classMethodNames($classMethods);
        foreach ($methods as $method) {
          $name = $method['name'];
          if (in_array($name, $methodsList)) {
            throw new \Exception("Class Method [{$name}] already exists.");
          }

          $classMethods[] = $method;
        }

        // Update Class
        $class['definition']['methods'] = $classMethods;
      }

      // TODO Handle toString Shortcut      
      unset($property['shortcuts']);
    }

    return $property;
  }

  /**
   * 
   * @param type $class
   * @return array
   */
  protected function _getMethodsDefinition(&$class) {

    if (!isset($class['definition'])) {
      $class['definition'] = [
        'methods' => []
      ];
    } else if (!isset($class['definition']['methods'])) {
      $class['definition']['methods'] = [];
    }

    return $class['definition']['methods'];
  }

  /**
   * 
   * @param type $methods
   * @return type
   */
  protected function _classMethodNames($methods) {
    $names = [];
    foreach ($methods as $method) {
      $names[] = $method['name'];
    }

    return $names;
  }

  protected function _expandShortcut($property, $shortcut) {
    // Shortcut Type
    $type = $shortcut['name'];


    // Basic Function Definition
    $methodName = $type === 'toString' ? '__toString' : $shortcut['name'] . ucfirst($property['name']);
    $method = [
      'visibility' => ['public'],
      'type' => 'method',
      'name' => $methodName,
      'statements' => []
    ];

    switch ($type) {
      case 'toString':
      case 'get':
        $method['statements'][] = [
          'type' => 'return',
          'expr' => [
            'type' => 'property-access',
            'left' => [
              'type' => 'variable',
              'value' => 'this',
              'file' => $shortcut['file'],
              'line' => $shortcut['line'],
              'char' => $shortcut['char'],
            ],
            'right' => [
              'type' => 'variable',
              'value' => $property['name'],
              'file' => $shortcut['file'],
              'line' => $shortcut['line'],
              'char' => $shortcut['char'],
            ],
            'file' => $shortcut['file'],
            'line' => $shortcut['line'],
            'char' => $shortcut['char'],
          ],
          'file' => $shortcut['file'],
          'line' => $shortcut['line'],
          'char' => $shortcut['char'],
        ];
        break;
      case 'set':
        // Add Parameter to Function
        $pname = "__p_{$property['name']}__";
        $method['parameters'][] = [
          'type' => 'parameter',
          'name' => $pname,
          'const' => 0,
          'data-type' => 'variable',
          'mandatory' => 0,
          'reference' => 0,
          'file' => $shortcut['file'],
          'line' => $shortcut['line'],
          'char' => $shortcut['char'],
        ];

        /* TODO: See if Class Properties Can have Declared Types
         * If so, we need to add a declared type
          'cast' => [
          'type' => 'variable',
          'value' => 'DiInterface',
          'file' => '/home/pj/WEBPROJECTS/zephir/test/router.zep',
          'line' => 107,
          'char' => 55,
          ],
         */
        $method['statements'][] = [
          'type' => 'let',
          'assignments' => [
            [
              'assign-type' => 'object-property',
              'operator' => 'assign',
              'variable' => 'this',
              'property' => $property['name'],
              'expr' => [
                'type' => 'variable',
                'value' => $pname,
                'file' => $shortcut['file'],
                'line' => $shortcut['line'],
                'char' => $shortcut['char'],
              ],
              'file' => $shortcut['file'],
              'line' => $shortcut['line'],
              'char' => $shortcut['char'],
            ],
          ],
          'file' => $shortcut['file'],
          'line' => $shortcut['line'],
          'char' => $shortcut['char'],
        ];
    }

    return $method;
  }

}
